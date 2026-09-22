<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('office_licenses', 'microsoft_last_sign_in_at')) {
                $table->timestamp('microsoft_last_sign_in_at')->nullable()->index();
            }
            if (! Schema::hasColumn('office_licenses', 'microsoft_last_non_interactive_sign_in_at')) {
                $table->timestamp('microsoft_last_non_interactive_sign_in_at')->nullable();
            }
            if (! Schema::hasColumn('office_licenses', 'microsoft_activity_source')) {
                $table->string('microsoft_activity_source', 40)->nullable();
            }
            if (! Schema::hasColumn('office_licenses', 'microsoft_activity_checked_at')) {
                $table->timestamp('microsoft_activity_checked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_licenses', function (Blueprint $table): void {
            foreach ([
                'microsoft_last_sign_in_at',
                'microsoft_last_non_interactive_sign_in_at',
                'microsoft_activity_source',
                'microsoft_activity_checked_at',
            ] as $column) {
                if (Schema::hasColumn('office_licenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
