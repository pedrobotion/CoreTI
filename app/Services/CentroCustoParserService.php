<?php

namespace App\Services;

class CentroCustoParserService
{
    /**
     * @return array{
     *     centro_custo:?string,
     *     centro_custo_original:?string,
     *     unicoop:null,
     *     area:null,
     *     valid:bool,
     *     error:?string
     * }
     */
    public function parseCentroCusto(?string $centroCusto): array
    {
        $original = trim((string) $centroCusto);

        if ($original === '' || $original === '\\N') {
            return $this->invalid($original, 'CentroCusto vazio ou ausente.');
        }

        if ($this->isZeroLike($original)) {
            return $this->invalid($original, 'CentroCusto vazio ou zerado.');
        }

        return [
            'centro_custo' => $original,
            'centro_custo_original' => $original,
            'unicoop' => null,
            'area' => null,
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     centro_custo:?string,
     *     centro_custo_original:?string,
     *     unicoop:null,
     *     area:null,
     *     valid:false,
     *     error:string
     * }
     */
    private function invalid(?string $original, string $message): array
    {
        $value = trim((string) $original);

        return [
            'centro_custo' => $value !== '' ? $value : null,
            'centro_custo_original' => $value !== '' ? $value : null,
            'unicoop' => null,
            'area' => null,
            'valid' => false,
            'error' => $message,
        ];
    }

    private function isZeroLike(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits !== '' && preg_match('/^0+$/', $digits) === 1;
    }
}
