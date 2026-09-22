<?php

namespace App\Services\Backups;

use App\Models\CmdbAsset;
use App\Models\CmdbDatabaseBackup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Recebe o evento de backup do agente e o transforma em registro auditável.
 *
 * Substitui os nós "Converte Data e Hora" e "Grava o log do Backup" do n8n,
 * acrescentando deduplicação e vínculo com o ativo do CMDB.
 */
class DatabaseBackupIngestService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{registro: CmdbDatabaseBackup, duplicado: bool}
     */
    public function ingest(array $payload): array
    {
        $dados = $this->normalizar($payload);

        $existente = CmdbDatabaseBackup::where('fingerprint', $dados['fingerprint'])->first();

        if ($existente !== null) {
            // Reenvio do agente: não duplica linha nem gera segundo chamado.
            return ['registro' => $existente, 'duplicado' => true];
        }

        $registro = DB::transaction(function () use ($dados): CmdbDatabaseBackup {
            return CmdbDatabaseBackup::create($dados);
        });

        return ['registro' => $registro, 'duplicado' => false];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizar(array $payload): array
    {
        $host = trim((string) ($payload['host'] ?? ''));
        $database = trim((string) ($payload['database'] ?? ''));
        $status = trim((string) ($payload['status'] ?? ''));

        $inicio = $this->paraDataHora($payload['inicio'] ?? null);
        $fim = $this->paraDataHora($payload['fim'] ?? null);

        $ativo = $this->resolverAtivo($host);

        return [
            'host' => $host,
            'database_name' => $database,
            'backup_type' => $this->ouNulo($payload['backup_type'] ?? null),
            'status' => $status,
            'sucesso' => $this->ehSucesso($status),
            'ip_address' => $this->ouNulo($payload['ip'] ?? null),
            'arquivo' => $this->ouNulo($payload['arquivo'] ?? null),
            'tamanho' => isset($payload['tamanho']) && is_numeric($payload['tamanho'])
                ? (float) $payload['tamanho']
                : null,
            'observacao' => $this->ouNulo($payload['observacao'] ?? null),
            'inicio_backup' => $inicio,
            'fim_backup' => $fim,
            'data_backup' => $this->paraDataHora($payload['data_geracao'] ?? null),
            'data_verificacao' => $this->paraDataHora($payload['data_verificacao'] ?? null),
            'duracao_segundos' => $inicio && $fim && $fim->greaterThanOrEqualTo($inicio)
                ? $inicio->diffInSeconds($fim)
                : null,
            'asset_id' => $ativo['id'],
            'asset_match_method' => $ativo['metodo'],
            'payload' => $payload,
            'fingerprint' => $this->fingerprint($payload),
            'received_at' => now(),
        ];
    }

    /**
     * O agente envia timestamps Unix; aceita também data já formatada, para
     * o importador do histórico reaproveitar este método.
     */
    private function paraDataHora(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            $timestamp = (int) $valor;

            // Timestamp zerado ou absurdo indica campo não preenchido.
            if ($timestamp <= 0) {
                return null;
            }

            return Carbon::createFromTimestamp($timestamp);
        }

        try {
            return Carbon::parse((string) $valor);
        } catch (\Throwable) {
            return null;
        }
    }

    public function ehSucesso(string $status): bool
    {
        $aceitos = array_map('mb_strtoupper', (array) config('backups.status_ok', ['OK']));

        return in_array(mb_strtoupper(trim($status)), $aceitos, true);
    }

    /**
     * Chave de deduplicação: mesma execução reenviada gera o mesmo hash.
     *
     * Usa os campos que identificam a execução, não o payload inteiro — assim
     * uma diferença irrelevante (ex.: observação reescrita) não cria duplicata.
     */
    private function fingerprint(array $payload): string
    {
        return hash('sha256', implode('|', [
            mb_strtolower(trim((string) ($payload['host'] ?? ''))),
            mb_strtolower(trim((string) ($payload['database'] ?? ''))),
            trim((string) ($payload['backup_type'] ?? '')),
            (string) ($payload['data_geracao'] ?? ''),
            (string) ($payload['inicio'] ?? ''),
            (string) ($payload['fim'] ?? ''),
            trim((string) ($payload['status'] ?? '')),
        ]));
    }

    /**
     * Liga o evento ao ativo do CMDB pelo hostname — é o que faz o backup
     * aparecer na ficha do servidor em vez de virar log solto.
     *
     * @return array{id: ?int, metodo: ?string}
     */
    private function resolverAtivo(string $host): array
    {
        if ($host === '' || ! Schema::hasTable('cmdb_assets')) {
            return ['id' => null, 'metodo' => null];
        }

        $normalizado = mb_strtolower($host);
        $semDominio = Str::before($normalizado, '.');

        $colunas = collect(['hostname', 'name'])
            ->filter(fn (string $coluna) => Schema::hasColumn('cmdb_assets', $coluna))
            ->values();

        foreach ($colunas as $coluna) {
            // 1) Nome do ativo idêntico ao host recebido.
            $id = CmdbAsset::query()
                ->whereRaw("LOWER({$coluna}) = ?", [$normalizado])
                ->value('id');

            if ($id) {
                return ['id' => (int) $id, 'metodo' => $coluna];
            }

            // 2) Host veio com domínio e o ativo está cadastrado sem ele.
            if ($semDominio !== $normalizado) {
                $id = CmdbAsset::query()
                    ->whereRaw("LOWER({$coluna}) = ?", [$semDominio])
                    ->value('id');

                if ($id) {
                    return ['id' => (int) $id, 'metodo' => $coluna.'_sem_dominio'];
                }
            }

            // 3) Caso inverso: ativo cadastrado com FQDN, host veio curto.
            // LIKE em vez de SUBSTRING_INDEX para funcionar fora do MySQL.
            $id = CmdbAsset::query()
                ->whereRaw("LOWER({$coluna}) LIKE ?", [$semDominio.'.%'])
                ->value('id');

            if ($id) {
                return ['id' => (int) $id, 'metodo' => $coluna.'_fqdn'];
            }
        }

        return ['id' => null, 'metodo' => null];
    }

    private function ouNulo(mixed $valor): ?string
    {
        $valor = is_scalar($valor) ? trim((string) $valor) : null;

        return ($valor === null || $valor === '') ? null : $valor;
    }
}
