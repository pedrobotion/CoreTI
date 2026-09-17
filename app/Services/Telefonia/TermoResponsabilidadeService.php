<?php

namespace App\Services\Telefonia;

use App\Models\AssetAssignment;
use App\Models\AssetAssignmentItem;
use App\Models\Municipio;
use App\Models\OrganizationArea;
use App\Models\OrganizationDepartment;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use Illuminate\Support\Facades\DB;

/**
 * Monta o Termo de Responsabilidade de Celular Corporativo.
 *
 * Reproduz o documento oficial da COCARI (Termo de Responsabilidade.docm):
 * mesma numeração contínua, mesmas cláusulas, mesmos campos de rodapé.
 */
class TermoResponsabilidadeService
{
    private const SEQUENCIA_CELULAR = 'celular';

    /**
     * Número do termo, atribuído na primeira geração e preservado depois.
     *
     * Não é atribuído na criação do vínculo de propósito: o número identifica
     * o documento, e vínculo que nunca gerou termo queimaria um número da
     * série. Uma vez atribuído nunca muda — regerar o PDF traz o mesmo número,
     * senão a via assinada deixaria de bater com o sistema.
     */
    public function numero(AssetAssignment $assignment): int
    {
        if ($assignment->numero_termo !== null) {
            return $assignment->numero_termo;
        }

        return DB::transaction(function () use ($assignment): int {
            // Relê dentro da transação: outra requisição pode ter numerado
            // este mesmo vínculo enquanto esperávamos o lock.
            $atual = AssetAssignment::query()->whereKey($assignment->id)->lockForUpdate()->value('numero_termo');

            if ($atual !== null) {
                $assignment->setAttribute('numero_termo', (int) $atual);

                return (int) $atual;
            }

            $numero = (int) DB::table('termo_sequencias')
                ->where('chave', self::SEQUENCIA_CELULAR)
                ->lockForUpdate()
                ->value('proximo');

            if ($numero <= 0) {
                $numero = (int) config('telefonia.termo.numero_inicial', 1617);
            }

            DB::table('termo_sequencias')
                ->where('chave', self::SEQUENCIA_CELULAR)
                ->update(['proximo' => $numero + 1, 'updated_at' => now()]);

            $assignment->forceFill(['numero_termo' => $numero])->save();

            return $numero;
        });
    }

    /**
     * Todos os campos que o documento precisa, já resolvidos.
     *
     * @return array<string, mixed>
     */
    public function dados(AssetAssignment $assignment): array
    {
        $assignment->loadMissing(['collaborator', 'items.line', 'items.device']);

        $colaborador = $assignment->collaborator;
        $linha = $assignment->items->first(fn (AssetAssignmentItem $i) => $i->corp_line_id !== null)?->line;
        $aparelho = $assignment->items->first(fn (AssetAssignmentItem $i) => $i->corp_device_id !== null)?->device;
        $unidade = $colaborador?->unit_source_id
            ? OrganizationUnit::query()->where('source_id', $colaborador->unit_source_id)->first()
            : null;

        return [
            'numero' => $this->numero($assignment),
            'colaborador' => $colaborador?->name ?? '—',
            'matricula' => $assignment->collaborator_matricula ?? $colaborador?->employee_number ?? '—',
            'area' => $this->nome(OrganizationArea::class, $colaborador?->area_source_id),
            'local' => $unidade?->name ?? '—',
            'departamento' => $this->nome(OrganizationDepartment::class, $colaborador?->department_source_id),
            'cargo' => $this->nome(OrganizationPosition::class, $colaborador?->position_source_id),
            'modelo' => $aparelho?->descricao,
            'imei' => $aparelho?->imei_1_formatado,
            'numero_serie' => $aparelho?->numero_serie,
            'chip' => $linha?->numero_formatado,
            'operadora' => $linha?->operadora,
            'cidade' => $this->cidade($unidade),
            'data' => $assignment->data_vinculo ?? now(),
            'estado' => $this->estadoDoAparelho($assignment, $aparelho?->id),
            'instrucao_normativa' => (string) config('telefonia.termo.instrucao_normativa', 'IN-06/18'),
        ];
    }

    /**
     * Marca "Novo", "Troca Venc." ou "Usado" e, neste caso, o termo anterior.
     *
     * A regra segue o que o documento pergunta:
     *  - o aparelho já foi de alguém? então é Usado, e o termo anterior dele
     *    é a referência que o formulário pede;
     *  - não foi de ninguém, mas substitui um termo vencido do mesmo
     *    colaborador? é troca por vencimento;
     *  - caso contrário, vale o estado cadastrado no aparelho.
     *
     * @return array{tipo: string, termo_anterior: int|null}
     */
    private function estadoDoAparelho(AssetAssignment $assignment, ?int $aparelhoId): array
    {
        if ($aparelhoId !== null) {
            $anterior = AssetAssignment::query()
                ->whereKeyNot($assignment->id)
                ->whereNotNull('numero_termo')
                ->whereHas('items', fn ($q) => $q->where('corp_device_id', $aparelhoId))
                ->where('data_vinculo', '<=', $assignment->data_vinculo ?? now())
                ->orderByDesc('data_vinculo')
                ->first();

            if ($anterior !== null) {
                return ['tipo' => 'usado', 'termo_anterior' => $anterior->numero_termo];
            }
        }

        $trocaPorVencimento = AssetAssignment::query()
            ->whereKeyNot($assignment->id)
            ->where('hr_collaborator_id', $assignment->hr_collaborator_id)
            ->where('ativo', false)
            ->whereDate('data_vencimento', '<=', $assignment->data_vinculo ?? now())
            ->exists();

        if ($trocaPorVencimento) {
            return ['tipo' => 'troca_vencimento', 'termo_anterior' => null];
        }

        $aparelho = $aparelhoId !== null
            ? $assignment->items->first(fn (AssetAssignmentItem $i) => $i->corp_device_id === $aparelhoId)?->device
            : null;

        return [
            'tipo' => $aparelho?->estado_aparelho === 'Novo' ? 'novo' : 'usado',
            'termo_anterior' => null,
        ];
    }

    private function cidade(?OrganizationUnit $unidade): string
    {
        $padrao = (string) config('telefonia.termo.cidade_padrao', 'Mandaguari');

        if ($unidade?->municipality_id === null || $unidade->municipality_id === '') {
            return $padrao;
        }

        return Municipio::query()->where('codigo_ibge', $unidade->municipality_id)->value('nome') ?? $padrao;
    }

    /** @param  class-string  $modelo */
    private function nome(string $modelo, int|string|null $sourceId): string
    {
        if ($sourceId === null || $sourceId === '') {
            return '—';
        }

        return $modelo::query()->where('source_id', (string) $sourceId)->value('name') ?? '—';
    }
}
