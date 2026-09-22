<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cmdb_connectors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('connector_type', 60)->index();
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->text('token_encrypted')->nullable();
            $table->string('status', 20)->default('inactive')->index();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('last_sync_at')->nullable()->index();
            $table->string('last_sync_status', 30)->nullable()->index();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cmdb_sync_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connector_id')->nullable()->constrained('cmdb_connectors')->nullOnDelete();
            $table->string('name');
            $table->string('schedule_type', 20)->default('manual')->index();
            $table->unsignedInteger('frequency_minutes')->nullable();
            $table->time('run_at')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('last_run_at')->nullable()->index();
            $table->string('last_status', 30)->nullable()->index();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cmdb_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connector_id')->nullable()->constrained('cmdb_connectors')->nullOnDelete();
            $table->foreignId('sync_job_id')->nullable()->constrained('cmdb_sync_jobs')->nullOnDelete();
            $table->string('status', 20)->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('assets_found')->default(0);
            $table->unsignedInteger('assets_created')->default(0);
            $table->unsignedInteger('assets_updated')->default(0);
            $table->unsignedInteger('assets_unchanged')->default(0);
            $table->unsignedInteger('assets_failed')->default(0);
            $table->string('message')->nullable();
            $table->text('error_message')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_sync_logs');
        Schema::dropIfExists('cmdb_sync_jobs');
        Schema::dropIfExists('cmdb_connectors');
    }
};
