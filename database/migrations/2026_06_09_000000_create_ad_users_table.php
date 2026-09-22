<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela ad_users (espelho do Active Directory).
 *
 * A tabela existe em produção desde antes, criada fora do versionamento — sem
 * esta migration o schema não podia ser reconstruído do zero, e a migration
 * de coreti_google_emails falhava ao criar a chave estrangeira para cá.
 *
 * Diferença deliberada em relação à produção: lá o 'id' é int(11); aqui usamos
 * o bigint unsigned padrão do Laravel, para casar com o ad_user_id
 * (unsignedBigInteger) de coreti_google_emails e a FK poder ser criada. Em
 * produção esta migration é ignorada pelo guard hasTable, então nada muda lá.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ad_users')) {
            return;
        }

        Schema::create('ad_users', function (Blueprint $table): void {
            $table->id();
            $table->string('matricula', 100)->nullable();
            $table->string('nome_completo')->nullable();
            $table->string('nome_usuario', 100)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('centro_custo')->nullable();
            $table->string('Unicoop', 100)->nullable();
            $table->string('Área', 100)->nullable();
            $table->string('nome_exibicao')->nullable();
            $table->string('unidade_setor')->nullable();
            $table->string('Cargo', 1000)->nullable();
            $table->boolean('e_ativo')->default(true);
            $table->dateTime('criado_em')->nullable()->index('idx_ad_users_criado_em');
            $table->dateTime('ultima_modificacao_senha')->nullable();
            $table->timestamp('last_sync')->useCurrent();
            $table->string('distinguished_name', 1000)->nullable();
            $table->string('unidade_principal')->nullable()->index('idx_ad_users_unidade_principal');
            $table->integer('total_grupos_ad')->default(0)->index('idx_ad_users_total_grupos_ad');
            $table->longText('ad_groups')->nullable();
            $table->dateTime('data_desativacao')->nullable();
            $table->string('desativado_por')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_users');
    }
};
