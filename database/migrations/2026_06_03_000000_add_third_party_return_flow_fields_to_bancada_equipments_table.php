<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->timestamp('terceiros_retorno_informado_em')->nullable()->after('terceiros_retorno_em');
            $table->foreignId('terceiros_retorno_informado_by')
                ->nullable()
                ->after('terceiros_retorno_informado_em')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('terceiros_retorno_fisico_em')->nullable()->after('terceiros_retorno_informado_by');
            $table->foreignId('terceiros_retorno_fisico_by')
                ->nullable()
                ->after('terceiros_retorno_fisico_em')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('terceiros_retorno_fisico_observacao')->nullable()->after('terceiros_retorno_fisico_by');
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('terceiros_retorno_informado_by');
            $table->dropConstrainedForeignId('terceiros_retorno_fisico_by');
            $table->dropColumn([
                'terceiros_retorno_informado_em',
                'terceiros_retorno_fisico_em',
                'terceiros_retorno_fisico_observacao',
            ]);
        });
    }
};
