<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jira_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('email', 255);
            $table->enum('tipo_unidade', ['Sede', 'Unidades'])->default('Unidades');
            $table->string('unidade_nome', 255);
            $table->string('centro_custo', 255)->nullable();
            $table->string('unicoop', 80)->nullable();
            $table->string('area', 80)->nullable();
            $table->string('projeto_grupo', 255);
            $table->enum('status', ['Ativo', 'Desativado'])->default('Ativo');
            $table->text('obs')->nullable();
            $table->date('data_inclusao')->nullable();
            $table->date('data_desativacao')->nullable();
            $table->boolean('excluido')->default(false);
            $table->timestamps();

            $table->index(['status', 'excluido'], 'jira_projects_status_excl_idx');
            $table->index(['tipo_unidade', 'excluido'], 'jira_projects_tipo_excl_idx');
            $table->index('projeto_grupo', 'jira_projects_grupo_idx');
            $table->index('email', 'jira_projects_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jira_projects');
    }
};

