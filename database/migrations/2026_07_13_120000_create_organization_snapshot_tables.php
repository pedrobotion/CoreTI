<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_sync_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 50)->default('intranet')->index();
            $table->string('sync_type', 50)->index();
            $table->string('status', 30)->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('units_found')->default(0);
            $table->unsignedInteger('units_created')->default(0);
            $table->unsignedInteger('units_updated')->default(0);
            $table->unsignedInteger('units_unchanged')->default(0);
            $table->unsignedInteger('units_missing')->default(0);
            $table->unsignedInteger('units_failed')->default(0);
            $table->unsignedInteger('departments_found')->default(0);
            $table->unsignedInteger('departments_created')->default(0);
            $table->unsignedInteger('departments_updated')->default(0);
            $table->unsignedInteger('departments_unchanged')->default(0);
            $table->unsignedInteger('departments_missing')->default(0);
            $table->unsignedInteger('departments_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_units', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 50)->default('intranet');
            $table->string('source_id', 100);
            $table->string('code', 50)->nullable()->index();
            $table->string('name')->index();
            $table->string('type_id', 100)->nullable();
            $table->string('cnpj', 30)->nullable()->index();
            $table->string('state_registration', 100)->nullable();
            $table->string('municipal_registration', 100)->nullable();
            $table->string('address')->nullable();
            $table->string('address_complement')->nullable();
            $table->string('municipality_id', 100)->nullable();
            $table->string('zip_code', 30)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('secondary_phone', 100)->nullable();
            $table->string('fax', 100)->nullable();
            $table->string('direct_extension', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('nfe_email')->nullable();
            $table->string('regional_id', 100)->nullable()->index();
            $table->boolean('active')->default(true)->index();
            $table->string('old_source_id', 100)->nullable();
            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->string('nire', 100)->nullable();
            $table->decimal('latitude', 12, 8)->nullable();
            $table->decimal('longitude', 12, 8)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('source_hash', 64)->nullable()->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamp('source_missing_at')->nullable()->index();
            $table->foreignId('sync_batch_id')->nullable()->index()->constrained('organization_sync_batches')->nullOnDelete();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_id']);
        });

        Schema::create('organization_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 50)->default('intranet');
            $table->string('source_id', 100);
            $table->string('source_unit_id', 100)->nullable()->index();
            $table->foreignId('unit_id')->nullable()->index()->constrained('organization_units')->nullOnDelete();
            $table->string('source_area_id', 100)->nullable()->index();
            $table->string('code', 100)->nullable()->index();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('source_hash', 64)->nullable()->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamp('source_missing_at')->nullable()->index();
            $table->foreignId('sync_batch_id')->nullable()->index()->constrained('organization_sync_batches')->nullOnDelete();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_id']);
        });

        Schema::create('organization_legacy_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 30)->index();
            $table->string('legacy_table', 100)->index();
            $table->string('legacy_id', 100)->index();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('source_id', 100)->nullable()->index();
            $table->string('match_type', 50)->index();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('status', 30)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'legacy_table', 'legacy_id'], 'organization_legacy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_legacy_mappings');
        Schema::dropIfExists('organization_departments');
        Schema::dropIfExists('organization_units');
        Schema::dropIfExists('organization_sync_batches');
    }
};
