<?php

namespace App\Observers;

use App\Models\BancadaEquipment;
use App\Models\BancadaEquipmentEvent;
use App\Services\Jira\JiraTicketOpener;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Espelha o processo do CoreTI no chamado de manutenção do Jira: a cada evento
 * do equipamento (entrada fiscal, terceiros, peças, nota de saída, mudança de
 * status, etc.), posta um comentário no chamado cuja chave está em
 * `equipment->tic`. Roda após o commit e é defensivo — falha no Jira nunca
 * quebra o fluxo do CoreTI.
 */
class BancadaEquipmentEventObserver
{
    /** Ações que NÃO viram comentário (seriam ruído/duplicadas). */
    private const IGNORAR = [
        'manutencao_chamado_finalizado',  // já veio do Jira (webhook)
    ];

    /** Rótulos amigáveis para campos do metadata. */
    private const ROTULOS = [
        'docto' => 'Documento de entrada',
        'docto_entrada' => 'Documento de entrada',
        'numero_nota' => 'Número da nota',
        'numero_nota_entrada' => 'Número da nota de entrada',
        'operacao' => 'Operação',
        'data_emissao' => 'Data de emissão',
        'valor' => 'Valor',
        'valor_nota_entrada' => 'Valor da nota de entrada',
        'numero_nota_saida' => 'Número da nota de saída',
        'data_emissao_saida' => 'Emissão da nota de saída',
        'empresa' => 'Empresa terceirizada',
        'cnpj' => 'CNPJ',
        'os_numero' => 'OS',
        'nota_remessa' => 'Nota de remessa',
        'nota_orcamento' => 'Nº do orçamento',
        'valor_reparo' => 'Valor do reparo',
        'resultado' => 'Resultado',
        'peca_nome' => 'Peça',
        'peca_quantidade' => 'Quantidade',
        'peca_origem' => 'Origem da peça',
        'unidade' => 'Unidade',
        'departamento' => 'Departamento',
        'observacao' => 'Observação',
    ];

    private const OPERACOES = [
        'transferencia_imobilizado' => 'Transferência de imobilizado',
        'remessa_manutencao' => 'Remessa p/ Manutenção',
    ];

    private const ORIGENS_PECA = [
        'cd' => 'Estoque do CD',
        'compra_internet' => 'Compra pela internet',
        'estoque_ti' => 'Estoque interno da TI',
        'dell' => 'Fornecedor Dell',
    ];

    private const RESULTADOS_TERCEIROS = [
        'aprovada' => 'Aprovada',
        'aprovado' => 'Aprovado',
        'negada' => 'Negada',
        'reprovado' => 'Reprovado',
        'reprovada' => 'Reprovada',
        'sem_conserto' => 'Sem conserto',
    ];

    public function created(BancadaEquipmentEvent $event): void
    {
        if (! (bool) config('bancada.manutencao.comentar_jira', true)) {
            return;
        }
        if (in_array($event->action, self::IGNORAR, true)) {
            return;
        }

        try {
            $equipment = $event->equipment ?? $event->equipment()->first();
        } catch (Throwable $e) {
            return;
        }

        $tic = $equipment ? trim((string) $equipment->tic) : '';
        if ($tic === '') {
            return;
        }

        $texto = $this->montarComentario($event, $equipment);
        if ($texto === '') {
            return;
        }

        // Fora da transação: só posta depois do commit (ou já, se não houver).
        DB::afterCommit(function () use ($tic, $texto): void {
            try {
                app(JiraTicketOpener::class)->comentar($tic, $texto);
            } catch (Throwable $e) {
                Log::warning('bancada.jira_comentario.falha', ['tic' => $tic, 'erro' => $e->getMessage()]);
            }
        });
    }

