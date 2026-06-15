<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->increments('id_unidades');
            $table->string('unidade', 255);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
