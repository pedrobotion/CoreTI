<?php

namespace App\Observers;

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
        'manutencao_chamado',             // é a própria abertura do chamado
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

        $texto = $this->montarComentario($event);
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

    private function montarComentario(BancadaEquipmentEvent $event): string
    {
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

        $de = trim((string) $event->previous_status);
        $para = trim((string) $event->new_status);
        if ($de !== '' && $para !== '' && $de !== $para) {
            $linhas[] = 'Status: ' . $de . ' → ' . $para;
        }

        // Só vale comentar se tem mais que o cabeçalho.
        return count($linhas) > 1 ? implode("\n", $linhas) : '';
    }

    private function formatarValor(string $chave, mixed $valor): string
    {
        if ($chave === 'operacao') {
            return self::OPERACOES[$valor] ?? (string) $valor;
        }
        if ($chave === 'peca_origem') {
            return self::ORIGENS_PECA[$valor] ?? (string) $valor;
        }
        if (in_array($chave, ['valor', 'valor_nota_entrada', 'valor_reparo'], true) && is_numeric($valor)) {
            return 'R$ ' . number_format((float) $valor, 2, ',', '.');
        }

        return (string) $valor;
    }
}
