<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            $table->string('matricula', 50)->nullable()->after('id');
            $table->index('matricula');
        });
    }

    public function down(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            $table->dropIndex(['matricula']);
            $table->dropColumn('matricula');
        });
    }
};

