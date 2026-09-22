<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_sync_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_license_id')->constrained('office_licenses')->cascadeOnDelete();
            $table->foreignId('hr_collaborator_id')->nullable()->constrained('hr_collaborators')->nullOnDelete();
            $table->string('field_name', 80);
            $table->text('current_value')->nullable();
            $table->string('current_label')->nullable();
            $table->text('proposed_value')->nullable();
            $table->string('proposed_label')->nullable();
            $table->string('current_source', 20)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('sync_run_id')->nullable()->index();
            $table->string('proposal_hash', 64);
            $table->timestamps();
            $table->index(['office_license_id', 'field_name', 'status'], 'office_sync_change_lookup');
            $table->unique(['office_license_id', 'field_name', 'proposal_hash', 'status'], 'office_sync_change_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_sync_change_requests');
    }
};
