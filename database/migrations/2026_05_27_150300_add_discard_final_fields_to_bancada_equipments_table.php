<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->boolean('plaqueta_retirada')->default(false)->after('terceiros_retorno_em');
            $table->timestamp('plaqueta_retirada_at')->nullable()->after('plaqueta_retirada');
            $table->foreignId('plaqueta_retirada_by')->nullable()->after('plaqueta_retirada_at')
                ->constrained('users')->nullOnDelete();

            $table->boolean('baixa_realizada')->default(false)->after('plaqueta_retirada_by');
            $table->timestamp('baixa_realizada_at')->nullable()->after('baixa_realizada');
            $table->foreignId('baixa_realizada_by')->nullable()->after('baixa_realizada_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('baixa_realizada_by');
            $table->dropColumn('baixa_realizada_at');
            $table->dropColumn('baixa_realizada');

            $table->dropConstrainedForeignId('plaqueta_retirada_by');
            $table->dropColumn('plaqueta_retirada_at');
            $table->dropColumn('plaqueta_retirada');
        });
    }
};
