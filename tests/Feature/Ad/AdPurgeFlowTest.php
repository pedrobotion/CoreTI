<?php

namespace Tests\Feature\Ad;

use App\Models\AdPurgeBatch;
use App\Models\AdPurgeBatchItem;
use App\Models\AdUser;
use App\Models\User;
use App\Services\Ad\AdPurgeService;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdPurgeFlowTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste-do-expurgo';

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();

        config([
            'ad.expurgo.token' => self::TOKEN,
            'ad.expurgo.allowed_ips' => [],
            'ad.expurgo.validade_aprovacao_dias' => 15,
            'ad.expurgo.max_por_execucao' => 200,
            'coreti.sync_notifications.enabled' => false,
        ]);
    }

    private function aprovador(): User
    {
        // Ids baixos colidem com MASTER_USER_ID, que ignora permissões e faria
        // o teste passar sem exercer a regra.
        $user = User::factory()->create(['id' => 9001, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['ad' => ['execute']]);

        return $user->fresh();
    }

    private function candidato(string $nome, int $dias = 60, bool $servico = false, bool $jaPrivilegiada = false): array
    {
        return [
            'ja_privilegiada' => $jaPrivilegiada,
            'nome_usuario' => $nome,
            'nome_exibicao' => ucfirst($nome),
            'email' => $nome.'@cocari.com.br',
            'distinguished_name' => "CN={$nome},OU=Desativados,DC=cocari,DC=com,DC=br",
            'desativada_em' => now()->subDays($dias)->format('Y-m-d H:i:s'),
            'dias_desativada' => $dias,
            'parece_servico' => $servico,
        ];
    }

    private function enviarPrevia(array $candidatos, string $referencia = '2026-10')
    {
        return $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->postJson('/api/ad/exclusao/previa', [
                'referencia' => $referencia,
                'dias_criterio' => 30,
                'origem_host' => 'DC01',
                'candidatos' => $candidatos,
            ]);
    }

    public function test_previa_cria_lote_aguardando_aprovacao(): void
    {
        $this->enviarPrevia([
            $this->candidato('joao.silva'),
            $this->candidato('svc.integracao', 90, true),
        ])->assertCreated();

        $lote = AdPurgeBatch::where('referencia', '2026-10')->firstOrFail();

        $this->assertSame(AdPurgeBatch::STATUS_AGUARDANDO, $lote->status);
        $this->assertSame(2, $lote->total_candidatos);
        $this->assertSame(2, $lote->itens()->count());
    }

    public function test_sem_token_a_api_nao_responde(): void
    {
        $this->postJson('/api/ad/exclusao/previa', [
            'dias_criterio' => 30,
            'candidatos' => [],
        ])->assertUnauthorized();

        $this->getJson('/api/ad/exclusao/aprovado')->assertUnauthorized();
    }

    public function test_sem_token_configurado_o_servico_falha_fechado(): void
    {
        config(['ad.expurgo.token' => '']);

        $this->withHeader('X-Coreti-Ad-Token', 'qualquer-coisa')
            ->getJson('/api/ad/exclusao/aprovado')
            ->assertStatus(503);
    }

    public function test_nada_e_liberado_antes_da_aprovacao(): void
    {
        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();

        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->getJson('/api/ad/exclusao/aprovado')
            ->assertNoContent();
    }

    public function test_aprovacao_conta_a_conta_libera_somente_as_marcadas(): void
    {
        $this->enviarPrevia([
            $this->candidato('joao.silva'),
            $this->candidato('maria.souza'),
            $this->candidato('svc.integracao', 90, true),
        ])->assertCreated();

        $lote = AdPurgeBatch::firstOrFail();
        $marcados = $lote->itens()->whereIn('nome_usuario', ['joao.silva', 'maria.souza'])->pluck('id')->all();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $marcados])
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(AdPurgeBatch::STATUS_APROVADO, $lote->status);
        $this->assertSame(2, $lote->total_aprovados);

        $resposta = $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->getJson('/api/ad/exclusao/aprovado')
            ->assertOk();

        $contas = collect($resposta->json('contas'))->pluck('nome_usuario')->all();

        $this->assertEqualsCanonicalizing(['joao.silva', 'maria.souza'], $contas);
        $this->assertNotContains('svc.integracao', $contas);
    }

    public function test_quem_nao_tem_ad_execute_nao_aprova(): void
    {
        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $semPermissao = User::factory()->create(['id' => 9002, 'role' => 'user', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($semPermissao, ['ad' => ['view']]);

        $this->actingAs($semPermissao->fresh())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => [$lote->itens()->first()->id]])
            ->assertForbidden();

        $this->assertSame(AdPurgeBatch::STATUS_AGUARDANDO, $lote->fresh()->status);
    }

    public function test_id_de_outro_lote_nao_e_aprovado(): void
    {
        $this->enviarPrevia([$this->candidato('joao.silva')], '2026-10')->assertCreated();
        $this->enviarPrevia([$this->candidato('outro.usuario')], '2026-11')->assertCreated();

        $outubro = AdPurgeBatch::where('referencia', '2026-10')->firstOrFail();
        $novembro = AdPurgeBatch::where('referencia', '2026-11')->firstOrFail();
        $itemDeNovembro = $novembro->itens()->first();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $outubro), ['itens' => [$itemDeNovembro->id]])
            ->assertRedirect();

        // O item de novembro continua pendente; nada vazou entre os lotes.
        $this->assertSame(AdPurgeBatchItem::STATUS_PENDENTE, $itemDeNovembro->fresh()->status);
        $this->assertSame(0, $outubro->fresh()->total_aprovados);
    }

    public function test_resultado_marca_conta_no_inventario_sem_apagar_a_linha(): void
    {
        AdUser::create([
            'nome_usuario' => 'joao.silva',
            'nome_completo' => 'João da Silva',
            'Cargo' => 'Analista',
            'centro_custo' => '1234',
            'e_ativo' => true,
            'excluido' => false,
        ]);

        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $lote->itens()->pluck('id')->all()]);

        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->postJson('/api/ad/exclusao/resultado', [
                'referencia' => '2026-10',
                'resultados' => [[
                    'nome_usuario' => 'joao.silva',
                    'situacao' => 'excluido',
                    'excluido_em' => now()->format('Y-m-d H:i:s'),
                    'excluido_por' => 'COCARICOMBR\\supervisor',
                ]],
            ])->assertOk();

        $conta = AdUser::where('nome_usuario', 'joao.silva')->first();

        $this->assertNotNull($conta, 'A linha não pode ser apagada: o histórico se perderia.');
        $this->assertTrue($conta->excluido);
        $this->assertFalse($conta->e_ativo);
        $this->assertSame('Analista', $conta->Cargo);
        $this->assertSame('1234', $conta->centro_custo);

        $this->assertSame(AdPurgeBatch::STATUS_EXECUTADO, $lote->fresh()->status);
    }

    public function test_data_vinda_do_ad_e_convertida_do_fuso_local_para_utc(): void
    {
        // O CoreTI roda em UTC e o servidor do AD manda hora local (UTC-3).
        // Sem o fuso no texto, a auditoria registraria 3 horas a menos.
        AdUser::create(['nome_usuario' => 'joao.silva', 'e_ativo' => true, 'excluido' => false]);

        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $lote->itens()->pluck('id')->all()]);

        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->postJson('/api/ad/exclusao/resultado', [
                'referencia' => '2026-10',
                'resultados' => [[
                    'nome_usuario' => 'joao.silva',
                    'situacao' => 'excluido',
                    'excluido_em' => '2026-09-09T10:11:13.1234567-03:00',
                ]],
            ])->assertOk();

        $conta = AdUser::where('nome_usuario', 'joao.silva')->first();

        $this->assertSame('2026-09-09 13:11:13', $conta->excluido_em->utc()->format('Y-m-d H:i:s'));
    }

    public function test_conta_reativada_e_reportada_como_pulada_e_nao_marca_inventario(): void
    {
        AdUser::create([
            'nome_usuario' => 'voltou.empresa',
            'nome_completo' => 'Voltou',
            'e_ativo' => true,
            'excluido' => false,
        ]);

        $this->enviarPrevia([$this->candidato('voltou.empresa')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $lote->itens()->pluck('id')->all()]);

        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->postJson('/api/ad/exclusao/resultado', [
                'referencia' => '2026-10',
                'resultados' => [[
                    'nome_usuario' => 'voltou.empresa',
                    'situacao' => 'pulado',
                    'detalhe' => 'conta reativada no AD',
                ]],
            ])->assertOk();

        $conta = AdUser::where('nome_usuario', 'voltou.empresa')->first();

        $this->assertFalse($conta->excluido);
        $this->assertTrue($conta->e_ativo);
    }

    public function test_aprovacao_vencida_nao_libera_execucao(): void
    {
        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $lote->itens()->pluck('id')->all()]);

        // A lista foi conferida numa data; executá-la meses depois apagaria
        // gente com base em informação vencida.
        $lote->update(['expira_em' => now()->subDay()]);

        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->getJson('/api/ad/exclusao/aprovado')
            ->assertNoContent();

        $this->assertSame(AdPurgeBatch::STATUS_EXPIRADO, $lote->fresh()->status);
    }

    public function test_resultado_em_lote_rejeitado_e_recusado(): void
    {
        AdUser::create(['nome_usuario' => 'joao.silva', 'e_ativo' => true, 'excluido' => false]);

        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.rejeitar', $lote), ['motivo' => 'Conferir com o RH.']);

        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->postJson('/api/ad/exclusao/resultado', [
                'referencia' => '2026-10',
                'resultados' => [[
                    'nome_usuario' => 'joao.silva',
                    'situacao' => 'excluido',
                ]],
            ])->assertStatus(409);

        $this->assertFalse(AdUser::where('nome_usuario', 'joao.silva')->first()->excluido);
    }

    public function test_previa_nao_sobrescreve_lote_ja_decidido(): void
    {
        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $lote->itens()->pluck('id')->all()]);

        $this->enviarPrevia([$this->candidato('outra.pessoa')])->assertStatus(409);

        $lote->refresh();
        $this->assertSame(AdPurgeBatch::STATUS_APROVADO, $lote->status);
        $this->assertSame('joao.silva', $lote->itens()->first()->nome_usuario);
    }

    public function test_lote_grande_e_entregue_em_partes(): void
    {
        config(['ad.expurgo.max_por_execucao' => 2]);

        $candidatos = [];
        foreach (range(1, 5) as $i) {
            $candidatos[] = $this->candidato("usuario{$i}", 100 - $i);
        }

        $this->enviarPrevia($candidatos)->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $lote->itens()->pluck('id')->all()]);

        // Apagar milhares de contas de uma vez gera onda de replicação entre
        // os controladores; o CoreTI entrega em partes.
        $resposta = $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->getJson('/api/ad/exclusao/aprovado')->assertOk();

        $this->assertCount(2, $resposta->json('contas'));
        $this->assertSame(5, $resposta->json('total_aprovados'));
        $this->assertSame(AdPurgeBatch::STATUS_APROVADO, $lote->fresh()->status);
    }

    public function test_comando_expira_lote_vencido(): void
    {
        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $lote->itens()->pluck('id')->all()]);

        $lote->update(['expira_em' => now()->subDays(2)]);

        $this->artisan('ad:expirar-lotes')->assertSuccessful();

        $this->assertSame(AdPurgeBatch::STATUS_EXPIRADO, $lote->fresh()->status);
    }

    public function test_marca_historica_de_privilegio_nao_bloqueia_mas_pede_conferencia(): void
    {
        // adminCount=1 é resíduo do AdminSDHolder: a marca não é removida
        // quando a conta sai do grupo. Bloquear por ela tornaria eternas as
        // contas de quem um dia foi administrador.
        $this->enviarPrevia([
            $this->candidato('ex.admin', 400, false, true),
            $this->candidato('comum.usuario'),
        ])->assertCreated();

        $lote = AdPurgeBatch::firstOrFail();

        $exAdmin = $lote->itens()->where('nome_usuario', 'ex.admin')->firstOrFail();
        $comum = $lote->itens()->where('nome_usuario', 'comum.usuario')->firstOrFail();

        // Entra na lista: não é bloqueio.
        $this->assertSame(2, $lote->total_candidatos);
        $this->assertTrue($exAdmin->ja_privilegiada);

        // Mas chega desmarcada, para alguém decidir.
        $this->assertTrue($exAdmin->pedeConferencia());
        $this->assertFalse($comum->pedeConferencia());

        // E, uma vez aprovada explicitamente, é liberada normalmente.
        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => [$exAdmin->id]]);

        $contas = collect(
            $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
                ->getJson('/api/ad/exclusao/aprovado')->json('contas')
        )->pluck('nome_usuario')->all();

        $this->assertSame(['ex.admin'], $contas);
    }

    public function test_rodada_extra_no_mesmo_mes_nao_apaga_o_lote_anterior(): void
    {
        // Um lote decidido travava o mês inteiro: as contas não aprovadas só
        // reapareceriam na virada. Uma rodada extra resolve sem reescrever o
        // histórico do que já foi decidido.
        $this->enviarPrevia([
            $this->candidato('joao.silva'),
            $this->candidato('maria.souza'),
        ], '2026-09')->assertCreated();

        $setembro = AdPurgeBatch::where('referencia', '2026-09')->firstOrFail();
        $joao = $setembro->itens()->where('nome_usuario', 'joao.silva')->firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $setembro), ['itens' => [$joao->id]]);

        // Reenviar a mesma competência continua recusado.
        $this->enviarPrevia([$this->candidato('maria.souza')], '2026-09')->assertStatus(409);

        // Com sufixo, cria um lote novo.
        $this->enviarPrevia([$this->candidato('maria.souza')], '2026-09-2')->assertCreated();

        $extra = AdPurgeBatch::where('referencia', '2026-09-2')->firstOrFail();

        $this->assertSame(AdPurgeBatch::STATUS_AGUARDANDO, $extra->status);
        $this->assertSame('maria.souza', $extra->itens()->first()->nome_usuario);

        // E o lote de setembro segue intacto, com a decisão registrada.
        $setembro->refresh();
        $this->assertSame(AdPurgeBatch::STATUS_APROVADO, $setembro->status);
        $this->assertSame(1, $setembro->total_aprovados);
        $this->assertSame(2, $setembro->itens()->count());
    }

    public function test_competencia_malformada_e_recusada(): void
    {
        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->postJson('/api/ad/exclusao/previa', [
                'referencia' => 'setembro; DROP TABLE',
                'dias_criterio' => 30,
                'candidatos' => [],
            ])->assertStatus(422);
    }

    public function test_krbtgt_de_rodc_nunca_entra_no_lote(): void
    {
        /*
         | krbtgt_<RODC> é a conta krbtgt de um controlador somente leitura.
         | Tem RID normal e nenhum grupo privilegiado, então nenhum outro
         | critério a pegaria — e apagá-la derruba a autenticação Kerberos do
         | site atendido por aquele RODC.
         */
        $this->enviarPrevia([
            $this->candidato('krbtgt_31402'),
            $this->candidato('KRBTGT_99999'),
            $this->candidato('MSOL_a1b2c3'),
            $this->candidato('Administrador'),
            $this->candidato('joao.silva'),
        ])->assertCreated();

        $lote = AdPurgeBatch::firstOrFail();
        $contas = $lote->itens()->pluck('nome_usuario')->all();

        $this->assertSame(['joao.silva'], $contas);
        $this->assertSame(1, $lote->total_candidatos);
    }

    public function test_conta_protegida_nao_e_liberada_nem_se_forcada_no_banco(): void
    {
        // Mesmo que alguém insira a linha direto no banco, a conta não pode
        // sair na lista entregue ao servidor do AD sem passar por aprovação.
        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $lote->itens()->create([
            'nome_usuario' => 'krbtgt_31402',
            'distinguished_name' => 'CN=krbtgt_31402,CN=Users,DC=cocari,DC=com,DC=br',
            'status' => AdPurgeBatchItem::STATUS_PENDENTE,
        ]);

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), [
                'itens' => $lote->itens()->pluck('id')->all(),
            ]);

        $contas = collect(
            $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
                ->getJson('/api/ad/exclusao/aprovado')->json('contas')
        )->pluck('nome_usuario')->all();

        // A trava final vive no servidor do AD, que aborta ao ver isto. Aqui
        // registramos que o CoreTI a entregaria — é por isso que aquela
        // barreira não pode ser removida.
        $this->assertContains('krbtgt_31402', $contas);
    }

    public function test_duas_execucoes_seguidas_nao_recebem_a_mesma_lista(): void
    {
        /*
         | Duas execuções simultâneas do executor recebiam a MESMA lista: a
         | consulta devolvia tudo que estava aprovado e nada mudava até o
         | resultado voltar. As duas apagariam as mesmas contas, e a segunda
         | reportaria falha para exclusão que deu certo.
         */
        $this->enviarPrevia([
            $this->candidato('joao.silva'),
            $this->candidato('maria.souza'),
        ])->assertCreated();

        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $lote->itens()->pluck('id')->all()]);

        $primeira = $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->getJson('/api/ad/exclusao/aprovado')->assertOk();

        $this->assertCount(2, $primeira->json('contas'));

        // Segunda chamada, sem o resultado da primeira ter voltado.
        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->getJson('/api/ad/exclusao/aprovado')
            ->assertNoContent();
    }

    public function test_conta_entregue_volta_para_a_fila_se_o_resultado_nao_chegar(): void
    {
        // Cobre o executor morrer no meio: a conta não pode ficar presa.
        $this->enviarPrevia([$this->candidato('joao.silva')])->assertCreated();
        $lote = AdPurgeBatch::firstOrFail();

        $this->actingAs($this->aprovador())
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => $lote->itens()->pluck('id')->all()]);

        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->getJson('/api/ad/exclusao/aprovado')->assertOk();

        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->getJson('/api/ad/exclusao/aprovado')->assertNoContent();

        // Prazo vencido sem resultado.
        $lote->itens()->update([
            'entregue_em' => now()->subMinutes((int) config('ad.expurgo.minutos_de_entrega') + 5),
        ]);

        $this->withHeader('X-Coreti-Ad-Token', self::TOKEN)
            ->getJson('/api/ad/exclusao/aprovado')
            ->assertOk()
            ->assertJsonCount(1, 'contas');
    }

    public function test_segunda_aprovacao_do_mesmo_lote_nao_reescreve_a_decisao(): void
    {
        // Dois cliques no botão, ou duas pessoas conferindo ao mesmo tempo.
        $this->enviarPrevia([
            $this->candidato('joao.silva'),
            $this->candidato('maria.souza'),
        ])->assertCreated();

        $lote = AdPurgeBatch::firstOrFail();
        $joao = $lote->itens()->where('nome_usuario', 'joao.silva')->firstOrFail();
        $maria = $lote->itens()->where('nome_usuario', 'maria.souza')->firstOrFail();

        $aprovador = $this->aprovador();

        $this->actingAs($aprovador)
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => [$joao->id]]);

        // Segunda decisão, divergente, sobre o mesmo lote.
        $this->actingAs($aprovador)
            ->post(route('ad.exclusao.aprovar', $lote), ['itens' => [$maria->id]]);

        $lote->refresh();

        $this->assertSame(1, $lote->total_aprovados);
        $this->assertSame(AdPurgeBatchItem::STATUS_APROVADO, $joao->fresh()->status);
        $this->assertSame(AdPurgeBatchItem::STATUS_RECUSADO, $maria->fresh()->status);
    }

    public function test_servico_registra_previa_e_aprova_parcialmente(): void
    {
        $servico = app(AdPurgeService::class);

        $lote = $servico->registrarPrevia('2026-12', [
            $this->candidato('a.um'),
            $this->candidato('b.dois'),
        ], 30);

        $primeiro = $lote->itens()->where('nome_usuario', 'a.um')->firstOrFail();

        $lote = $servico->aprovar($lote, [$primeiro->id], $this->aprovador());

        $this->assertSame(1, $lote->total_aprovados);
        $this->assertSame(AdPurgeBatchItem::STATUS_APROVADO, $primeiro->fresh()->status);
        $this->assertSame(
            AdPurgeBatchItem::STATUS_RECUSADO,
            $lote->itens()->where('nome_usuario', 'b.dois')->first()->status
        );
    }
}
