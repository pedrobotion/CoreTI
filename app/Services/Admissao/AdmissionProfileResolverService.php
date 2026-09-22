<?php

namespace App\Services\Admissao;

use App\Models\AdmissionAccessProfile;
use App\Models\AdmissionCase;
use App\Models\AdmissionProfileItem;
use Illuminate\Support\Collection;

/**
 * Decide QUAIS tarefas uma admissão gera.
 *
 * Regra de casamento: um perfil se aplica quando todos os seus critérios
 * preenchidos batem com o caso (critério nulo = curinga). Entre os perfis que
 * casam, vence o de maior prioridade e, no empate, o mais específico. Perfis
 * marcados como 'cumulativo' são somados ao vencedor em vez de disputá-lo —
 * é assim que "todo mundo da sede ganha crachá" convive com o perfil do cargo.
 */
class AdmissionProfileResolverService
{
    /**
     * Perfis aplicáveis ao caso, já ordenados.
     *
     * @return Collection<int, AdmissionAccessProfile>
     */
    public function profilesFor(AdmissionCase $case): Collection
    {
        $candidates = AdmissionAccessProfile::ativos()
            ->with('items')
            ->get()
            ->filter(fn (AdmissionAccessProfile $profile) => $this->matches($profile, $case))
            ->sortByDesc(fn (AdmissionAccessProfile $profile) => [
                $profile->prioridade,
                $profile->especificidade(),
            ])
            ->values();

        $cumulativos = $candidates->filter(fn (AdmissionAccessProfile $p) => $p->cumulativo)->values();
        $principal = $candidates->first(fn (AdmissionAccessProfile $p) => ! $p->cumulativo);

        return collect(array_filter([$principal]))
            ->concat($cumulativos)
            ->unique('id')
            ->values();
    }

    /**
     * Itens resultantes, deduplicados por task_type.
     *
     * O primeiro perfil da lista vence em caso de conflito, então o item do
     * perfil principal prevalece sobre o de um perfil cumulativo.
     *
     * @return Collection<string, AdmissionProfileItem>
     */
    public function resolveItems(AdmissionCase $case): Collection
    {
        $items = collect();

        foreach ($this->profilesFor($case) as $profile) {
            foreach ($profile->items as $item) {
                if (! $this->taskTypeIsKnown($item->task_type)) {
                    continue;
                }

                if (! $items->has($item->task_type)) {
                    $items->put($item->task_type, $item);
                }
            }
        }

        return $items;
    }

    /**
     * Explica a resolução para exibir ao RH antes do envio.
     *
     * @return array{perfis: array<int, string>, itens: array<int, array<string, mixed>>, sem_perfil: bool}
     */
    public function preview(AdmissionCase $case): array
    {
        $profiles = $this->profilesFor($case);
        $items = $this->resolveItems($case);

        return [
            'perfis' => $profiles->pluck('nome')->all(),
            'sem_perfil' => $profiles->isEmpty(),
            'itens' => $items->map(fn (AdmissionProfileItem $item) => [
                'task_type' => $item->task_type,
                'label' => $item->label(),
                'area' => $item->area(),
                'area_label' => (string) config('admissao.areas.'.$item->area().'.label', $item->area()),
                'obrigatorio' => $item->obrigatorio,
                'sla_dias_antes' => $item->slaDiasAntes(),
                'faltando' => $this->camposFaltantes($case, $item->task_type),
            ])->values()->all(),
        ];
    }

    /**
     * Campos do caso exigidos pela tarefa e ainda não preenchidos.
     *
     * @return array<int, string>
     */
    public function camposFaltantes(AdmissionCase $case, string $taskType): array
    {
        $required = (array) config("admissao.task_types.{$taskType}.requer_dado", []);

        $mapa = [
            'nome' => $case->nome,
            'matricula' => $case->matricula,
            'cargo' => $case->cargo_nome ?: $case->cargo_source_id,
            'unidade' => $case->unidade_nome ?: $case->unidade_source_id,
            'data_inicio' => $case->data_inicio,
            'tamanho_uniforme' => $case->tamanho_uniforme,
            'centro_custo' => $case->centro_custo,
            'cpf' => $case->cpf,
        ];

        return array_values(array_filter(
            $required,
            fn (string $campo) => blank($mapa[$campo] ?? null)
        ));
    }

    private function matches(AdmissionAccessProfile $profile, AdmissionCase $case): bool
    {
        $criterios = [
            'cargo_source_id' => $case->cargo_source_id,
            'area_source_id' => $case->area_source_id,
            'departamento_source_id' => $case->departamento_source_id,
            'unidade_source_id' => $case->unidade_source_id,
        ];

        foreach ($criterios as $campo => $valorDoCaso) {
            $esperado = $profile->{$campo};

            if ($esperado === null || $esperado === '') {
                continue; // curinga
            }

            if ((string) $esperado !== (string) $valorDoCaso) {
                return false;
            }
        }

        return true;
    }

    private function taskTypeIsKnown(string $taskType): bool
    {
        return config("admissao.task_types.{$taskType}") !== null;
    }
}
