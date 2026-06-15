<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancada_third_party_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('cnpj', 20)->nullable()->index();
            $table->string('contact', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['name', 'cnpj'], 'bq_third_party_name_cnpj_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancada_third_party_companies');
    }
};
