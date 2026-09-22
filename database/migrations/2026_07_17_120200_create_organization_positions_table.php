<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 50)->default('intranet');
            $table->string('source_id', 100);
            $table->string('name')->nullable();
            $table->string('rh_code')->nullable();
            $table->string('rh_level_code')->nullable();
            $table->string('category_source_id')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->string('protheus_position_code')->nullable();
            $table->string('system')->nullable();
            $table->string('cbo')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('source_hash', 64)->nullable()->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamp('source_missing_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->unique(['source_system', 'source_id'], 'organization_position_source_unique');
        });

        Schema::table('office_licenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('office_licenses', 'organization_position_id')) {
                $table->unsignedBigInteger('organization_position_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            if (Schema::hasColumn('office_licenses', 'organization_position_id')) {
                $table->dropColumn('organization_position_id');
            }
        });
        Schema::dropIfExists('organization_positions');
    }
};
