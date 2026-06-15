<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_malote_routes', function (Blueprint $table): void {
            if (! Schema::hasColumn('bancada_malote_routes', 'observacao')) {
                $table->string('observacao', 500)->nullable()->after('dia_separa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bancada_malote_routes', function (Blueprint $table): void {
            if (Schema::hasColumn('bancada_malote_routes', 'observacao')) {
                $table->dropColumn('observacao');
            }
        });
    }
};
