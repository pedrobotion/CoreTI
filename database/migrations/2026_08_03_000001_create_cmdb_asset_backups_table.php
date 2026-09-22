<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cmdb_asset_backups')) {
            Schema::create('cmdb_asset_backups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('asset_id')->constrained('cmdb_assets')->cascadeOnDelete();
                $table->foreignId('connector_id')->constrained('cmdb_connectors')->cascadeOnDelete();
                $table->string('platform', 60)->default('veeam')->index();
                $table->string('external_vm_id')->nullable();
                $table->string('external_object_id')->nullable();
                $table->string('job_id')->nullable();
                $table->string('job_name')->nullable();
                $table->boolean('is_in_backup_job')->nullable();
                $table->boolean('is_protected')->nullable();
                $table->string('protection_status', 30)->nullable()->index();
                $table->string('last_session_status', 40)->nullable();
                $table->timestamp('last_session_started_at')->nullable();
                $table->timestamp('last_session_finished_at')->nullable();
                $table->timestamp('last_successful_backup_at')->nullable()->index();
                $table->timestamp('latest_restore_point_at')->nullable();
                $table->unsignedInteger('restore_points_count')->nullable();
                $table->string('repository_name')->nullable();
                $table->unsignedInteger('retention_days')->nullable();
                $table->string('schedule_description')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('last_synced_at')->nullable()->index();
                $table->string('sync_hash', 128)->nullable();
                $table->string('match_method', 40)->nullable();
                $table->decimal('match_confidence', 5, 2)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['connector_id', 'asset_id']);
                $table->index(['connector_id', 'external_vm_id']);
                $table->index(['connector_id', 'job_id']);
            });
        }

        if (Schema::hasTable('cmdb_assets')) {
            Schema::table('cmdb_assets', function (Blueprint $table): void {
                if (! Schema::hasColumn('cmdb_assets', 'backup_exception')) {
                    $table->boolean('backup_exception')->default(false)->after('sync_hash');
                }
                if (! Schema::hasColumn('cmdb_assets', 'backup_exception_reason')) {
                    $table->text('backup_exception_reason')->nullable()->after('backup_exception');
                }
                if (! Schema::hasColumn('cmdb_assets', 'backup_exception_approved_by')) {
                    $table->foreignId('backup_exception_approved_by')->nullable()->after('backup_exception_reason')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('cmdb_assets', 'backup_exception_expires_at')) {
                    $table->timestamp('backup_exception_expires_at')->nullable()->after('backup_exception_approved_by')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cmdb_assets')) {
            Schema::table('cmdb_assets', function (Blueprint $table): void {
                foreach (['backup_exception_approved_by', 'backup_exception_expires_at', 'backup_exception_reason', 'backup_exception'] as $column) {
                    if (Schema::hasColumn('cmdb_assets', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('cmdb_asset_backups');
    }
};
