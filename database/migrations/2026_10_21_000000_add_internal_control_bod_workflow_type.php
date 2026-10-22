<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite must disable FK checks before the transaction that rebuilds this cyclic table.
    public $withinTransaction = false;

    private const LEGACY_TYPES = ['NORMAL', 'SPECIAL_1', 'SPECIAL_2', 'SPECIAL_3', 'URGENT', 'SPECIAL_4'];

    public function up(): void
    {
        $this->changeWorkflowTypes([...self::LEGACY_TYPES, 'INTERNAL_CONTROL_BOD']);
    }

    public function down(): void
    {
        // Query the table directly so soft-deleted reports also block a destructive shrink.
        if (DB::table('vendor_reports')->where('workflow_type', 'INTERNAL_CONTROL_BOD')->exists()) {
            throw new RuntimeException('Cannot remove INTERNAL_CONTROL_BOD while vendor reports still use it, including soft-deleted reports.');
        }

        $this->changeWorkflowTypes(self::LEGACY_TYPES);
    }

    private function changeWorkflowTypes(array $types): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('vendor_reports', function (Blueprint $table) use ($types) {
                $table->enum('workflow_type', $types)->change();
            });

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException("Unsupported database driver for vendor report workflow migration: {$driver}");
        }

        $connection = DB::connection();
        if ($connection->transactionLevel() !== 0) {
            throw new RuntimeException('Run the SQLite workflow migration outside an existing transaction so foreign keys can be disabled safely.');
        }

        $foreignKeysEnabled = (bool) $connection->scalar('PRAGMA foreign_keys');
        Schema::disableForeignKeyConstraints();

        try {
            $connection->transaction(function () use ($types, $connection) {
                Schema::table('vendor_reports', function (Blueprint $table) use ($types) {
                    $table->enum('workflow_type', $types)->change();
                    // SQLite introspection omits CHECKs, so restate the other enum on rebuild.
                    $table->enum('status', ['DRAFT', 'SUBMITTED', 'IN_APPROVAL', 'APPROVED', 'REJECTED', 'CANCELED'])
                        ->default('DRAFT')->change();
                });

                if ($connection->select('PRAGMA foreign_key_check')) {
                    throw new RuntimeException('Foreign key violations detected after rebuilding vendor_reports.');
                }
            });
        } finally {
            if ($foreignKeysEnabled) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }
};
