<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_desk_emails', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20)->index();
            $table->string('email')->index();
            $table->string('matricula', 20)->index();
            $table->string('colaborador_nome');
            $table->unsignedBigInteger('id_pessoa')->nullable()->index();
            $table->string('centro_custo')->nullable();
            $table->string('unicoop_sede', 50)->nullable();
            $table->string('area_sede', 50)->nullable();
            $table->date('data_inclusao')->nullable();
            $table->date('data_desativacao')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_desk_emails');
    }
};
