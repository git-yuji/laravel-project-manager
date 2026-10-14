<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_edit_project_without_changing_ownership_or_revisions(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '元の案件', 'description' => '元の説明']);
        $revision = $project->revisionRequests()->create(['content' => '既存の依頼']);
        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()
            ->assertSee(route('projects.update', $project))->assertSee('value="元の案件"', false)->assertSee('元の説明');

        $this->patch(route('projects.update', $project), [
            'name' => '更新後の案件', 'description' => '更新後の説明', 'user_id' => $other->id,
        ])->assertRedirect(route('projects.show', $project))->assertSessionHas('success', '案件情報を更新しました。');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'user_id' => $owner->id,
            'name' => '更新後の案件', 'description' => '更新後の説明']);
        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'project_id' => $project->id,
            'content' => '既存の依頼', 'status' => 'pending']);
        $this->get(route('projects.index'))->assertOk()->assertSee('更新後の案件')->assertDontSee('元の案件');
        $this->get(route('projects.show', $project))->assertOk()->assertSee('更新後の説明')->assertSee('既存の依頼');
    }

    public function test_owner_can_save_maximum_length_values_and_clear_description(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '案件']);
        $this->actingAs($owner);

        $this->patch(route('projects.update', $project), ['name' => str_repeat('案', 100), 'description' => str_repeat('説', 2000)])
            ->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => str_repeat('案', 100), 'description' => str_repeat('説', 2000)]);
        $this->patch(route('projects.update', $project), ['name' => '案件', 'description' => ''])->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'description' => null]);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_does_not_modify_project(array $input, string $field): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '元の案件', 'description' => '元の説明']);

        $this->actingAs($owner)->from(route('projects.show', $project))
            ->patch(route('projects.update', $project), $input)
            ->assertRedirect(route('projects.show', $project))->assertSessionHasErrors($field);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => '元の案件', 'description' => '元の説明']);
    }

    public static function invalidInputs(): array
    {
        return [
            'missing name' => [['description' => '説明'], 'name'],
            'blank name' => [['name' => '   '], 'name'],
            'non-string name' => [['name' => ['案件']], 'name'],
            'long name' => [['name' => str_repeat('案', 101)], 'name'],
            'non-string description' => [['name' => '案件', 'description' => ['説明']], 'description'],
            'long description' => [['name' => '案件', 'description' => str_repeat('説', 2001)], 'description'],
        ];
    }

    public function test_validation_failure_preserves_input_and_opens_edit_form(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '元の案件', 'description' => '元の説明']);
        $name = str_repeat('案', 101);
        $response = $this->actingAs($owner)->from(route('projects.show', $project))->followingRedirects()
            ->patch(route('projects.update', $project), ['name' => $name, 'description' => '入力中の説明'])
            ->assertOk()->assertSee('入力内容を確認してください。')
            ->assertSee('value="'.$name.'"', false)->assertSee('入力中の説明');
        $this->assertMatchesRegularExpression('/<details\s+open\s*>/', $response->getContent());
    }

    public function test_guests_and_other_users_cannot_update_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '非公開案件', 'description' => '非公開説明']);
        $url = route('projects.update', $project);

        $this->patch($url, ['name' => '不正な変更'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch($url, ['name' => '不正な変更'])->assertNotFound();
        $this->patch($url, ['name' => ''])->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'user_id' => $owner->id,
            'name' => '非公開案件', 'description' => '非公開説明']);
    }

    public function test_edit_form_escapes_project_values(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '"><script>name</script>',
            'description' => '</textarea><script>description</script>']);

        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()
            ->assertSee($project->name)->assertSee($project->description)
            ->assertDontSee('<script>name</script>', false)->assertDontSee('<script>description</script>', false);
    }
}
