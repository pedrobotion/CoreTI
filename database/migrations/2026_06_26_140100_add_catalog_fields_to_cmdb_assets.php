<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cmdb_assets', function (Blueprint $table): void {
            $table->foreignId('manufacturer_id')->nullable()->index()->after('manufacturer');
            $table->foreignId('model_id')->nullable()->index()->after('manufacturer_id');
        });
    }

    public function down(): void
    {
        Schema::table('cmdb_assets', function (Blueprint $table): void {
            $table->dropColumn(['model_id', 'manufacturer_id']);
        });
    }
};
