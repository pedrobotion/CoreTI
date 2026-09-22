<?php

use App\Support\OfficeLicenseReportPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('office_license_assignments')
            ->whereIn('sku_part_number', ['officesubscription', 'o365_business', 'powerapps_dev'])
            ->orderBy('id')
            ->chunkById(500, function ($assignments): void {
                foreach ($assignments as $assignment) {
                    DB::table('office_license_assignments')
                        ->where('id', $assignment->id)
                        ->update([
                            'sku_name' => OfficeLicenseReportPolicy::displayName(
                                $assignment->sku_part_number,
                                $assignment->sku_name
                            ),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Os nomes amigáveis não devem ser revertidos após a sincronização.
    }
};
