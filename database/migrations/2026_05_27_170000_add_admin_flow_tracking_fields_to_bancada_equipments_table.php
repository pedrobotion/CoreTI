<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->string('peca_fluxo_status', 40)->nullable()->after('service_tag')->index();
            $table->timestamp('peca_admin_realizado_em')->nullable()->after('peca_fluxo_status');
            $table->timestamp('peca_recebida_confirmada_em')->nullable()->after('peca_admin_realizado_em');

            $table->string('terceiros_fluxo_status', 40)->nullable()->after('terceiros_resultado')->index();
            $table->timestamp('terceiros_enviado_em')->nullable()->after('terceiros_fluxo_status');
            $table->decimal('terceiros_valor_reparo', 12, 2)->nullable()->after('terceiros_enviado_em');
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->dropColumn([
                'peca_fluxo_status',
                'peca_admin_realizado_em',
                'peca_recebida_confirmada_em',
                'terceiros_fluxo_status',
                'terceiros_enviado_em',
                'terceiros_valor_reparo',
            ]);
        });
    }
};
