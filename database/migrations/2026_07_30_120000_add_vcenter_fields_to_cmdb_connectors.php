<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cmdb_connectors')) {
            return;
        }

        Schema::table('cmdb_connectors', function (Blueprint $table): void {
            if (! Schema::hasColumn('cmdb_connectors', 'verify_ssl')) {
                $table->boolean('verify_ssl')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('cmdb_connectors', 'timeout_seconds')) {
                $table->unsignedInteger('timeout_seconds')->default(30)->after('verify_ssl');
            }
            if (! Schema::hasColumn('cmdb_connectors', 'sync_interval_minutes')) {
                $table->unsignedInteger('sync_interval_minutes')->nullable()->after('timeout_seconds');
            }
            if (! Schema::hasColumn('cmdb_connectors', 'import_powered_off')) {
                $table->boolean('import_powered_off')->default(true)->after('sync_interval_minutes');
            }
            if (! Schema::hasColumn('cmdb_connectors', 'last_connection_test_at')) {
                $table->timestamp('last_connection_test_at')->nullable()->after('import_powered_off');
            }
            if (! Schema::hasColumn('cmdb_connectors', 'last_connection_test_status')) {
                $table->string('last_connection_test_status', 30)->nullable()->after('last_connection_test_at');
            }
            if (! Schema::hasColumn('cmdb_connectors', 'last_connection_test_message')) {
                $table->text('last_connection_test_message')->nullable()->after('last_connection_test_status');
            }
            if (! Schema::hasColumn('cmdb_connectors', 'last_successful_sync_at')) {
                $table->timestamp('last_successful_sync_at')->nullable()->after('last_connection_test_message');
            }
            if (! Schema::hasColumn('cmdb_connectors', 'last_failed_sync_at')) {
                $table->timestamp('last_failed_sync_at')->nullable()->after('last_successful_sync_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cmdb_connectors')) {
            return;
        }

        Schema::table('cmdb_connectors', function (Blueprint $table): void {
            foreach (['verify_ssl', 'timeout_seconds', 'sync_interval_minutes', 'import_powered_off', 'last_connection_test_at', 'last_connection_test_status', 'last_connection_test_message', 'last_successful_sync_at', 'last_failed_sync_at'] as $column) {
                if (Schema::hasColumn('cmdb_connectors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
