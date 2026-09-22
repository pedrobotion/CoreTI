<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $assignments = DB::table('office_license_assignments')
            ->whereIn('sku_part_number', ['visioclient', 'visio_client'])
            ->get(['id', 'office_license_id', 'sku_part_number']);

        foreach ($assignments as $assignment) {
            DB::table('office_license_assignments')
                ->where('id', $assignment->id)
                ->update([
                    'sku_name' => 'Visio Plan 2',
                    'license_category' => 'visio_plan',
                    'included_in_report' => true,
                    'updated_at' => now(),
                ]);

            DB::table('office_licenses')
                ->where('id', $assignment->office_license_id)
                ->update([
                    'visio_plan' => true,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Não desfaz a flag: ela pode ter sido atualizada por uma nova
        // sincronização do Microsoft Graph após esta correção.
    }
};
