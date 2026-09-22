<?php

namespace App\Services;

use App\Models\AdUser;
use App\Models\CoretiCostCenter;

class AdUserCostCenterResolverService
{
    /**
     * Resolve the cost center explicitly assigned to an AD user.
     * A null result means the caller must keep its existing fallback strategy.
     *
     * @return array{found:bool,ambiguous:bool,code:?string,name:?string,source:string,message:?string}
     */
    public function resolve(?string $matricula): ?array
    {
        $matricula = trim((string) $matricula);
        if ($matricula === '') {
            return null;
        }

        $users = AdUser::query()
            ->where('matricula', $matricula)
            ->where(function ($query): void {
                $query->where('e_ativo', true)->orWhereNull('e_ativo');
            })
            ->get(['id', 'matricula', 'centro_custo']);

        if ($users->count() !== 1) {
            if ($users->isEmpty()) {
                return null;
            }

            return [
                'found' => false,
                'ambiguous' => true,
                'code' => null,
                'name' => null,
                'source' => 'ad_users',
                'message' => 'Mais de um usuário ativo possui a mesma matrícula; fallback mantido.',
            ];
        }

        $rawCode = trim((string) $users->first()->centro_custo);
        if ($rawCode === '') {
            return null;
        }

        $code = $this->normalizeCode($rawCode);
        $center = CoretiCostCenter::query()
            ->active()
            ->where(function ($query) use ($rawCode, $code): void {
                $query->where('code', $rawCode)->orWhere('code', $code);
            })
            ->first();

        return [
            'found' => true,
            'ambiguous' => false,
            'code' => $code,
            'name' => $center?->name,
            'source' => 'ad_users',
            'message' => $center === null
                ? 'Centro de custo informado no AD ainda não foi cadastrado no catálogo do CoreTI.'
                : null,
        ];
    }

    private function normalizeCode(string $value): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $value, $matches) === 1) {
            return str_pad(ltrim($matches[1], '0') ?: '0', 2, '0', STR_PAD_LEFT)
                . '.'
                . str_pad(ltrim($matches[2], '0') ?: '0', 3, '0', STR_PAD_LEFT);
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) <= 3) {
            return $value;
        }

        return str_pad(ltrim(substr($digits, 0, -3), '0') ?: '0', 2, '0', STR_PAD_LEFT)
            . '.'
            . str_pad(ltrim(substr($digits, -3), '0') ?: '0', 3, '0', STR_PAD_LEFT);
    }
}
