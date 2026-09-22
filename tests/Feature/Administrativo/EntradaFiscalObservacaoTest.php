<?php

namespace Tests\Feature\Administrativo;

use App\Models\BancadaEquipment;
use App\Models\User;
use App\Services\BancadaStatusFlowService;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Observação da entrada fiscal (Administrativo → Entrada Fiscal).
 *
 * Campo próprio, separado de 'observacao', que é a observação do equipamento
 * usada na bancada, na listagem e na etiqueta impressa.
 */
class EntradaFiscalObservacaoTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): User
    {
        // A rota exige perfil admin e o módulo administrativo.
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        app(ModuleAccessService::class)->sync($user, ['administrativo' => ['manage']]);

        return $user->fresh();
    }

    private function equipamentoAguardandoEntrada(array $override = []): BancadaEquipment
    {
        return BancadaEquipment::create(array_merge([
            'tipo_equipamento' => 'Notebook',
            'plaqueta' => 'TESTE-001',
            'unidade_setor' => 'Unidade Teste',
            'data_chegada' => '2026-09-01',
            'origem_tipo' => 'unidade',
            'status' => BancadaStatusFlowService::STATUS_AGUARDANDO_ENTRADA_FISCAL,
            'entrada_status' => BancadaStatusFlowService::ENTRY_PENDING,
            'observacao' => 'Observação original do equipamento',
        ], $override));
    }

    private function processar(User $user, BancadaEquipment $e, array $dados = [])
    {
        // A rota é PATCH; o formulário usa POST + @method('PATCH').
        return $this->actingAs($user)->patch(
            route('bancada-servicos.assets.administrative.process', $e),
            array_merge([
                'action' => 'entrada',
                'nota_documento_entrada' => 'NF-E',
                'nota_numero_entrada' => '123456',
                'data_emissao_entrada' => '2026-09-01',
                'nota_valor_entrada' => '1.234,56',
            ], $dados)
        );
    }

    public function test_observacao_da_entrada_fiscal_e_gravada(): void
    {
        $e = $this->equipamentoAguardandoEntrada();

        $this->processar($this->operador(), $e, [
            'nota_observacao_entrada' => 'Nota recebida com valor divergente do orçamento.',
        ])->assertRedirect();

        $e->refresh();

        $this->assertSame('Nota recebida com valor divergente do orçamento.', $e->nota_observacao_entrada);
        $this->assertSame(BancadaStatusFlowService::ENTRY_DONE, $e->entrada_status);
    }

    /** O campo novo não pode encostar na observação do equipamento. */
    public function test_nao_sobrescreve_a_observacao_do_equipamento(): void
    {
        $e = $this->equipamentoAguardandoEntrada();

        $this->processar($this->operador(), $e, [
            'nota_observacao_entrada' => 'Observação fiscal',
        ]);

        $e->refresh();

        $this->assertSame('Observação original do equipamento', $e->observacao);
        $this->assertSame('Observação fiscal', $e->nota_observacao_entrada);
    }

    public function test_observacao_e_opcional(): void
    {
        $e = $this->equipamentoAguardandoEntrada();

        $this->processar($this->operador(), $e)->assertRedirect();

        $e->refresh();

        $this->assertNull($e->nota_observacao_entrada);
        $this->assertSame(BancadaStatusFlowService::ENTRY_DONE, $e->entrada_status);
    }

    public function test_observacao_em_branco_vira_nulo(): void
    {
        $e = $this->equipamentoAguardandoEntrada();

        $this->processar($this->operador(), $e, ['nota_observacao_entrada' => '   '])
            ->assertRedirect();

        $e->refresh();

        $this->assertNull($e->nota_observacao_entrada);
        // Confirma que a entrada foi de fato processada, e não que a
        // requisição falhou e deixou o campo nulo por acidente.
        $this->assertSame(BancadaStatusFlowService::ENTRY_DONE, $e->entrada_status);
    }

    public function test_observacao_fica_registrada_no_historico(): void
    {
        $e = $this->equipamentoAguardandoEntrada();

        $this->processar($this->operador(), $e, [
            'nota_observacao_entrada' => 'Item chegou sem a fonte.',
        ]);

        $evento = $e->fresh()->events()
            ->where('action', 'entrada_fiscal_realizada')
            ->latest('id')
            ->first();

        $this->assertNotNull($evento, 'a entrada fiscal deve gerar evento no histórico');
        $this->assertSame('Item chegou sem a fonte.', $evento->metadata['observacao'] ?? null);
    }

    public function test_texto_acima_do_limite_e_recusado(): void
    {
        $e = $this->equipamentoAguardandoEntrada();

        $this->processar($this->operador(), $e, [
            'nota_observacao_entrada' => str_repeat('x', 2001),
        ])->assertSessionHasErrors('nota_observacao_entrada');

        $this->assertNull($e->fresh()->nota_observacao_entrada);
    }
}
