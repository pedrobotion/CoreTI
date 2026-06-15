<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->string('origem_tipo', 20)->default('unidade')->after('unidade_setor')->index();

            $table->string('entrada_status', 30)->default('Aguardando Entrada')->after('status')->index();
            $table->string('nota_documento_entrada', 255)->nullable()->after('entrada_status');
            $table->string('nota_numero_entrada', 80)->nullable()->after('nota_documento_entrada');
            $table->decimal('nota_valor_entrada', 12, 2)->nullable()->after('nota_numero_entrada');
            $table->string('nota_anexo_entrada', 255)->nullable()->after('nota_valor_entrada');
            $table->timestamp('entrada_realizada_em')->nullable()->after('nota_anexo_entrada');

            $table->string('peca_nome', 255)->nullable()->after('tic');
            $table->unsignedInteger('peca_quantidade')->nullable()->after('peca_nome');
            $table->string('peca_origem', 40)->nullable()->after('peca_quantidade')->index();
            $table->text('peca_link_compra')->nullable()->after('peca_origem');
            $table->string('service_tag', 100)->nullable()->after('peca_link_compra');

            $table->text('terceiros_problema')->nullable()->after('terceiros_nota_orcamento');
            $table->string('terceiros_empresa', 255)->nullable()->after('terceiros_problema');
            $table->string('terceiros_nota_remessa', 100)->nullable()->after('terceiros_empresa');
            $table->string('terceiros_os_numero', 100)->nullable()->after('terceiros_nota_remessa');
            $table->string('terceiros_orcamento_anexo', 255)->nullable()->after('terceiros_os_numero');
            $table->text('terceiros_observacoes')->nullable()->after('terceiros_orcamento_anexo');
            $table->string('terceiros_resultado', 30)->nullable()->after('terceiros_observacoes')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->dropColumn([
                'origem_tipo',
                'entrada_status',
                'nota_documento_entrada',
                'nota_numero_entrada',
                'nota_valor_entrada',
                'nota_anexo_entrada',
                'entrada_realizada_em',
                'peca_nome',
                'peca_quantidade',
                'peca_origem',
                'peca_link_compra',
                'service_tag',
                'terceiros_problema',
                'terceiros_empresa',
                'terceiros_nota_remessa',
                'terceiros_os_numero',
                'terceiros_orcamento_anexo',
                'terceiros_observacoes',
                'terceiros_resultado',
            ]);
        });
    }
};

