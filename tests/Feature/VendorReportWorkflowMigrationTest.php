<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class VendorReportWorkflowMigrationTest extends TestCase
{
    private const MIGRATION = '2026_10_21_000000_add_internal_control_bod_workflow_type.php';

    private int $creatorId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue($this->app->environment('testing'));
        $driver = config('database.default');
        if (config("database.connections.{$driver}.url")) {
            throw new RuntimeException('Migration tests must not use a database URL override.');
        }
        config(['cache.default' => 'array']);

        if ($driver === 'sqlite') {
            config(['database.connections.sqlite.database' => ':memory:']);
            DB::purge('sqlite');
        } else {
            // A destructive fixture reset is permitted only on the disposable test server.
            $this->assertSame('mysql', $driver);
            $this->assertSame('vendorreport_workflow_test', config('database.connections.mysql.database'));
            $socket = config('database.connections.mysql.unix_socket');
            $this->assertStringStartsWith('/tmp/vendorreport-workflow-db-', $socket);
            $dataDirectory = DB::selectOne('SELECT @@datadir AS directory')->directory;
            $this->assertSame(dirname($socket).'/data', rtrim($dataDirectory, '/'));
            Schema::dropAllTables();
        }

        $paths = array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== self::MIGRATION);

        $this->artisan('migrate', ['--path' => array_values($paths), '--realpath' => true, '--force' => true])
            ->assertSuccessful();

        $this->creatorId = DB::table('users')->insertGetId([
            'name' => 'Migration fixture',
            'email' => 'migration@example.test',
            'password' => 'unused',
        ]);
    }

    public function test_upgrade_accepts_the_new_workflow_but_rejects_unknown_values(): void
    {
        $this->migration()->up();

        $id = $this->insertReport(['workflow_type' => 'INTERNAL_CONTROL_BOD']);
        $this->assertSame('INTERNAL_CONTROL_BOD', DB::table('vendor_reports')->find($id)->workflow_type);

        $this->expectException(QueryException::class);
        $this->insertReport(['workflow_type' => 'UNKNOWN_WORKFLOW']);
    }

    public function test_upgrade_preserves_the_status_constraint_and_draft_default(): void
    {
        $this->migration()->up();

        $id = $this->insertReport();
        $this->assertSame('DRAFT', DB::table('vendor_reports')->find($id)->status);

        $this->expectException(QueryException::class);
        $this->insertReport(['status' => 'UNKNOWN_STATUS']);
    }

    #[DataProvider('newWorkflowRows')]
    public function test_rollback_refuses_to_discard_a_new_workflow_even_when_soft_deleted(?string $deletedAt): void
    {
        $migration = $this->migration();
        $migration->up();
        $id = $this->insertReport(['workflow_type' => 'INTERNAL_CONTROL_BOD', 'deleted_at' => $deletedAt]);

        $blocked = false;

        try {
            $migration->down();
        } catch (RuntimeException $exception) {
            $blocked = true;
            $this->assertStringContainsString('INTERNAL_CONTROL_BOD', $exception->getMessage());
        }

        $this->assertTrue($blocked, 'Rollback must refuse while a report uses the new workflow.');
        $report = DB::table('vendor_reports')->find($id);
        $this->assertSame('INTERNAL_CONTROL_BOD', $report->workflow_type);
        $this->assertSame($deletedAt, $report->deleted_at);
        $this->assertGreaterThan($id, $this->insertReport(['workflow_type' => 'INTERNAL_CONTROL_BOD']));
    }

    public static function newWorkflowRows(): array
    {
        return [
            'active' => [null],
            'soft deleted' => ['2026-10-21 09:00:00'],
        ];
    }

    public function test_upgrade_preserves_legacy_reports_steps_and_revision_links(): void
    {
        $ids = [];
        foreach (['NORMAL', 'SPECIAL_1', 'SPECIAL_2', 'SPECIAL_3', 'URGENT', 'SPECIAL_4'] as $type) {
            $ids[] = $this->insertReport(['workflow_type' => $type, 'code' => '2026/TEST/'.$type]);
        }
        $stepId = DB::table('vendor_report_approval_steps')->insertGetId([
            'report_id' => $ids[0], 'step_key' => 'DEPT_HEAD', 'step_order' => 1,
            'status' => 'PENDING', 'assignee_user_id' => $this->creatorId,
        ]);
        DB::table('vendor_reports')->where('id', $ids[0])->update([
            'status' => 'IN_APPROVAL', 'current_step_id' => $stepId,
        ]);
        DB::table('vendor_reports')->where('id', $ids[1])->update([
            'parent_id' => $ids[0], 'root_id' => $ids[0], 'revision_number' => 2,
        ]);
        $reportsBefore = DB::table('vendor_reports')->orderBy('id')->get()->toArray();
        $stepsBefore = DB::table('vendor_report_approval_steps')->orderBy('id')->get()->toArray();

        $this->migration()->up();

        $this->assertEquals($reportsBefore, DB::table('vendor_reports')->orderBy('id')->get()->toArray());
        $this->assertEquals($stepsBefore, DB::table('vendor_report_approval_steps')->orderBy('id')->get()->toArray());
        $this->assertGreaterThan(max($ids), $this->insertReport(['workflow_type' => 'INTERNAL_CONTROL_BOD']));

        // The foreign key must still perform its declared null-on-delete action.
        DB::table('vendor_report_approval_steps')->where('id', $stepId)->delete();
        $this->assertNull(DB::table('vendor_reports')->find($ids[0])->current_step_id);
    }

    public function test_upgrade_preserves_the_unique_report_code(): void
    {
        $this->insertReport(['code' => '2026/TEST/001']);
        $this->migration()->up();

        $this->expectException(QueryException::class);
        $this->insertReport(['code' => '2026/TEST/001']);
    }

    public function test_upgrade_preserves_foreign_key_enforcement(): void
    {
        $this->migration()->up();

        $this->expectException(QueryException::class);
        $this->insertReport(['created_by' => $this->creatorId + 100000]);
    }

    public function test_rollback_without_new_workflow_rows_keeps_legacy_data_and_restricts_values(): void
    {
        $id = $this->insertReport(['workflow_type' => 'SPECIAL_4']);
        $migration = $this->migration();
        $migration->up();
        $migration->down();

        $this->assertSame('SPECIAL_4', DB::table('vendor_reports')->find($id)->workflow_type);
        $this->assertGreaterThan($id, $this->insertReport(['workflow_type' => 'NORMAL']));
        $this->expectException(QueryException::class);
        $this->insertReport(['workflow_type' => 'INTERNAL_CONTROL_BOD']);
    }

    public function test_failed_sqlite_rebuild_restores_schema_data_and_foreign_keys(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite table rebuild and PRAGMA recovery are specific to SQLite.');
        }

        // The historical SQLite add-current-step migration lost the enum CHECK constraints.
        $id = $this->insertReport(['status' => 'INVALID_LEGACY_STATUS']);
        $schemaBefore = DB::scalar("SELECT sql FROM sqlite_master WHERE name = 'vendor_reports'");
        $foreignKeysBefore = DB::scalar('PRAGMA foreign_keys');
        $failed = false;
        try {
            $this->migration()->up();
        } catch (QueryException) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Invalid legacy data must fail the migration without partial changes.');
        $this->assertSame($schemaBefore, DB::scalar("SELECT sql FROM sqlite_master WHERE name = 'vendor_reports'"));
        $this->assertSame('INVALID_LEGACY_STATUS', DB::table('vendor_reports')->find($id)->status);
        $this->assertSame($foreignKeysBefore, DB::scalar('PRAGMA foreign_keys'));
        $this->assertFalse(Schema::hasTable('__temp__vendor_reports'));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION);
    }

    private function insertReport(array $attributes = []): int
    {
        return DB::table('vendor_reports')->insertGetId(array_merge([
            'title' => 'Migration report',
            'workflow_type' => 'NORMAL',
            'created_by' => $this->creatorId,
        ], $attributes));
    }
}
