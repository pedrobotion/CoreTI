<?php

namespace App\Services\Estoque;

use App\Models\InternalStockMovement;
use App\Models\InternalStockProduct;
use App\Models\InternalStockUnit;
use Illuminate\Support\Facades\DB;

/**
 * Regras de entrada e baixa do estoque interno da TI.
 *
 * Centraliza a mexida no saldo e no extrato (internal_stock_movements), para
 * que a tela e a integração com a bancada escrevam da mesma forma — e sempre
 * dentro de transação, com o saldo registrado em saldo_apos.
 */
class InternalStockService
{
    /**
     * Dá entrada em um produto.
     *
     * Serial: cria uma unidade física (um serial por vez). Quantidade: soma ao
     * saldo. Devolve a unidade criada quando serial, ou null.
     *
     * @param  array<string, mixed>  $dados
     */
    public function entrada(InternalStockProduct $produto, array $dados, ?int $userId): ?InternalStockUnit
    {
        return DB::transaction(function () use ($produto, $dados, $userId) {
            $unidade = null;
            $quantidade = $produto->ehSerial() ? 1 : max(1, (int) ($dados['quantidade'] ?? 0));

            if ($produto->ehSerial()) {
                $unidade = $produto->units()->create([
                    'serial_number' => $dados['serial_number'] ?? null,
                    'plaqueta' => $dados['plaqueta'] ?? null,
                    'modelo' => $dados['modelo'] ?? null,
                    'estado' => $dados['estado'] ?? 'Usado',
                    'status' => InternalStockUnit::STATUS_EM_ESTOQUE,
                    'observacao' => $dados['observacao'] ?? null,
                    'created_by' => $userId,
                ]);
            } else {
                $produto->increment('quantidade_atual', $quantidade);
                $produto->refresh();
            }

            $this->registrarMovimento($produto, InternalStockMovement::TIPO_ENTRADA, $quantidade, [
                'internal_stock_unit_id' => $unidade?->id,
                'motivo' => $dados['motivo'] ?? 'compra',
                'referencia' => $dados['referencia'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
            ], $userId);

            return $unidade;
        });
    }

    /**
     * Dá baixa. Serial exige a unidade (via $dados['internal_stock_unit_id']);
     * quantidade exige $dados['quantidade']. Lança InvalidArgumentException se
     * faltar saldo.
     *
     * @param  array<string, mixed>  $dados
     */
    public function baixa(InternalStockProduct $produto, array $dados, ?int $userId): void
    {
        DB::transaction(function () use ($produto, $dados, $userId) {
            $motivo = $dados['motivo'] ?? 'entrega_colaborador';
            $unidadeId = null;
            $quantidade = 1;

            if ($produto->ehSerial()) {
                /** @var InternalStockUnit $unidade */
                $unidade = $produto->units()
                    ->where('id', $dados['internal_stock_unit_id'] ?? 0)
                    ->where('status', InternalStockUnit::STATUS_EM_ESTOQUE)
                    ->lockForUpdate()
                    ->firstOrFail();

                $unidade->update([
                    'status' => $motivo === 'descarte' ? InternalStockUnit::STATUS_BAIXADO : InternalStockUnit::STATUS_ENTREGUE,
                ]);
                $unidadeId = $unidade->id;
            } else {
                $quantidade = max(1, (int) ($dados['quantidade'] ?? 0));
                $atual = (int) InternalStockProduct::whereKey($produto->getKey())->lockForUpdate()->value('quantidade_atual');

                if ($quantidade > $atual) {
                    throw new \InvalidArgumentException('Saldo insuficiente: há '.$atual.' em estoque.');
                }

                $produto->decrement('quantidade_atual', $quantidade);
                $produto->refresh();
            }

            $this->registrarMovimento($produto, InternalStockMovement::TIPO_BAIXA, $quantidade, [
                'internal_stock_unit_id' => $unidadeId,
                'motivo' => $motivo,
                'destino_tipo' => $dados['destino_tipo'] ?? null,
                'destino_colaborador_id' => $dados['destino_colaborador_id'] ?? null,
                'destino_descricao' => $dados['destino_descricao'] ?? null,
                'referencia' => $dados['referencia'] ?? null,
                'observacao' => $dados['observacao'] ?? null,
            ], $userId);
        });
    }

    /**
     * Baixa automática quando a bancada consome uma peça do estoque_ti.
     *
     * É "melhor esforço": tenta casar um produto ativo por quantidade cujo nome
     * bata com a peça; se achar e houver saldo, dá baixa. Devolve true se baixou.
     */
    public function registrarConsumoBancada(string $pecaNome, int $quantidade, ?string $referencia, ?int $userId = null): bool
    {
        $produto = InternalStockProduct::query()
            ->ativos()
            ->where('controle', InternalStockProduct::CONTROLE_QUANTIDADE)
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower(trim($pecaNome))])
            ->first();

        if ($produto === null || (int) $produto->quantidade_atual < max(1, $quantidade)) {
            return false;
        }

        $this->baixa($produto, [
            'quantidade' => max(1, $quantidade),
            'motivo' => 'uso_bancada',
            'destino_tipo' => 'bancada',
            'destino_descricao' => 'Consumo na bancada de serviços',
            'referencia' => $referencia,
        ], $userId);

        return true;
    }

    /** @param  array<string, mixed>  $dados */
    private function registrarMovimento(InternalStockProduct $produto, string $tipo, int $quantidade, array $dados, ?int $userId): void
    {
        $produto->movements()->create([
            'internal_stock_unit_id' => $dados['internal_stock_unit_id'] ?? null,
            'tipo' => $tipo,
            'quantidade' => $quantidade,
            'motivo' => $dados['motivo'] ?? null,
            'destino_tipo' => $dados['destino_tipo'] ?? null,
            'destino_colaborador_id' => $dados['destino_colaborador_id'] ?? null,
            'destino_descricao' => $dados['destino_descricao'] ?? null,
            'referencia' => $dados['referencia'] ?? null,
            'saldo_apos' => $produto->fresh()->saldo,
            'observacao' => $dados['observacao'] ?? null,
            'created_by' => $userId,
        ]);
    }
}
