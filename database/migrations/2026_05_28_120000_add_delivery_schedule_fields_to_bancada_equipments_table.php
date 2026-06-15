<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('bancada_equipments', 'delivery_route_id')) {
                $table->foreignId('delivery_route_id')->nullable()->after('origem_tipo')->constrained('bancada_malote_routes')->nullOnDelete();
            }
            if (! Schema::hasColumn('bancada_equipments', 'sent_to_cd_at')) {
                $table->timestamp('sent_to_cd_at')->nullable()->after('delivery_route_id');
            }
            if (! Schema::hasColumn('bancada_equipments', 'sent_to_cd_by')) {
                $table->foreignId('sent_to_cd_by')->nullable()->after('sent_to_cd_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('bancada_equipments', 'expected_separation_date')) {
                $table->date('expected_separation_date')->nullable()->after('sent_to_cd_by');
            }
            if (! Schema::hasColumn('bancada_equipments', 'expected_loading_date')) {
                $table->date('expected_loading_date')->nullable()->after('expected_separation_date');
            }
            if (! Schema::hasColumn('bancada_equipments', 'expected_delivery_date')) {
                $table->date('expected_delivery_date')->nullable()->after('expected_loading_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bancada_equipments', function (Blueprint $table): void {
            foreach (['delivery_route_id', 'sent_to_cd_by'] as $fk) {
                try {
                    $table->dropConstrainedForeignId($fk);
                } catch (\Throwable $e) {
                    // no-op
                }
            }

            foreach (['sent_to_cd_at', 'expected_separation_date', 'expected_loading_date', 'expected_delivery_date'] as $col) {
                if (Schema::hasColumn('bancada_equipments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
