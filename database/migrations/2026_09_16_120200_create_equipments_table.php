<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventário de equipamentos de TI, dentro do ServiceDesk.
 *
 * Fica fora do CMDB de propósito: o CMDB guarda o que sustenta serviço
 * (servidores, VMs, storage), enquanto aqui entra o parque distribuído pelas
 * unidades. E fica fora da Bancada, que trata o ciclo de manutenção — um
 * equipamento pode passar pela Bancada sem deixar de estar inventariado aqui.
 *
 * A unidade aponta para organization_units por source_id, como o resto do
 * CoreTI faz. O gestaoTI guardava 'filial' como texto livre, o que produziu
 * grafias concorrentes para a mesma unidade ("Cristalina I" x "Cristalina",
 * "Campo Alegre de Goiás I" x "... II (Café)"); localizacao continua livre
 * só para o que não é unidade — bancada, empréstimo, remessa em trânsito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20)->index();
            $table->string('tipo', 60)->index();
            $table->string('nome', 200);
            $table->string('patrimonio', 100)->nullable()->unique();
            $table->string('numero_serie', 100)->nullable()->index();
            $table->string('endereco_ip', 45)->nullable()->index();
            $table->string('organization_unit_source_id', 100)->nullable()->index();
            $table->string('departamento', 150)->nullable();
            $table->string('centro_custo_codigo', 20)->nullable()->index();
            $table->string('centro_custo_nome', 255)->nullable();
            $table->string('localizacao', 255)->nullable();
            $table->string('status', 30)->default('Disponível')->index();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'status']);
            $table->index(['scope', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};
