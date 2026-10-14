<?php

namespace App\Services\Bancada;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolve a unidade/setor do equipamento para o campo cascata
 * "Unidade/Departamento" (customfield_10067) do catálogo de manutenção no Jira.
 *
 * As opções (72 unidades, cada uma com seus departamentos) são lidas do próprio
 * Jira e cacheadas, para não depender de uma lista fixa que envelhece. Os nomes
 * que divergem entre o CoreTI e o Jira são resolvidos por `apelidos_unidade`
 * (config). Sem casar a unidade (nível 1), devolve null e o chamado NÃO é aberto.
 */
class ManutencaoUnidadeMapper
{
    private const CACHE_KEY = 'bancada.manutencao.unidade_options';

    /**
     * Resolve o valor do campo cascata para o Jira.
     *
     * @return array{value: array<string, mixed>, unidade: string, departamento: ?string}|null
     */
    public function resolver(?string $unidadeSetor, bool $ehSede): ?array
    {
        $opcoes = $this->opcoes();
        if ($opcoes === []) {
            return null;
        }

        $nome = trim((string) $unidadeSetor);

        if ($ehSede) {
            $pai = $opcoes[$this->norm('Sede')] ?? null;
            if (! $pai) {
                return null;
            }
            // Para a Sede, o "unidade_setor" do equipamento costuma ser o próprio
            // departamento (ex.: Tecnologia da Informação). Tenta casar o filho.
            $filho = $pai['children'][$this->norm($nome)] ?? null;

            return $this->montar($pai, $filho);
        }

        // Unidade não-Sede: aplica apelido e exige casar o nível 1.
        $apelidos = (array) config('bancada.manutencao.apelidos_unidade', []);
        $canonico = $apelidos[$nome] ?? $nome;
        $pai = $opcoes[$this->norm($canonico)] ?? null;
        if (! $pai) {
            return null;
        }

        // Departamento padrão (ex.: Administrativo); se não existir, primeiro filho.
        $padrao = (string) config('bancada.manutencao.departamento_padrao', '');
        $filho = $pai['children'][$this->norm($padrao)] ?? (($pai['children'] ?? []) ? reset($pai['children']) : null);

        return $this->montar($pai, $filho);
    }

    /** @param array{id:string,label:string,children:array<string,array{id:string,label:string}>} $pai */
    private function montar(array $pai, ?array $filho): array
    {
        $value = ['id' => $pai['id']];
        if ($filho) {
            $value['child'] = ['id' => $filho['id']];
        }

        return [
            'value' => $value,
            'unidade' => $pai['label'],
            'departamento' => $filho['label'] ?? null,
        ];
    }

    /**
     * Opções do campo, indexadas por rótulo normalizado.
     *
     * @return array<string, array{id:string,label:string,children:array<string,array{id:string,label:string}>}>
     */
    public function opcoes(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), function (): array {
            $base = rtrim((string) config('services.jira.base_url'), '/');
            $sd = (string) config('backups.jira.service_desk_id');
            $rt = (string) config('bancada.manutencao.jira_request_type');
            $campo = (string) config('bancada.manutencao.campo_unidade');

            if ($base === '' || $sd === '' || $rt === '') {
                return [];
            }

            $resposta = Http::withBasicAuth(
                (string) config('services.jira.email'),
                (string) config('services.jira.api_token')
            )->acceptJson()->withHeaders(['X-ExperimentalApi' => 'opt-in'])->timeout(20)
                ->get("{$base}/rest/servicedeskapi/servicedesk/{$sd}/requesttype/{$rt}/field");

            if (! $resposta->successful()) {
                return [];
            }

            $valores = [];
            foreach ((array) $resposta->json('requestTypeFields', []) as $fld) {
                if (($fld['fieldId'] ?? '') !== $campo) {
                    continue;
                }
                foreach ((array) ($fld['validValues'] ?? []) as $pai) {
                    $filhos = [];
                    foreach ((array) ($pai['children'] ?? []) as $ch) {
                        $filhos[$this->norm((string) ($ch['label'] ?? ''))] = [
                            'id' => (string) ($ch['value'] ?? ''),
                            'label' => (string) ($ch['label'] ?? ''),
                        ];
                    }
                    $valores[$this->norm((string) ($pai['label'] ?? ''))] = [
                        'id' => (string) ($pai['value'] ?? ''),
                        'label' => (string) ($pai['label'] ?? ''),
                        'children' => $filhos,
                    ];
                }
            }

            return $valores;
        });
    }

    /** Esquece o cache das opções (ao trocar apelidos/catálogo). */
    public function limparCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function norm(string $valor): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $valor) ?? ''));
    }
}
