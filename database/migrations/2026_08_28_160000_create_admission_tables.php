<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estrutura do módulo de Admissão (Fase 1).
 *
 * Os vínculos organizacionais são gravados como *_source_id (string), no mesmo
 * padrão das tabelas de snapshot da organização, porque o dado nasce no
 * Protheus/intranet e o caso pode ser aberto antes de o snapshot ter
 * sincronizado o colaborador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_access_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();

            // Critérios de aplicação. Nulo = "qualquer".
            $table->string('cargo_source_id')->nullable();
            $table->string('area_source_id')->nullable();
            $table->string('departamento_source_id')->nullable();
            $table->string('unidade_source_id')->nullable();

            // Maior prioridade vence quando mais de um perfil casa.
            $table->unsignedSmallInteger('prioridade')->default(0);
            $table->boolean('cumulativo')->default(false);
            $table->boolean('ativo')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ativo', 'prioridade']);
            $table->index(['cargo_source_id', 'area_source_id']);
        });

        Schema::create('admission_profile_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_access_profile_id')->constrained()->cascadeOnDelete();
            $table->string('task_type', 64);
            $table->boolean('obrigatorio')->default(true);
            $table->unsignedSmallInteger('sla_dias_antes')->nullable();
            $table->text('observacao')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['admission_access_profile_id', 'task_type'], 'admission_profile_items_unique');
        });

        Schema::create('admission_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 32)->unique();

            $table->string('tipo', 32)->default('admissao');
            $table->string('status', 32)->default('rascunho');

            // Vínculo com o snapshot de RH, quando o colaborador já existe lá.
            $table->foreignId('hr_collaborator_id')->nullable()->constrained('hr_collaborators')->nullOnDelete();

            $table->string('nome');
            $table->string('matricula', 32)->nullable();
            $table->string('cpf', 14)->nullable();
            $table->string('email_pessoal')->nullable();
            $table->string('telefone', 32)->nullable();

            $table->string('cargo_source_id')->nullable();
            $table->string('cargo_nome')->nullable();
            $table->string('area_source_id')->nullable();
            $table->string('area_nome')->nullable();
            $table->string('departamento_source_id')->nullable();
            $table->string('unidade_source_id')->nullable();
            $table->string('unidade_nome')->nullable();
            $table->string('centro_custo', 32)->nullable();

            $table->string('gestor_source_id')->nullable();
            $table->string('gestor_nome')->nullable();
            $table->string('gestor_email')->nullable();

            $table->date('data_admissao')->nullable();
            $table->date('data_inicio')->nullable();

            // Dados que só o RH possui.
            $table->string('tamanho_uniforme', 16)->nullable();
            $table->string('tamanho_calcado', 16)->nullable();
            $table->json('epi')->nullable();

            // Quando informado, o pacote de acessos é espelhado deste colaborador.
            $table->string('substitui_matricula', 32)->nullable();
            $table->string('substitui_nome')->nullable();

            $table->text('observacoes')->nullable();
            $table->json('dados_extras')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('concluded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'data_inicio']);
            $table->index('matricula');
        });

        Schema::create('admission_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_case_id')->constrained()->cascadeOnDelete();

            $table->string('task_type', 64);
            $table->string('area', 32);
            $table->string('status', 32)->default('pendente');

            $table->date('due_at')->nullable();
            $table->boolean('obrigatorio')->default(true);
            $table->text('observacao')->nullable();
            $table->json('config')->nullable();

            // Preenchido pelo dispatcher; identifica o chamado que carrega a tarefa.
            $table->string('jira_issue_key', 64)->nullable();
            $table->string('jira_issue_url')->nullable();
            $table->timestamp('dispatched_at')->nullable();

            // Resultado da execução: e-mail criado, nº de patrimônio, licença, etc.
            $table->json('resultado')->nullable();
            $table->foreignId('concluded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('concluded_at')->nullable();
            $table->text('bloqueio_motivo')->nullable();

            $table->timestamps();

            // Uma tarefa de cada tipo por caso — base da idempotência da geração.
            $table->unique(['admission_case_id', 'task_type'], 'admission_tasks_unique');
            $table->index(['area', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('admission_case_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->text('descricao')->nullable();
            $table->json('contexto')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['admission_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_case_events');
        Schema::dropIfExists('admission_tasks');
        Schema::dropIfExists('admission_cases');
        Schema::dropIfExists('admission_profile_items');
        Schema::dropIfExists('admission_access_profiles');
    }
};
