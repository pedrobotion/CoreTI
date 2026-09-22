<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cmdb_services') || ! Schema::hasColumn('cmdb_servers', 'service_id')) {
            return;
        }

        DB::table('cmdb_services')->orderBy('id')->get(['id', 'name'])->each(function (object $service): void {
            DB::table('cmdb_servers')
                ->whereNull('service_id')
                ->where(function ($query) use ($service): void {
                    $query->where('service_type', $service->name)
                        ->orWhere('service_description', $service->name);
                })
                ->update(['service_id' => $service->id]);
        });
    }

    public function down(): void
    {
        // Links are intentionally preserved during rollback to avoid losing manual assignments.
    }
};
