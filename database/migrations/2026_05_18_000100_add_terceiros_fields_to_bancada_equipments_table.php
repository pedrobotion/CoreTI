<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->text('terceiros_nota_orcamento')->nullable()->after('backup_data_formatado');
            $table->string('terceiros_orcamento_status', 20)->nullable()->after('terceiros_nota_orcamento');
            $table->timestamp('terceiros_retorno_em')->nullable()->after('terceiros_orcamento_status');
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->dropColumn([
                'terceiros_nota_orcamento',
                'terceiros_orcamento_status',
                'terceiros_retorno_em',
            ]);
        });
    }
};
