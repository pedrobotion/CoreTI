<?php

namespace App\Services;

use Illuminate\Support\Collection;

class CoordenacaoParticipantResolverService
{
    public function __construct(private readonly CoordenacaoCostAnalysisService $analysis)
    {
    }

    public function all(): Collection
    {
        return $this->analysis->participants();
    }

    public function active(): Collection
    {
        return $this->all()->filter(fn (array $person): bool => (bool) ($person['ad_active'] ?? false))->values();
    }
}
