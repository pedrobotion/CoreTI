<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('departamentos') && Schema::hasColumn('departamentos', 'origem')) {
            Schema::table('departamentos', function (Blueprint $table): void {
                $table->dropColumn('origem');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('departamentos') && ! Schema::hasColumn('departamentos', 'origem')) {
            Schema::table('departamentos', function (Blueprint $table): void {
                $table->string('origem', 80)->nullable()->after('ativo');
            });
        }
    }
};

