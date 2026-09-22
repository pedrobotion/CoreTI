<?php

namespace App\Services\Cmdb\Veeam;

use App\Models\CmdbAsset;
use Illuminate\Support\Str;

class VeeamVmMatcherService
{
    public function match(array $object): array
    {
        $candidates = collect();
        $method = null;

        // The current CMDB schema exposes the vCenter external identifier directly.
        // UUID-specific matching will be enabled by the Veeam adapter when the
        // corresponding vCenter identifiers are available in the connector payload.
        foreach ([
            'external_id' => ['source_external_id'],
        ] as $key => $paths) {
            $value = trim((string) data_get($object, $key, ''));
            if ($value === '') {
                continue;
            }

            foreach ($paths as $path) {
                $query = CmdbAsset::query()->where('asset_type', 'virtual_machine');
                $query->where($path, $value);
                $found = $query->get();
                if ($found->isNotEmpty()) {
                    $candidates = $found;
                    $method = $key;
                    break 2;
                }
            }
        }

        if ($candidates->isEmpty() && filled($name = trim((string) data_get($object, 'name', '')))) {
            $candidates = CmdbAsset::query()
                ->where('asset_type', 'virtual_machine')
                ->where(function ($query) use ($name): void {
                    $query->where('hostname', $name)->orWhere('name', $name);
                })
                ->get();
            $method = 'exact_name';
        }

        if ($candidates->isEmpty() && filled($hostname = $this->normalizeHostname((string) data_get($object, 'hostname', '')))) {
            $candidates = CmdbAsset::query()
                ->where('asset_type', 'virtual_machine')
                ->where(function ($query) use ($hostname): void {
                    $query->whereRaw('LOWER(REPLACE(hostname, ".", "")) = ?', [$hostname])
                        ->orWhereRaw('LOWER(REPLACE(name, ".", "")) = ?', [$hostname]);
                })
                ->get();
            $method = 'normalized_hostname';
        }

        if ($candidates->count() !== 1) {
            return [
                'asset' => null,
                'status' => $candidates->isEmpty() ? 'not_found' : 'ambiguous',
                'method' => $method,
                'confidence' => null,
                'candidates' => $candidates->pluck('id')->values()->all(),
            ];
        }

        return [
            'asset' => $candidates->first(),
            'status' => 'matched',
            'method' => $method,
            'confidence' => $method === 'exact_name' ? 95.00 : 100.00,
            'candidates' => [$candidates->first()->id],
        ];
    }

    private function normalizeHostname(string $value): string
    {
        return Str::of($value)->lower()->replace(['.', '-', '_'], '')->trim()->value();
    }
}
