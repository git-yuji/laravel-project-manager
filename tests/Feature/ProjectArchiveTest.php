<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_archive_and_restore_without_losing_revisions_or_history(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 0));
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '保存する案件']);
        $revision = $project->revisionRequests()->create(['content' => '保存する依頼', 'status' => 'completed']);
        $history = $revision->statusHistories()->create(['user_id' => $owner->id, 'from_status' => 'pending', 'to_status' => 'completed']);
        $this->actingAs($owner);
        $this->get(route('projects.show', $project))->assertOk()->assertSee('案件をアーカイブ');

        $this->patch(route('projects.archive', $project), ['archived_at' => '2000-01-01', 'user_id' => 999])
            ->assertRedirect(route('projects.show', $project))->assertSessionHas('success');
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'archived_at' => '2026-10-14 12:00:00', 'user_id' => $owner->id]);
        $this->travel(1)->minutes();
        $this->patch(route('projects.archive', $project))->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'archived_at' => '2026-10-14 12:00:00']);
        $this->get(route('projects.index'))->assertOk()->assertDontSee('保存する案件');
        $this->get(route('projects.index', ['archive' => 'archived']))->assertOk()->assertSee('保存する案件');
        $this->get(route('projects.show', $project))->assertOk()->assertSee('進行中に戻す')->assertSee('保存する依頼');

        $this->patch(route('projects.restore', $project))->assertRedirect(route('projects.show', $project));
        $this->patch(route('projects.restore', $project))->assertRedirect();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'archived_at' => null]);
        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'content' => '保存する依頼', 'status' => 'completed']);
        $this->assertDatabaseHas('revision_status_histories', ['id' => $history->id]);
        $this->get(route('projects.index'))->assertOk()->assertSee('保存する案件');
    }

    public function test_archived_search_pagination_clear_and_scope_are_preserved(): void
    {
        $owner = User::factory()->create();
        for ($index = 0; $index < 11; $index++) {
            $project = Project::create(['user_id' => $owner->id, 'name' => '検索案件'.$index]);
            $project->archived_at = now();
            $project->save();
        }
        Project::create(['user_id' => $owner->id, 'name' => '検索案件進行中']);
        $private = Project::create(['user_id' => User::factory()->create()->id, 'name' => '検索案件非公開']);
        $private->archived_at = now();
        $private->save();
        $this->actingAs($owner);
        $url = route('projects.index', ['search' => '検索案件', 'archive' => 'archived']);

        $this->get($url)->assertOk()->assertDontSee('検索案件進行中')->assertDontSee('検索案件非公開')
            ->assertSee('archive=archived&amp;page=2', false)
            ->assertSee('href="'.route('projects.index', ['archive' => 'archived']).'">検索をクリア', false)
            ->assertSee('name="archive" value="archived"', false)
            ->assertViewHas('projects', fn ($projects) => $projects->total() === 11);
        $this->get($url.'&page=2')->assertOk()->assertViewHas('projects', fn ($projects) => $projects->count() === 1);
        $this->get($url.'&page=99')->assertRedirect(route('projects.index', ['search' => '検索案件', 'archive' => 'archived', 'page' => 2]));
    }

    public function test_unauthorized_users_cannot_archive_or_restore(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '非公開']);
        $this->patch(route('projects.archive', $project))->assertRedirect(route('login'));
        $this->patch(route('projects.restore', $project))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch(route('projects.archive', $project))->assertNotFound();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'archived_at' => null]);
        $project->archived_at = now();
        $project->save();
        $this->patch(route('projects.restore', $project))->assertNotFound();
        $this->assertNotNull($project->fresh()->archived_at);
        $this->get(route('projects.index', ['archive' => 'archived']))->assertOk()->assertDontSee('非公開');
    }

    public function test_empty_archived_list_and_invalid_filter(): void
    {
        $this->actingAs(User::factory()->create())->get(route('projects.index', ['archive' => 'archived']))
            ->assertOk()->assertSee('アーカイブ済みの案件はありません。');
        $this->getJson(route('projects.index', ['archive' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('archive');
        $this->getJson(route('projects.index', ['archive' => ['archived']]))->assertUnprocessable()->assertJsonValidationErrors('archive');
        $this->post(route('projects.store'), ['name' => '新しい案件', 'archived_at' => '2026-01-01'])->assertRedirect();
        $this->assertDatabaseHas('projects', ['name' => '新しい案件', 'archived_at' => null]);
    }
}
