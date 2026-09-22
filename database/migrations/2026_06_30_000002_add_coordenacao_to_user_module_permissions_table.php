<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_module_permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_module_permissions', 'coordenacao')) {
                $table->boolean('coordenacao')->default(false)->after('cmdb');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_module_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('user_module_permissions', 'coordenacao')) {
                $table->dropColumn('coordenacao');
            }
        });
    }
};
