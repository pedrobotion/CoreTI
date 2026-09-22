<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca contas removidas do Active Directory.
 *
 * A linha NÃO é apagada de propósito: depois de excluída do AD, a conta some
 * do inventário e o histórico se perderia — quem era, de qual centro de custo,
 * quando foi desativada. Manter o registro marcado preserva a rastreabilidade
 * de quem tinha acesso e quando deixou de ter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('ad_users', 'excluido')) {
                $table->boolean('excluido')->default(false)->after('desativado_por')->index();
            }
            if (! Schema::hasColumn('ad_users', 'excluido_em')) {
                $table->dateTime('excluido_em')->nullable()->after('excluido');
            }
            if (! Schema::hasColumn('ad_users', 'excluido_por')) {
                $table->string('excluido_por')->nullable()->after('excluido_em');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_users', function (Blueprint $table): void {
            foreach (['excluido_por', 'excluido_em', 'excluido'] as $coluna) {
                if (Schema::hasColumn('ad_users', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
