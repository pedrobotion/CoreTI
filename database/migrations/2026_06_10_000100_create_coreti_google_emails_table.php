<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coreti_google_emails')) {
            Schema::create('coreti_google_emails', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('nome')->nullable();
                $table->string('status_google')->nullable();
                $table->string('nome_usuario')->nullable()->index();
                $table->unsignedBigInteger('ad_user_id')->nullable()->index();
                $table->foreign('ad_user_id')->references('id')->on('ad_users')->nullOnDelete();
                $table->string('ad_unidade_setor_original')->nullable();
                $table->string('tipo_local')->nullable();
                $table->string('nome_local')->nullable();
                $table->string('nome_local_normalizado')->nullable()->index();
                $table->string('unicoop', 10)->nullable()->index();
                $table->string('area', 10)->nullable()->index();
                $table->string('centro_custo', 50)->nullable()->index();
                $table->string('centro_custo_nome')->nullable();
                $table->string('mapeamento_status')->default('pendente')->index();
                $table->text('mapeamento_motivo')->nullable();
                $table->timestamp('importado_em')->nullable();
                $table->timestamp('atualizado_rateio_em')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coreti_google_emails');
    }
};
