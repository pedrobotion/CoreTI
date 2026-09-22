<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cmdb_manufacturers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->string('website')->nullable();
            $table->string('support_url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cmdb_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manufacturer_id')->nullable()->index();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->string('asset_type', 50)->nullable()->index();
            $table->string('part_number')->nullable();
            $table->unsignedInteger('rack_units')->nullable();
            $table->date('eol_date')->nullable();
            $table->date('eos_date')->nullable();
            $table->date('support_end_date')->nullable();
            $table->string('manual_url')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedInteger('power_consumption_watts')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_models');
        Schema::dropIfExists('cmdb_manufacturers');
    }
};
