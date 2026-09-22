<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cmdb_racks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->string('location')->nullable();
            $table->string('room')->nullable();
            $table->string('floor')->nullable();
            $table->unsignedInteger('capacity_u')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['name']);
        });

        Schema::create('cmdb_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_type', 40)->index();
            $table->string('name');
            $table->string('hostname')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable()->index();
            $table->string('model')->nullable()->index();
            $table->string('serial_number')->nullable()->index();
            $table->string('patrimony_number')->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->string('criticality', 20)->default('medium')->index();
            $table->string('environment', 20)->default('production')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->string('location')->nullable()->index();
            $table->foreignId('rack_id')->nullable()->constrained('cmdb_racks')->nullOnDelete();
            $table->string('main_ip')->nullable()->index();
            $table->string('operating_system')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_type', 'status']);
            $table->index(['criticality', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cmdb_assets');
        Schema::dropIfExists('cmdb_racks');
    }
};
