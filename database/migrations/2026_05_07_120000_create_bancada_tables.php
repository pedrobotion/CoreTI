<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancada_equipments', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_equipamento', 100);
            $table->string('plaqueta', 100)->unique();
            $table->string('unidade_setor', 255);
            $table->date('data_chegada');
            $table->date('data_saida')->nullable();
            $table->string('status', 50)->index();
            $table->text('observacao')->nullable();
            $table->string('tic', 40)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('bancada_equipment_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bancada_equipment_id')->constrained('bancada_equipments')->cascadeOnDelete();
            $table->string('status', 50);
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->index(['bancada_equipment_id', 'start_time'], 'bq_hist_equipment_start_idx');
        });

        Schema::create('bancada_backup_equipments', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_equipamento', 100);
            $table->string('plaqueta', 100)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancada_backup_equipments');
        Schema::dropIfExists('bancada_equipment_status_histories');
        Schema::dropIfExists('bancada_equipments');
    }
};
