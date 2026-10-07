<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/projects')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('ログイン');
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])->assertRedirect('/projects');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_owner_can_create_project_and_manage_revision(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/projects', ['name' => '会社サイト', 'user_id' => 999])->assertRedirect();
        $project = Project::firstOrFail();
        $this->assertSame($user->id, $project->user_id);
        $this->get('/projects')->assertOk()->assertSee('会社サイト');
        $this->post("/projects/{$project->id}/revisions", ['content' => '見出し変更', 'status' => 'completed'])->assertRedirect();
        $revision = $project->revisionRequests()->firstOrFail();
        $this->assertSame('pending', $revision->status);
        $this->get("/projects/{$project->id}")->assertOk()->assertSee('見出し変更');
        $this->patch("/projects/{$project->id}/revisions/{$revision->id}", ['status' => 'completed'])->assertRedirect();
        $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'status' => 'completed']);
    }

    public function test_other_users_cannot_access_or_modify_project(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '非公開案件']);
        $revision = $project->revisionRequests()->create(['content' => '非公開依頼']);
        $this->actingAs(User::factory()->create());
        $this->get('/projects')->assertDontSee('非公開案件');
        $this->get("/projects/{$project->id}")->assertNotFound();
        $this->post("/projects/{$project->id}/revisions", ['content' => '不正な追加'])->assertNotFound();
        $this->patch("/projects/{$project->id}/revisions/{$revision->id}", ['status' => 'completed'])->assertNotFound();
        $this->assertDatabaseCount('revision_requests', 1);
        $this->assertSame('pending', $revision->fresh()->status);
    }

    public function test_validation_and_nested_project_relation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/projects', ['name' => ''])->assertSessionHasErrors('name');
        $this->post('/projects', ['name' => str_repeat('a', 101)])->assertSessionHasErrors('name');
        $project = Project::create(['user_id' => $user->id, 'name' => '案件A']);
        $other = Project::create(['user_id' => $user->id, 'name' => '案件B']);
        $revision = $project->revisionRequests()->create(['content' => '依頼']);
        $this->post("/projects/{$project->id}/revisions", ['content' => '   '])->assertSessionHasErrors('content');
        $this->patch("/projects/{$project->id}/revisions/{$revision->id}", ['status' => 'unknown'])->assertSessionHasErrors('status');
        $this->patch("/projects/{$other->id}/revisions/{$revision->id}", ['status' => 'completed'])->assertNotFound();
        $this->assertSame('pending', $revision->fresh()->status);
    }

    public function test_revisions_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => '案件']);
        $project->revisionRequests()->create(['content' => '未対応の依頼', 'status' => 'pending']);
        $project->revisionRequests()->create(['content' => '完了した依頼', 'status' => 'completed']);
        $this->actingAs($user);
        $this->get("/projects/{$project->id}?status=pending")
            ->assertOk()->assertSee('未対応の依頼')->assertDontSee('完了した依頼');
        $this->get("/projects/{$project->id}?status=")
            ->assertOk()->assertSee('未対応の依頼')->assertSee('完了した依頼');
        $this->get("/projects/{$project->id}?status=in_progress")
            ->assertOk()->assertSee('この対応状況の修正依頼はありません。');
        $this->getJson("/projects/{$project->id}?status=unknown")
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_filter_is_preserved_when_changing_pages(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => '案件']);
        for ($i = 0; $i < 21; $i++) {
            $project->revisionRequests()->create(['content' => "依頼{$i}", 'status' => 'pending']);
        }
        $this->actingAs($user)->get("/projects/{$project->id}?status=pending")
            ->assertOk()->assertSee('status=pending&amp;page=2', false);
    }
}
