<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            if (! Schema::hasColumn('unidades', 'cnpj')) {
                $table->string('cnpj', 20)->nullable()->after('unidade');
            }

            if (! Schema::hasColumn('unidades', 'endereco')) {
                $table->string('endereco', 255)->nullable()->after('cnpj');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            if (Schema::hasColumn('unidades', 'endereco')) {
                $table->dropColumn('endereco');
            }

            if (Schema::hasColumn('unidades', 'cnpj')) {
                $table->dropColumn('cnpj');
            }
        });
    }
};

