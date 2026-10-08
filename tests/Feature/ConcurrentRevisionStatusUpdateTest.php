<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentRevisionStatusUpdateTest extends TestCase
{
    public function test_concurrent_updates_succeed_and_record_a_consistent_history(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'revision-concurrency-');
        $process = null;
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
        DB::purge('sqlite');

        try {
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
            $user = User::factory()->create();
            $project = Project::create(['user_id' => $user->id, 'name' => '案件']);
            $revision = $project->revisionRequests()->create(['content' => '依頼']);

            $code = <<<'CODE'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[2]]);
Illuminate\Support\Facades\DB::purge('sqlite');
App\Models\RevisionRequest::updating(function () {
    echo "locked\n";
    fflush(STDOUT);
    usleep(500000);
});
$request = Illuminate\Http\Request::create('/', 'PATCH', ['status' => 'in_progress']);
$request->setUserResolver(fn () => App\Models\User::findOrFail($argv[3]));
$app->make(App\Http\Controllers\ProjectController::class)->updateRevision(
    $request,
    App\Models\Project::findOrFail($argv[4]),
    App\Models\RevisionRequest::findOrFail($argv[5])
);
CODE;
            $process = new Process([PHP_BINARY, '-r', $code, base_path(), $database,
                (string) $user->id, (string) $project->id, (string) $revision->id,
            ]);
            $process->setTimeout(10);
            $process->start();
            $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'locked')),
                $process->getErrorOutput());

            $this->actingAs($user)->patch(route('revisions.update', [$project, $revision]), ['status' => 'completed'])
                ->assertRedirect(route('projects.show', $project));
            $this->assertSame(0, $process->wait(), $process->getErrorOutput());

            $this->assertDatabaseHas('revision_requests', ['id' => $revision->id, 'status' => 'completed']);
            $this->assertDatabaseCount('revision_status_histories', 2);
            $this->assertDatabaseHas('revision_status_histories', [
                'revision_request_id' => $revision->id, 'from_status' => 'pending', 'to_status' => 'in_progress',
            ]);
            $this->assertDatabaseHas('revision_status_histories', [
                'revision_request_id' => $revision->id, 'from_status' => 'in_progress', 'to_status' => 'completed',
            ]);
        } finally {
            $process?->stop();
            DB::purge('sqlite');
            unlink($database);
        }
    }
}
