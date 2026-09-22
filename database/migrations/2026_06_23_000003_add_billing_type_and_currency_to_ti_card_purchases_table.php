<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ti_card_purchases', function (Blueprint $table): void {
            $table->string('billing_type', 20)->nullable()->after('purchase_value');
            $table->string('currency', 3)->nullable()->after('billing_type');
        });
    }

    public function down(): void
    {
        Schema::table('ti_card_purchases', function (Blueprint $table): void {
            $table->dropColumn(['billing_type', 'currency']);
        });
    }
};
