<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regra que define o centro de custo do campo "Escritório" no AD.
 *
 * O Active Directory não tem atributo calculado: nenhum campo se preenche a
 * partir da OU ou de grupo. A regra vive aqui, e um script a aplica.
 *
 * A chave é o caminho de OU porque é assim que a estrutura já está organizada
 * ("Sede / E-Commerce"). Não é a matrícula: o centro de custo pertence ao
 * setor, e usar a pessoa exigiria manter uma linha por colaborador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_office_rules', function (Blueprint $table): void {
            $table->id();

            // Caminho legível da OU, do mais geral ao mais específico.
            $table->string('ou_caminho')->unique();

            // Código de coreti_cost_centers. Nulo = ainda não decidido.
            $table->string('centro_custo_code', 20)->nullable()->index();

            /*
             | Marcada para não ser tocada, com o motivo registrado.
             |
             | Existe para casos como a Superintendência Logística Integrada,
             | que tem centro de custo por setor e está em revisão de
             | nomenclatura. Sem isso, a alternativa seria deixar a regra vazia
             | — e "vazio" não distingue "ninguém decidiu ainda" de "decidimos
             | não mexer".
             */
            $table->boolean('ignorar')->default(false)->index();
            $table->string('motivo_ignorar')->nullable();

            // Sugestão calculada a partir do que o AD tem hoje, para conferência.
            $table->string('sugestao_code', 20)->nullable();
            $table->unsignedInteger('contas_na_ou')->default(0);
            $table->unsignedInteger('contas_divergentes')->default(0);

            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmado_em')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_office_rules');
    }
};
