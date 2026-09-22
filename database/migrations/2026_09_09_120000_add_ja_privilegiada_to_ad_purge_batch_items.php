<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca histórica de privilégio (adminCount=1 no AD).
 *
 * Não bloqueia a exclusão: o AdminSDHolder grava essa marca quando a conta
 * entra num grupo privilegiado e nunca a remove quando ela sai. Bloquear por
 * ela tornaria eternas as contas de quem um dia foi administrador.
 *
 * Serve para a conta chegar desmarcada na tela e alguém decidir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_purge_batch_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('ad_purge_batch_items', 'ja_privilegiada')) {
                $table->boolean('ja_privilegiada')->default(false)->after('parece_servico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_purge_batch_items', function (Blueprint $table): void {
            if (Schema::hasColumn('ad_purge_batch_items', 'ja_privilegiada')) {
                $table->dropColumn('ja_privilegiada');
            }
        });
    }
};
