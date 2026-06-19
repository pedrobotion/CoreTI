<?php

namespace App\Services;

use App\Models\NetworkServicoConectividade;
use Illuminate\Support\Facades\Storage;

class NetworkFichaIaExtractionService
{
    public function extract(NetworkServicoConectividade $servico): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'nao_configurada',
                'message' => 'Extração automática não configurada. Preencha manualmente.',
                'resultado' => null,
            ];
        }

        if (! $servico->ficha_storage_path || ! Storage::disk('local')->exists($servico->ficha_storage_path)) {
            return [
                'status' => 'erro',
                'message' => 'Anexe a ficha PDF antes de solicitar a extração.',
                'resultado' => null,
            ];
        }

        return [
            'status' => 'nao_configurada',
            'message' => 'Extração automática ainda não foi habilitada nesta instalação.',
            'resultado' => null,
        ];
    }

    private function isConfigured(): bool
    {
        return filter_var((string) env('NETWORK_DATACENTER_AI_ENABLED', false), FILTER_VALIDATE_BOOL)
            || filled(env('OPENAI_API_KEY'));
    }
}
