<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancada_malote_routes', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120);
            $table->string('dia_entrega', 20)->nullable();
            $table->string('dia_carrega', 20)->nullable();
            $table->string('dia_separa', 20)->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('bancada_malote_route_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('route_id')->constrained('bancada_malote_routes')->cascadeOnDelete();
            $table->string('unit_label', 255);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
            $table->index(['route_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancada_malote_route_units');
        Schema::dropIfExists('bancada_malote_routes');
    }
};

