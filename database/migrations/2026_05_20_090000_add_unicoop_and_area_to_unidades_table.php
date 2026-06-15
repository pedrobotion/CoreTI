<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table): void {
            $table->string('unicoop', 10)->nullable()->after('cnpj');
            $table->string('area', 10)->nullable()->after('unicoop');
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table): void {
            $table->dropColumn(['unicoop', 'area']);
        });
    }
};

