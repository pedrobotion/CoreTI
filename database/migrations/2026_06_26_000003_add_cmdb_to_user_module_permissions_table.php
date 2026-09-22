<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_module_permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_module_permissions', 'cmdb')) {
                $table->boolean('cmdb')->default(false)->after('network_datacenter');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_module_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('user_module_permissions', 'cmdb')) {
                $table->dropColumn('cmdb');
            }
        });
    }
};
