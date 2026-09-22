<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NetworkLocationResolverService
{
    public function allLocations(): Collection
    {
        return Cache::remember('network-datacenter.locations', now()->addHours(6), function (): Collection {
            $locations = collect();

            app(OrganizationLookupService::class)->unitOptions()
                ->each(function ($row) use ($locations): void {
                    $label = trim((string) $row->name);
                    $locations->push([
                        'label' => $label,
                        'type' => 'unidade',
                        'name' => $label,
                        'id_unidade' => $row->organization_unit_id,
                        'id_departamento' => null,
                        'endereco' => trim((string) ($row->address ?? '')) ?: null,
                        'search' => $this->normalize($label),
                    ]);
                });

            app(OrganizationLookupService::class)->departmentOptions()
                ->each(function ($row) use ($locations): void {
                    $label = trim((string) $row->name);
                    $locations->push([
                        'label' => 'Departamento - ' . $label,
                        'type' => 'departamento',
                        'name' => $label,
                        'id_unidade' => null,
                        'id_departamento' => $row->organization_department_id,
                        'endereco' => null,
                        'search' => $this->normalize($label),
                    ]);
                });

            return $locations
                ->unique(fn (array $item): string => $item['type'] . '|' . $item['name'])
                ->values();
        });
    }

    public function resolve(string $term): ?array
    {
        $needle = $this->normalize($term);
        if ($needle === '') {
            return null;
        }

        foreach ($this->allLocations() as $location) {
            if ($location['search'] === $needle) {
                return $location;
            }
        }

        foreach ($this->allLocations() as $location) {
            if (str_contains($location['search'], $needle) || str_contains($needle, $location['search'])) {
                return $location;
            }
        }

        return null;
    }

    public function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/^(unidades?|unidade|sede|departamento)\s*-\s*/i', '', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);

        return trim((string) $value);
    }
}
