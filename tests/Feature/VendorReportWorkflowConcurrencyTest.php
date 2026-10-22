<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorReport;
use App\Services\VendorReportSubmissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VendorReportWorkflowConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Concurrency requires the disposable MySQL/MariaDB test server.');
        }
        $this->assertTrue($this->app->environment('testing'));
        $this->assertSame('vendorreport_workflow_test', config('database.connections.mysql.database'));
        $this->assertEmpty(config('database.connections.mysql.url'));
        $socket = config('database.connections.mysql.unix_socket');
        $this->assertStringStartsWith('/tmp/vendorreport-workflow-db-', $socket);
        $this->assertSame(dirname($socket).'/data', rtrim(DB::selectOne('SELECT @@datadir AS directory')->directory, '/'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Notification::fake();
    }

    #[DataProvider('competingOperations')]
    public function test_only_one_competing_operation_commits(string $first, string $second): void
    {
        foreach (['requester', 'internal_control', 'bod', 'admin_system'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
        $department = Department::create(['code' => 'RACE', 'name' => 'Concurrency fixture', 'is_active' => true]);
        $actors = [];
        foreach (['requester', 'internal_control', 'bod', 'admin_system'] as $role) {
            $actors[$role] = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
            $actors[$role]->assignRole($role);
        }
        $report = VendorReport::create([
            'title' => 'Concurrent transition', 'workflow_type' => 'INTERNAL_CONTROL_BOD',
            'created_by' => $actors['requester']->id, 'status' => 'DRAFT',
        ]);
        if ($first !== 'submit') {
            app(VendorReportSubmissionService::class)->submit($report);
        }
        $directory = sys_get_temp_dir().'/vendorreport-race-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $processes = [];
        DB::beginTransaction();
        try {
            VendorReport::query()->lockForUpdate()->findOrFail($report->id);
            foreach ([$first, $second] as $index => $operation) {
                $actor = $operation === 'submit' ? $actors['requester'] : ($operation === 'cancel' ? $actors['admin_system'] : $actors['internal_control']);
                $command = [PHP_BINARY, '-r', $this->workerScript(), base_path(), (string) $report->id,
                    (string) $actor->id, (string) $actors['bod']->id, $operation, "$directory/$index"];
                $processes[] = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', "$directory/$index.log", 'w'], 2 => ['file', "$directory/$index.log", 'a']], $pipes);
                $this->assertIsResource($processes[$index]);
            }
            $deadline = microtime(true) + 10;
            while (! (file_exists("$directory/0.ready") && file_exists("$directory/1.ready")) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists("$directory/0.ready", file_get_contents("$directory/0.log"));
            $this->assertFileExists("$directory/1.ready", file_get_contents("$directory/1.log"));
            // Both callers have read the old snapshot. Release the row lock to let them compete.
            DB::commit();
            $deadline = microtime(true) + 10;
            while (! (file_exists("$directory/0.result") && file_exists("$directory/1.result")) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $results = [];
            foreach ([0, 1] as $index) {
                $this->assertFileExists("$directory/$index.result", file_get_contents("$directory/$index.log"));
                $results[] = json_decode(file_get_contents("$directory/$index.result"), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertCount(1, array_filter($results, fn ($result) => $result['success']), json_encode($results));
            $this->assertCount(1, array_filter($results, fn ($result) => ! $result['success']), json_encode($results));
            $this->assertSame(2, $report->approvalSteps()->count());
            $events = $first === 'submit' ? ['submitted'] : ['approved', 'rejected', 'canceled'];
            $this->assertSame(1, DB::table('activity_log')->where('subject_id', $report->id)->whereIn('description', $events)->count());
            $loser = array_values(array_filter($results, fn ($result) => ! $result['success']))[0];
            $this->assertSame(0, $loser['notifications'], 'The losing operation must not notify anyone.');
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if (is_resource($process)) {
                    if (proc_get_status($process)['running']) {
                        proc_terminate($process);
                    }
                    proc_close($process);
                }
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public static function competingOperations(): array
    {
        return [
            'duplicate submit' => ['submit', 'submit'],
            'two approvals' => ['approve', 'approve'],
            'approval versus rejection' => ['approve', 'reject'],
            'approval versus cancellation' => ['approve', 'cancel'],
        ];
    }

    private function workerScript(): string
    {
        return <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Notification::fake();
$report = App\Models\VendorReport::findOrFail($argv[2]);
$actor = App\Models\User::findOrFail($argv[3]);
Illuminate\Support\Facades\Auth::setUser($actor);
file_put_contents($argv[6].'.ready', 'ready');
try {
    $service = $app->make(App\Services\VendorReportApprovalService::class);
    match ($argv[5]) {
        'submit' => $app->make(App\Services\VendorReportSubmissionService::class)->submit($report),
        'approve' => $service->approve($report, $actor, null, (int) $argv[4]),
        'reject' => $service->reject($report, $actor, 'Concurrent rejection'),
        'cancel' => $service->cancel($report, $actor, 'Concurrent cancellation'),
    };
    $result = ['success' => true];
} catch (Throwable $exception) {
    $result = ['success' => false, 'error' => $exception->getMessage()];
}
$result['notifications'] = collect(Illuminate\Support\Facades\Notification::sentNotifications())->flatten()->count();
file_put_contents($argv[6].'.result', json_encode($result));
PHP;
    }
}
