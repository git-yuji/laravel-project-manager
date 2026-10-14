<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RevisionContentEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_edit_content_without_changing_status_history_or_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '案件']);
        $other = Project::create(['user_id' => $owner->id, 'name' => '別案件']);
        $revision = $project->revisionRequests()->create(['content' => '元の依頼', 'status' => 'completed']);
        $history = $revision->statusHistories()->create(['user_id' => $owner->id, 'from_status' => 'pending', 'to_status' => 'completed']);
        $content = '</textarea><script>alert(1)</script>';

        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()
            ->assertSee(route('revisions.content.update', [$project, $revision]));
        $this->patch(route('revisions.content.update', [$project, $revision]), [
            'edit_content' => [$revision->id => $content], 'status' => 'pending', 'project_id' => $other->id,
            'filter_status' => 'completed', 'page' => 1,
        ])->assertRedirect(route('projects.show', ['project' => $project, 'status' => 'completed', 'page' => 1]))
            ->assertSessionHas('success', '修正依頼の内容を更新しました。');

        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'project_id' => $project->id, 'content' => $content, 'status' => 'completed']);
        $this->assertDatabaseCount('revision_status_histories', 1);
        $this->assertDatabaseHas('revision_status_histories', ['id' => $history->id, 'from_status' => 'pending', 'to_status' => 'completed']);
        $this->get(route('projects.show', $project))->assertOk()->assertSee($content)->assertDontSee($content, false);
    }

    public function test_content_edit_preserves_filter_and_second_page(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '案件']);
        for ($index = 0; $index < 21; $index++) {
            $project->revisionRequests()->create(['content' => '依頼'.$index]);
        }
        $this->actingAs($owner);
        $url = route('projects.show', ['project' => $project, 'status' => 'pending', 'page' => 2]);
        $revision = $project->revisionRequests()->latest()->skip(20)->firstOrFail();

        $this->get($url)->assertOk()->assertSee('name="page" value="2"', false);
        $this->patch(route('revisions.content.update', [$project, $revision]), [
            'edit_content' => [$revision->id => str_repeat('案', 2000)], 'filter_status' => 'pending', 'page' => 2,
        ])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee(str_repeat('案', 2000));
        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'content' => str_repeat('案', 2000)]);
    }

    #[DataProvider('invalidContents')]
    public function test_invalid_content_returns_to_page_without_modifying_records(mixed $content): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '案件']);
        $revision = $project->revisionRequests()->create(['content' => '元の依頼']);
        $other = $project->revisionRequests()->create(['content' => '別の依頼']);
        $url = route('projects.show', $project);

        $response = $this->actingAs($owner)->from($url)->followingRedirects()
            ->patch(route('revisions.content.update', [$project, $revision]), ['edit_content' => [$revision->id => $content]])
            ->assertOk()->assertSee('入力内容を確認してください。')->assertSee('別の依頼');
        $this->assertMatchesRegularExpression('/<details\s+open\s*>/', $response->getContent());
        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'content' => '元の依頼', 'status' => 'pending']);
        $this->assertDatabaseHas('revision_requests', ['id' => $other->id, 'content' => '別の依頼']);
        $this->assertDatabaseCount('revision_status_histories', 0);
    }

    public static function invalidContents(): array
    {
        return ['null' => [null], 'blank' => ['   '], 'array' => [['依頼']], 'too long' => [str_repeat('案', 2001)]];
    }

    public function test_validation_preserves_input_only_in_target_edit_form(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '案件']);
        $revision = $project->revisionRequests()->create(['content' => '元の依頼']);
        $other = $project->revisionRequests()->create(['content' => '別の依頼']);
        $content = str_repeat('案', 2001).'<script>input</script>';

        $response = $this->actingAs($owner)->from(route('projects.show', $project))->followingRedirects()
            ->patch(route('revisions.content.update', [$project, $revision]), ['edit_content' => [$revision->id => $content]])
            ->assertOk()->assertSee($content)->assertDontSee('<script>input</script>', false);
        $this->assertSame(1, substr_count($response->getContent(), e($content)));
        $response->assertSee('name="edit_content['.$other->id.']"', false)->assertSee('別の依頼');
    }

    public function test_guests_other_users_and_mismatched_projects_cannot_edit_content(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '案件']);
        $other = Project::create(['user_id' => $owner->id, 'name' => '別案件']);
        $revision = $project->revisionRequests()->create(['content' => '元の依頼']);
        $payload = ['edit_content' => [$revision->id => '不正な変更']];
        $url = route('revisions.content.update', [$project, $revision]);

        $this->patch($url, $payload)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch($url, $payload)->assertNotFound();
        $this->actingAs($owner)->patch(route('revisions.content.update', [$other, $revision]), $payload)->assertNotFound();
        $this->patch($url, ['edit_content' => []])->assertSessionHasErrors('edit_content.'.$revision->id);
        $this->patch($url, $payload + ['filter_status' => 'invalid'])->assertSessionHasErrors('filter_status');
        $this->patch($url, $payload + ['page' => 0])->assertSessionHasErrors('page');
        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'content' => '元の依頼']);
    }
}
