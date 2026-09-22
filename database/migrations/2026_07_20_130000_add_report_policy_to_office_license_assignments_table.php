<?php

use App\Support\OfficeLicenseReportPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_license_assignments', function (Blueprint $table): void {
            $table->boolean('included_in_report')->default(true)->after('license_category')->index();
            $table->string('exclusion_reason', 150)->nullable()->after('included_in_report');
        });

        DB::table('office_license_assignments')->orderBy('id')->chunkById(500, function ($assignments): void {
            foreach ($assignments as $assignment) {
                $excluded = OfficeLicenseReportPolicy::isExcluded(
                    $assignment->sku_part_number,
                    $assignment->sku_name
                );

                DB::table('office_license_assignments')
                    ->where('id', $assignment->id)
                    ->update([
                        'included_in_report' => ! $excluded,
                        'exclusion_reason' => $excluded
                            ? OfficeLicenseReportPolicy::exclusionReason($assignment->sku_part_number, $assignment->sku_name)
                            : null,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_license_assignments', function (Blueprint $table): void {
            $table->dropColumn(['included_in_report', 'exclusion_reason']);
        });
    }
};
