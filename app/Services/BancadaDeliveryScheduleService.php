<?php

namespace App\Services;

use App\Models\BancadaMaloteRoute;
use Carbon\CarbonImmutable;

class BancadaDeliveryScheduleService
{
    /**
     * Calcula próximas datas da agenda logística de malote.
     * Regra: usa a próxima ocorrência do dia de separação (inclusive hoje)
     * e ancora carregamento/entrega na mesma semana logística da separação.
     */
    public function nextDates(BancadaMaloteRoute $route, ?CarbonImmutable $baseDate = null): ?array
    {
        $base = $baseDate ?? CarbonImmutable::today();

        $sepWeekday = $this->weekdayToIso($route->dia_separa);
        $loadWeekday = $this->weekdayToIso($route->dia_carrega);
        $delWeekday = $this->weekdayToIso($route->dia_entrega);

        if (! $sepWeekday || ! $loadWeekday || ! $delWeekday) {
            return null;
        }

        $separation = $this->nextOccurrenceOnOrAfter($base, $sepWeekday);
        $weekStart = $separation->startOfWeek(CarbonImmutable::MONDAY);

        $loading = $weekStart->addDays($loadWeekday - 1);
        $delivery = $weekStart->addDays($delWeekday - 1);

        return [
            'separation' => $separation,
            'loading' => $loading,
            'delivery' => $delivery,
        ];
    }

    private function weekdayToIso(?string $weekday): ?int
    {
        $map = [
            'Segunda' => 1,
            'Terça' => 2,
            'Quarta' => 3,
            'Quinta' => 4,
            'Sexta' => 5,
            'Sábado' => 6,
            'Domingo' => 7,
        ];

        return $map[trim((string) $weekday)] ?? null;
    }

    private function nextOccurrenceOnOrAfter(CarbonImmutable $base, int $targetIsoWeekday): CarbonImmutable
    {
        $current = $base->isoWeekday();
        $delta = $targetIsoWeekday - $current;
        if ($delta < 0) {
            $delta += 7;
        }

        return $base->addDays($delta);
    }
}
