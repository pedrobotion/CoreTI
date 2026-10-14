<?php

namespace App\Services\Bancada;

use App\Models\BancadaEquipment;
use App\Models\BancadaMaloteRoute;
use App\Services\BancadaDeliveryScheduleService;
use App\Services\BancadaStatusFlowService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Detecta os equipamentos que precisam de NOTA FISCAL DE SAÍDA no dia da
 * separação da rota: equipamentos NÃO-Sede, em "Pronto para entrega", ainda
 * sem nota de saída emitida, cuja rota (casada pela unidade de destino, igual
 * à tela "Aguardando entrega") tem o dia de separação HOJE.
 *
 * Eles devem ir ao local de embarque no dia da separação (até ~15:00), então o
 * chamado para emitir a nota é aberto na manhã desse dia.
 */
class NotaSaidaSeparacaoService
{
    public function __construct(
        private readonly BancadaStatusFlowService $statusFlow,
        private readonly BancadaDeliveryScheduleService $schedule,
    ) {
    }

    /**
     * @return list<array{equipment: BancadaEquipment, route: BancadaMaloteRoute, separation: CarbonImmutable}>
     */
    public function detectarParaData(?CarbonImmutable $base = null): array
    {
        $base = $base ?? CarbonImmutable::today();

        // Rotas que não geram nota de saída (ex.: a rota interna da TI).
        $excluidas = collect((array) config('bancada.notas_saida.rotas_excluidas', []))
            ->map(fn ($nome) => mb_strtolower(trim((string) $nome)))
            ->filter()
            ->all();

        /** @var Collection<int, BancadaMaloteRoute> $routes */
        $routes = BancadaMaloteRoute::query()
            ->where('ativo', true)
            ->with('units')
            ->orderBy('ordem')
            ->get()
            ->reject(fn (BancadaMaloteRoute $route) => in_array(
                mb_strtolower(trim((string) $route->nome)),
                $excluidas,
                true
            ))
            ->values();

        // Dia de separação de cada rota para a semana logística de referência.
        $separacaoPorRota = [];
        foreach ($routes as $route) {
            $agenda = $this->schedule->nextDates($route, $base);
            $separacaoPorRota[$route->id] = $agenda['separation'] ?? null;
        }

        $equipamentos = BancadaEquipment::query()
            ->where('status', BancadaStatusFlowService::STATUS_PRONTO_ENTREGA)
            ->whereNull('nota_saida_emitida_em')
            ->orderBy('id')
            ->get();

        $resultado = [];
        foreach ($equipamentos as $equipment) {
            // Só não-Sede: Sede/unidades isentas não emitem nota de saída.
            if (! $this->statusFlow->requiresFiscalFlow($equipment)) {
                continue;
            }

            $route = $this->rotaDoEquipamento($equipment, $routes);
            if (! $route) {
                continue;
            }

            $separacao = $separacaoPorRota[$route->id] ?? null;
            if (! $separacao || $separacao->toDateString() !== $base->toDateString()) {
                continue;
            }

            $resultado[] = [
                'equipment' => $equipment,
                'route' => $route,
                'separation' => $separacao,
            ];
        }

        return $resultado;
    }

    /**
     * Casa o equipamento à rota pela unidade de destino — mesma regra da tela
     * "Aguardando entrega": primeira rota (por ordem) que lista a unidade.
     *
     * @param  Collection<int, BancadaMaloteRoute>  $routes
     */
    private function rotaDoEquipamento(BancadaEquipment $equipment, Collection $routes): ?BancadaMaloteRoute
    {
        $alvo = mb_strtolower(trim((string) $equipment->unidade_setor));
        if ($alvo === '') {
            return null;
        }

        foreach ($routes as $route) {
            foreach ($route->units as $unit) {
                if (mb_strtolower(trim((string) $unit->unit_label)) === $alvo) {
                    return $route;
                }
            }
        }

        return null;
    }
}
