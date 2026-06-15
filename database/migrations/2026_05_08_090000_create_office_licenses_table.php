<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_licenses', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('departamento_unidade');
            $table->string('unicoop_office')->nullable();
            $table->string('area_office')->nullable();
            $table->boolean('office_apps')->default(false);
            $table->boolean('office_business')->default(false);
            $table->boolean('powerbi_pro')->default(false);
            $table->boolean('powerbi_premium')->default(false);
            $table->boolean('visio_plan')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('departamento_unidade');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_licenses');
    }
};

