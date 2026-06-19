<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NetworkLocationResolverService
{
    public function allLocations(): Collection
    {
        return Cache::remember('network-datacenter.locations', now()->addHours(6), function (): Collection {
            $locations = collect();

            DB::table('unidades')
                ->select(['id_unidades', 'unidade', 'endereco'])
                ->orderBy('unidade')
                ->get()
                ->each(function ($row) use ($locations): void {
                    $label = trim((string) $row->unidade);
                    $locations->push([
                        'label' => $label,
                        'type' => 'unidade',
                        'name' => $label,
                        'id_unidade' => $row->id_unidades,
                        'id_departamento' => null,
                        'endereco' => trim((string) ($row->endereco ?? '')) ?: null,
                        'search' => $this->normalize($label),
                    ]);
                });

            DB::table('departamentos')
                ->select(['id', 'nome'])
                ->orderBy('nome')
                ->get()
                ->each(function ($row) use ($locations): void {
                    $label = trim((string) $row->nome);
                    $locations->push([
                        'label' => 'Departamento - ' . $label,
                        'type' => 'departamento',
                        'name' => $label,
                        'id_unidade' => null,
                        'id_departamento' => $row->id,
                        'endereco' => null,
                        'search' => $this->normalize($label),
                    ]);
                });

            if (DB::getSchemaBuilder()->hasTable('sede_departamentos')) {
                DB::table('sede_departamentos')
                    ->select(['id', 'nome_departamento'])
                    ->orderBy('nome_departamento')
                    ->get()
                    ->each(function ($row) use ($locations): void {
                        $label = trim((string) $row->nome_departamento);
                        $locations->push([
                            'label' => 'Sede - ' . $label,
                            'type' => 'departamento',
                            'name' => $label,
                            'id_unidade' => null,
                            'id_departamento' => $row->id,
                            'endereco' => null,
                            'search' => $this->normalize($label),
                        ]);
                    });
            }

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
