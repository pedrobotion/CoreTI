<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circuitos_unidades', function (Blueprint $table): void {
            if (! Schema::hasColumn('circuitos_unidades', 'contato_whatsapp')) {
                $table->boolean('contato_whatsapp')->default(false)->after('contato');
            }
        });
    }

    public function down(): void
    {
        Schema::table('circuitos_unidades', function (Blueprint $table): void {
            if (Schema::hasColumn('circuitos_unidades', 'contato_whatsapp')) {
                $table->dropColumn('contato_whatsapp');
            }
        });
    }
};

