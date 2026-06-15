<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_desk_emails', function (Blueprint $table): void {
            $table->boolean('centro_custo_manual')->default(false)->after('area_sede');
            $table->timestamp('centro_custo_manual_at')->nullable()->after('centro_custo_manual');
        });
    }

    public function down(): void
    {
        Schema::table('service_desk_emails', function (Blueprint $table): void {
            $table->dropColumn(['centro_custo_manual', 'centro_custo_manual_at']);
        });
    }
};
