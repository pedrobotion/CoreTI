<?php

namespace Tests\Feature\Admissao;

use App\Models\AdmissionAccessProfile;
use App\Models\AdmissionCase;
use App\Models\AdmissionTask;
use App\Models\User;
use App\Services\Admissao\AdmissionCaseService;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AdmissionCase::esquecerEstrutura();
        LegacyModulePermissions::esquecerCache();

        // Jira desligado: o dispatcher roda em modo simulado.
        config(['services.jira.enabled' => false]);

        $padrao = AdmissionAccessProfile::create([
            'nome' => 'Padrão',
            'prioridade' => 0,
            'ativo' => true,
        ]);
        $padrao->items()->createMany([
            ['task_type' => 'email_google', 'obrigatorio' => true],
            ['task_type' => 'acesso_ad', 'obrigatorio' => true],
            ['task_type' => 'cracha', 'obrigatorio' => true],
        ]);

        $uniforme = AdmissionAccessProfile::create([
            'nome' => 'Uniforme',
            'prioridade' => 10,
            'cumulativo' => true,
            'ativo' => true,
        ]);
        $uniforme->items()->create(['task_type' => 'uniforme', 'obrigatorio' => true]);
    }

    private function rh(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['admissao' => ['request']]);

        return $user->fresh();
    }

    private function ti(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['admissao' => ['execute']]);

        return $user->fresh();
    }

    private function dadosValidos(array $override = []): array
    {
        return array_merge([
            'tipo' => 'admissao',
            'nome' => 'Maria da Silva',
            'matricula' => '99887',
            'cargo_nome' => 'Auxiliar Administrativo',
            'unidade_source_id' => '10',
            'unidade_nome' => 'Sede',
            'data_inicio' => now()->addDays(15)->toDateString(),
            'tamanho_uniforme' => 'M',
        ], $override);
    }

    public function test_rh_cria_caso_e_tarefas_sao_geradas_pelos_perfis(): void
    {
        $rh = $this->rh();

        $this->actingAs($rh)
            ->post(route('admissao.cases.store'), $this->dadosValidos())
            ->assertRedirect();

        $case = AdmissionCase::firstOrFail();

        $this->assertSame(AdmissionCase::STATUS_RASCUNHO, $case->status);
        $this->assertMatchesRegularExpression('/^ADM-\d{4}-\d{4}$/', $case->codigo);

        // Perfil padrão + perfil cumulativo de uniforme.
        $this->assertEqualsCanonicalizing(
            ['email_google', 'acesso_ad', 'cracha', 'uniforme'],
            $case->tasks->pluck('task_type')->all()
        );

        // A tarefa de uniforme pertence a Patrimônio, não à TI.
        $this->assertSame('patrimonio', $case->tasks->firstWhere('task_type', 'uniforme')->area);
    }

    public function test_prazo_da_tarefa_precede_a_data_de_inicio(): void
    {
        $rh = $this->rh();
        $inicio = now()->addDays(30)->startOfDay();

        $this->actingAs($rh)->post(route('admissao.cases.store'), $this->dadosValidos([
            'data_inicio' => $inicio->toDateString(),
        ]));

        $task = AdmissionCase::firstOrFail()->tasks->firstWhere('task_type', 'email_google');

        $this->assertTrue($task->due_at->lessThan($inicio));
    }

    public function test_envio_abre_um_chamado_por_area(): void
    {
        $rh = $this->rh();
        $this->actingAs($rh)->post(route('admissao.cases.store'), $this->dadosValidos());

        $case = AdmissionCase::firstOrFail();

        $this->actingAs($rh)
            ->post(route('admissao.cases.submit', $case))
            ->assertRedirect(route('admissao.cases.show', $case));

        $case->refresh()->load('tasks');

        $this->assertSame(AdmissionCase::STATUS_PROVISIONANDO, $case->status);
        $this->assertNotNull($case->submitted_at);

        // Todas as tarefas da mesma área compartilham o chamado.
        $porArea = $case->tasks->groupBy('area')->map(
            fn ($tasks) => $tasks->pluck('jira_issue_key')->unique()->values()
        );

        foreach ($porArea as $area => $chaves) {
            $this->assertCount(1, $chaves, "área {$area} deveria ter um único chamado");
            $this->assertNotNull($chaves->first());
        }

        $this->assertGreaterThan(1, $porArea->count(), 'esperava chamados para mais de uma área');
    }

    public function test_envio_sem_data_de_inicio_e_recusado(): void
    {
        $rh = $this->rh();
        $this->actingAs($rh)->post(route('admissao.cases.store'), $this->dadosValidos(['data_inicio' => null]));

        $case = AdmissionCase::firstOrFail();

        $this->actingAs($rh)
            ->post(route('admissao.cases.submit', $case))
            ->assertSessionHasErrors('data_inicio');

        $this->assertSame(AdmissionCase::STATUS_RASCUNHO, $case->fresh()->status);
    }

    public function test_rh_nao_executa_tarefa_e_ti_executa(): void
    {
        $rh = $this->rh();
        $this->actingAs($rh)->post(route('admissao.cases.store'), $this->dadosValidos());

        $case = AdmissionCase::firstOrFail();
        $this->actingAs($rh)->post(route('admissao.cases.submit', $case));

        $task = $case->fresh()->tasks->firstWhere('task_type', 'email_google');

        $this->actingAs($rh)
            ->post(route('admissao.tasks.complete', $task))
            ->assertForbidden();

        $this->actingAs($this->ti())
            ->post(route('admissao.tasks.complete', $task), [
                'resultado' => ['registro' => 'maria.silva@cocari.com.br'],
            ])
            ->assertRedirect();

        $task->refresh();

        $this->assertSame(AdmissionTask::STATUS_CONCLUIDO, $task->status);
        $this->assertSame('maria.silva@cocari.com.br', $task->resultado['registro']);
    }

    public function test_caso_fica_pronto_quando_todas_as_tarefas_terminam(): void
    {
        $rh = $this->rh();
        $ti = $this->ti();

        $this->actingAs($rh)->post(route('admissao.cases.store'), $this->dadosValidos());
        $case = AdmissionCase::firstOrFail();
        $this->actingAs($rh)->post(route('admissao.cases.submit', $case));

        $service = app(AdmissionCaseService::class);
        foreach ($case->fresh()->tasks as $task) {
            $service->completeTask($task, $ti);
        }

        $this->assertSame(AdmissionCase::STATUS_PRONTO, $case->fresh()->status);
    }

    public function test_recalculo_e_idempotente_e_preserva_tarefa_concluida(): void
    {
        $rh = $this->rh();
        $ti = $this->ti();

        $this->actingAs($rh)->post(route('admissao.cases.store'), $this->dadosValidos());
        $case = AdmissionCase::firstOrFail();

        $task = $case->tasks->firstWhere('task_type', 'email_google');
        app(AdmissionCaseService::class)->completeTask($task, $ti, ['registro' => 'x@y.com']);

        $antes = $case->fresh()->tasks->count();

        // Editar o caso dispara novo sync das tarefas.
        $this->actingAs($rh)->put(route('admissao.cases.update', $case), $this->dadosValidos([
            'observacoes' => 'ajuste',
        ]));

        $case->refresh()->load('tasks');

        $this->assertCount($antes, $case->tasks);
        $this->assertSame(
            AdmissionTask::STATUS_CONCLUIDO,
            $case->tasks->firstWhere('task_type', 'email_google')->status
        );
    }

    public function test_cancelamento_encerra_pendencias_e_registra_o_que_reverter(): void
    {
        $rh = $this->rh();
        $ti = $this->ti();

        $this->actingAs($rh)->post(route('admissao.cases.store'), $this->dadosValidos());
        $case = AdmissionCase::firstOrFail();
        $this->actingAs($rh)->post(route('admissao.cases.submit', $case));

        $task = $case->fresh()->tasks->firstWhere('task_type', 'email_google');
        app(AdmissionCaseService::class)->completeTask($task, $ti, ['registro' => 'x@y.com']);

        $this->actingAs($rh)->post(route('admissao.cases.cancel', $case), [
            'cancel_reason' => 'Candidato desistiu.',
        ])->assertRedirect();

        $case->refresh()->load('tasks');

        $this->assertSame(AdmissionCase::STATUS_CANCELADO, $case->status);
        $this->assertCount(0, $case->tasks->reject(fn ($t) => $t->isFinalizado()));

        $evento = $case->events()->where('event', 'caso.cancelado')->firstOrFail();
        $this->assertNotEmpty($evento->contexto['reverter']);
    }

    public function test_caso_sem_perfil_aplicavel_nao_e_enviado(): void
    {
        AdmissionAccessProfile::query()->update(['ativo' => false]);

        $rh = $this->rh();
        $this->actingAs($rh)->post(route('admissao.cases.store'), $this->dadosValidos());

        $case = AdmissionCase::firstOrFail();

        $this->actingAs($rh)
            ->post(route('admissao.cases.submit', $case))
            ->assertSessionHasErrors('perfil');

        $this->assertSame(AdmissionCase::STATUS_RASCUNHO, $case->fresh()->status);
    }
}
