<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cmdb_servers', function (Blueprint $table): void {
            $table->string('power_state')->nullable()->after('asset_id');
            $table->string('operational_status')->nullable()->after('power_state');
            $table->decimal('used_space_gb', 12, 2)->nullable()->after('storage_total_gb');
            $table->text('service_description')->nullable()->after('used_space_gb');
            $table->string('company_name')->nullable()->after('service_description');
            $table->string('service_type')->nullable()->after('company_name');
            $table->string('backup_policy')->nullable()->after('service_type');
            $table->string('backup_retention')->nullable()->after('backup_policy');
            $table->boolean('web_publication')->nullable()->after('backup_retention');
        });
    }

    public function down(): void
    {
        Schema::table('cmdb_servers', function (Blueprint $table): void {
            $table->dropColumn([
                'power_state',
                'operational_status',
                'used_space_gb',
                'service_description',
                'company_name',
                'service_type',
                'backup_policy',
                'backup_retention',
                'web_publication',
            ]);
        });
    }
};
