<?php

namespace App\Services;

use App\Models\CoretiRateioLocal;
use Illuminate\Support\Str;

class CoretiRateioLocalService
{
    public function normalizeLocalName(string $name): string
    {
        $normalized = trim($name);

        if ($normalized === '') {
            return '';
        }

        $normalized = Str::of($normalized)->ascii()->upper()->value();
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;
        $normalized = preg_replace('/\s*-\s*/u', ' - ', $normalized) ?: $normalized;
        $normalized = trim($normalized);

        $prefixes = [
            '/^(UNIDADES?|UNIDADE|DEPARTAMENTOS?|DEPARTAMENTO)\s*-\s*/u',
        ];

        foreach ($prefixes as $pattern) {
            $normalized = preg_replace($pattern, '', $normalized) ?: $normalized;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;
        $normalized = trim($normalized);

        return $normalized;
    }

    public function normalizeTypeLocal(?string $type): ?string
    {
        $type = trim((string) $type);
        if ($type === '') {
            return null;
        }

        $normalized = Str::of($type)->ascii()->upper()->value();
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;

        if (str_contains($normalized, 'DEPART')) {
            return 'departamento';
        }

        if (str_contains($normalized, 'UNID')) {
            return 'unidade';
        }

        return mb_strtolower(trim($type));
    }

    public function normalizeCenterComponent(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if ($digits === '') {
            return null;
        }

        $digits = ltrim($digits, '0');

        return $digits !== '' ? $digits : '0';
    }

    public function normalizeCentroCusto(?string $unicoop, ?string $area): ?string
    {
        $u = $this->normalizeCenterComponent($unicoop);
        $a = $this->normalizeCenterComponent($area);

        if ($u === null || $a === null) {
            return null;
        }

        return $u . '.' . $a;
    }

    /**
     * @return array{unicoop:?string, area:?string, centro_custo:?string}
     */
    public function parseCentroCusto(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return ['unicoop' => null, 'area' => null, 'centro_custo' => null];
        }

        if (preg_match('/^(\d+)\.(\d+)$/', $value, $matches)) {
            $unicoop = $this->normalizeCenterComponent($matches[1]);
            $area = $this->normalizeCenterComponent($matches[2]);

            return [
                'unicoop' => $unicoop,
                'area' => $area,
                'centro_custo' => $this->normalizeCentroCusto($unicoop, $area),
            ];
        }

        if (preg_match('/^(\d{1,3})(\d{3})$/', preg_replace('/\D+/', '', $value) ?: '', $matches)) {
            $unicoop = $this->normalizeCenterComponent($matches[1]);
            $area = $this->normalizeCenterComponent($matches[2]);

            return [
                'unicoop' => $unicoop,
                'area' => $area,
                'centro_custo' => $this->normalizeCentroCusto($unicoop, $area),
            ];
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if ($digits !== '' && strlen($digits) > 3) {
            $area = substr($digits, -3);
            $unicoop = substr($digits, 0, -3);

            return [
                'unicoop' => $this->normalizeCenterComponent($unicoop),
                'area' => $this->normalizeCenterComponent($area),
                'centro_custo' => $this->normalizeCentroCusto($unicoop, $area),
            ];
        }

        return ['unicoop' => null, 'area' => null, 'centro_custo' => null];
    }

    public function isCristalinaIi(?string $name, ?string $centroCusto = null): bool
    {
        $haystack = mb_strtolower(trim((string) ($name ?? '')) . ' ' . trim((string) ($centroCusto ?? '')));
        $haystack = Str::of($haystack)->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();

        return str_contains($haystack, 'cristalinii');
    }

    public function findLocalForRateio(string $nome, ?string $tipo = null): ?CoretiRateioLocal
    {
        $resolved = $this->resolveCandidate([
            'nome' => $nome,
            'tipo' => $tipo,
        ]);

        return $resolved['local'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveCentroCusto(string $nomeLocal, ?string $tipoLocal = null): ?array
    {
        $resolved = $this->resolveCandidate([
            'nome' => $nomeLocal,
            'tipo' => $tipoLocal,
        ]);
        if (! ($resolved['local'] ?? null) instanceof CoretiRateioLocal) {
            return null;
        }

        /** @var CoretiRateioLocal $local */
        $local = $resolved['local'];

        return [
            'tipo_local' => $local->tipo_local,
            'nome_local' => $local->nome_local,
            'nome_normalizado' => $local->nome_normalizado,
            'unicoop' => $local->unicoop,
            'area' => $local->area,
            'centro_custo' => $local->centro_custo,
            'centro_custo_nome' => $local->centro_custo_nome,
            'observacao' => $local->observacao,
        ];
    }

    /**
     * @param array{nome?:string|null,tipo?:string|null,unicoop?:string|null,area?:string|null,centro_custo?:string|null} $candidate
     * @return array{matched:bool,manual:bool,reason:string,local:?CoretiRateioLocal}
     */
    public function resolveCandidate(array $candidate): array
    {
        $nome = trim((string) ($candidate['nome'] ?? ''));
        $tipo = $this->normalizeTypeLocal($candidate['tipo'] ?? null);
        $unicoop = $this->normalizeCenterComponent($candidate['unicoop'] ?? null);
        $area = $this->normalizeCenterComponent($candidate['area'] ?? null);
        $centroCusto = trim((string) ($candidate['centro_custo'] ?? ''));
        $parsed = $this->parseCentroCusto($centroCusto);
        $normalizedName = $this->normalizeLocalName($nome);

        $centerUnicoop = $parsed['unicoop'] ?? $unicoop;
        $centerArea = $parsed['area'] ?? $area;
        $centerCode = $parsed['centro_custo'] ?? $this->normalizeCentroCusto($unicoop, $area);

        if ($this->isCristalinaIi($nome, $centroCusto) && $centerUnicoop === null && $centerArea === null && $centerCode === null) {
            return [
                'matched' => false,
                'manual' => true,
                'reason' => 'Cristalina II - validação manual',
                'local' => null,
            ];
        }

        $baseQuery = CoretiRateioLocal::query()->where('ativo', true);
        if ($tipo !== null) {
            $baseQuery->where('tipo_local', $tipo);
        }

        if ($normalizedName !== '') {
            $nameMatches = (clone $baseQuery)
                ->where('nome_normalizado', $normalizedName)
                ->get();

            if ($nameMatches->count() === 1) {
                return [
                    'matched' => true,
                    'manual' => false,
                    'reason' => 'Correspondência por nome normalizado',
                    'local' => $nameMatches->first(),
                ];
            }
        }

        if ($centerUnicoop !== null && $centerArea !== null) {
            $codeMatches = (clone $baseQuery)->get()->filter(function (CoretiRateioLocal $local) use ($centerUnicoop, $centerArea): bool {
                return $this->normalizeCenterComponent($local->unicoop) === $centerUnicoop
                    && $this->normalizeCenterComponent($local->area) === $centerArea;
            })->values();

            if ($codeMatches->count() === 1) {
                return [
                    'matched' => true,
                    'manual' => false,
                    'reason' => 'Correspondência única por únicoop/área',
                    'local' => $codeMatches->first(),
                ];
            }

            if ($codeMatches->count() > 1 && $normalizedName !== '') {
                $sameName = $codeMatches->first(function (CoretiRateioLocal $local) use ($normalizedName): bool {
                    return $this->normalizeLocalName($local->nome_local) === $normalizedName
                        || ($local->nome_normalizado ?? '') === $normalizedName;
                });

                if ($sameName instanceof CoretiRateioLocal) {
                    return [
                        'matched' => true,
                        'manual' => false,
                        'reason' => 'Correspondência única por nome + únicoop/área',
                        'local' => $sameName,
                    ];
                }
            }
        }

        if ($centerCode !== null) {
            $codeMatches = (clone $baseQuery)->get()->filter(function (CoretiRateioLocal $local) use ($centerCode): bool {
                return trim((string) $local->centro_custo) === $centerCode;
            })->values();

            if ($codeMatches->count() === 1) {
                return [
                    'matched' => true,
                    'manual' => false,
                    'reason' => 'Correspondência por centro de custo',
                    'local' => $codeMatches->first(),
                ];
            }
        }

        return [
            'matched' => false,
            'manual' => false,
            'reason' => $normalizedName !== '' ? 'Sem correspondência segura' : 'Sem dados suficientes para mapear',
            'local' => null,
        ];
    }
}