    private function montarComentario(BancadaEquipmentEvent $event, ?BancadaEquipment $equipment): string
    {
        // Abertura do chamado: a entrada fiscal ocorreu ANTES do chamado existir
        // (equipamento ainda sem tic), então a trazemos aqui. Sem entrada
        // fiscal, não comenta nada (não vira um comentário genérico de abertura).
        if ($event->action === 'manutencao_chamado') {
            return $this->blocoEntradaFiscal($equipment);
        }

        $linhas = ['[CoreTI] Atualização do processo'];

        $obs = trim((string) $event->observation);
        if ($obs !== '') {
            $linhas[] = $obs;
        }

        $meta = is_array($event->metadata) ? $event->metadata : [];
        foreach (self::ROTULOS as $chave => $rotulo) {
            if (! array_key_exists($chave, $meta)) {
                continue;
            }
            $valor = $meta[$chave];
            if ($valor === null || $valor === '' || ! is_scalar($valor)) {
                continue;
            }
            $valor = $this->formatarValor($chave, $valor);
            if ($valor !== '') {
                $linhas[] = $rotulo . ': ' . $valor;
            }
        }

        // Dados administrativos da fase, puxados do equipamento (o evento às
        // vezes vem pobre, mas o equipamento tem os campos preenchidos).
        $linhas = array_merge($linhas, $this->dadosAdministrativosDaFase($event, $equipment));

        $de = trim((string) $event->previous_status);
        $para = trim((string) $event->new_status);
        if ($de !== '' && $para !== '' && $de !== $para) {
            $linhas[] = 'Status: ' . $de . ' → ' . $para;
        }

        // Só vale comentar se tem mais que o cabeçalho.
        return count($linhas) > 1 ? implode("\n", array_unique($linhas)) : '';
    }

    /** Bloco de entrada fiscal a partir do equipamento (ou '' se não houver). */
    private function blocoEntradaFiscal(?BancadaEquipment $e): string
    {
        if (! $e) {
            return '';
        }
        $linhas = [];
        $this->addLinha($linhas, 'Documento de entrada', $e->nota_documento_entrada);
        $this->addLinha($linhas, 'Número da nota', $e->nota_numero_entrada);
        $op = (string) ($e->nota_operacao_entrada ?? '');
        $this->addLinha($linhas, 'Operação', $op !== '' ? (self::OPERACOES[$op] ?? $op) : '');
        $this->addLinha($linhas, 'Valor', $this->dinheiro($e->nota_valor_entrada));
        $this->addLinha($linhas, 'Data de emissão', $e->data_emissao_entrada?->format('d/m/Y'));
        $this->addLinha($linhas, 'Observação', $e->nota_observacao_entrada);

        return $linhas === [] ? '' : implode("\n", array_merge(['[CoreTI] Entrada fiscal registrada'], $linhas));
    }

    /**
     * @return array<int, string>
     */
    private function dadosAdministrativosDaFase(BancadaEquipmentEvent $event, ?BancadaEquipment $e): array
    {
        if (! $e) {
            return [];
        }
        $acao = (string) $event->action;
        $linhas = [];

        // Fase de terceiros: empresa, OS, orçamento, resultado.
        if (str_contains($acao, 'terceiros') || trim((string) $event->new_status) === 'Terceiros') {
            $this->addLinha($linhas, 'Empresa terceirizada', $e->terceiros_empresa);
            $this->addLinha($linhas, 'CNPJ', $e->terceiros_cnpj);
            $this->addLinha($linhas, 'OS', $e->terceiros_os_numero);
            $this->addLinha($linhas, 'Nota de remessa', $e->terceiros_nota_remessa);
            $this->addLinha($linhas, 'Valor do reparo', $this->dinheiro($e->terceiros_valor_reparo));
            $res = (string) ($e->terceiros_resultado ?? '');
            $this->addLinha($linhas, 'Resultado', $res !== '' ? (self::RESULTADOS_TERCEIROS[$res] ?? $res) : '');
            $this->addLinha($linhas, 'Problema relatado', $e->terceiros_problema);
        }

        return $linhas;
    }

    /** @param array<int,string> $linhas */
    private function addLinha(array &$linhas, string $rotulo, mixed $valor): void
    {
        $valor = is_scalar($valor) ? trim((string) $valor) : '';
        if ($valor !== '') {
            $linhas[] = $rotulo . ': ' . $valor;
        }
    }

    private function dinheiro(mixed $valor): string
    {
        return ($valor !== null && $valor !== '' && is_numeric($valor))
            ? 'R$ ' . number_format((float) $valor, 2, ',', '.')
            : '';
    }

    private function formatarValor(string $chave, mixed $valor): string
    {
        if ($chave === 'operacao') {
            return self::OPERACOES[$valor] ?? (string) $valor;
        }
        if ($chave === 'peca_origem') {
            return self::ORIGENS_PECA[$valor] ?? (string) $valor;
        }
        if ($chave === 'resultado') {
            return self::RESULTADOS_TERCEIROS[$valor] ?? (string) $valor;
        }
        if (in_array($chave, ['valor', 'valor_nota_entrada', 'valor_reparo'], true) && is_numeric($valor)) {
            return 'R$ ' . number_format((float) $valor, 2, ',', '.');
        }

        return (string) $valor;
    }
}
