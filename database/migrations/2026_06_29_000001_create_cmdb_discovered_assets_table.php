<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cmdb_discovered_assets')) {
            return;
        }

        Schema::create('cmdb_discovered_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connector_id')->nullable()->constrained('cmdb_connectors')->nullOnDelete();
            $table->foreignId('sync_log_id')->nullable()->constrained('cmdb_sync_logs')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('cmdb_assets')->nullOnDelete();
            $table->string('source_type', 40)->index();
            $table->string('source_external_id')->nullable()->index();
            $table->string('hostname')->nullable()->index();
            $table->string('ip_address')->nullable()->index();
            $table->string('mac_address')->nullable()->index();
            $table->string('manufacturer')->nullable()->index();
            $table->string('model')->nullable()->index();
            $table->string('serial_number')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('object_id')->nullable()->index();
            $table->string('uptime')->nullable();
            $table->string('contact')->nullable();
            $table->string('location')->nullable();
            $table->json('raw_data')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('discovered_at')->nullable()->index();
            $table->string('source_hash', 64)->unique();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_discovered_assets');
    }
};
