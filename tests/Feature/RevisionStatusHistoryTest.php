<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RevisionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisionStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_changes_record_actor_time_and_render_latest_first(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8)->setTime(12, 0));
        $user = User::factory()->create(['name' => '<script>actor</script>']);
        $project = Project::create(['user_id' => $user->id, 'name' => '案件']);
        $revision = $project->revisionRequests()->create(['content' => '依頼']);
        $this->actingAs($user);
        $this->get(route('projects.show', $project))->assertOk()->assertSee('変更履歴はまだありません。');

        $this->patch(route('revisions.update', [$project, $revision]), [
            'status' => 'in_progress', 'user_id' => 999, 'from_status' => 'completed',
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('revision_status_histories', [
            'revision_request_id' => $revision->id, 'user_id' => $user->id,
            'from_status' => 'pending', 'to_status' => 'in_progress',
            'created_at' => '2026-10-08 12:00:00',
        ]);
        $this->travel(1)->minutes();
        $this->patch(route('revisions.update', [$project, $revision]), ['status' => 'completed'])->assertRedirect();
        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'status' => 'completed']);
        $this->assertDatabaseHas('revision_status_histories', [
            'revision_request_id' => $revision->id, 'from_status' => 'in_progress', 'to_status' => 'completed',
        ]);
        $this->assertDatabaseCount('revision_status_histories', 2);
        $this->get(route('projects.show', $project))->assertOk()
            ->assertSeeInOrder(['2026/10/08 12:01:00', '2026/10/08 12:00:00'])
            ->assertSee('未対応 →')->assertSee('対応中 →')
            ->assertSee('<script>actor</script>')->assertDontSee('<script>actor</script>', false);
    }

    public function test_resubmitting_same_status_does_not_add_history(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => '案件']);
        $revision = $project->revisionRequests()->create(['content' => '依頼']);

        $this->actingAs($user)->patch(route('revisions.update', [$project, $revision]), ['status' => 'pending'])
            ->assertRedirect();

        $this->assertDatabaseCount('revision_status_histories', 0);
        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'status' => 'pending']);
    }

    public function test_rejected_requests_leave_status_and_history_unchanged(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '案件']);
        $other = Project::create(['user_id' => $owner->id, 'name' => '別の案件']);
        $revision = $project->revisionRequests()->create(['content' => '依頼']);
        $url = route('revisions.update', [$project, $revision]);

        $this->patch($url, ['status' => 'completed'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch($url, ['status' => 'completed'])->assertNotFound();
        $this->get(route('projects.show', $project))->assertNotFound();
        $this->actingAs($owner)->patch($url, ['status' => 'unknown'])->assertSessionHasErrors('status');
        $this->patch($url, ['status' => 'completed', 'filter_status' => 'unknown'])->assertSessionHasErrors('filter_status');
        $this->patch(route('revisions.update', [$other, $revision]), ['status' => 'completed'])->assertNotFound();

        $this->assertDatabaseCount('revision_status_histories', 0);
        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'status' => 'pending']);
    }

    public function test_status_save_failure_rolls_back_history(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => '案件']);
        $revision = $project->revisionRequests()->create(['content' => '依頼']);
        RevisionRequest::updating(function (): void {
            throw new \RuntimeException('Status save failed');
        });

        try {
            $this->actingAs($user)->patch(route('revisions.update', [$project, $revision]), ['status' => 'completed'])
                ->assertServerError();
            $this->assertDatabaseCount('revision_status_histories', 0);
            $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'status' => 'pending']);
        } finally {
            RevisionRequest::flushEventListeners();
        }
    }
}
