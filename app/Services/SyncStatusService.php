<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Situação das sincronizações do CoreTI, para o painel inicial.
 *
 * As origens não são uniformes, e o painel não finge que são:
 *
 *  - algumas registram status próprio (vCenter em cmdb_sync_logs,
 *    organização em organization_sync_batches);
 *  - outras só carimbam a data da última atualização nos próprios registros
 *    (Active Directory, Google Workspace, Office 365).
 *
 * Para o segundo grupo, o status é derivado da FRESCURA do dado: dentro do
 * prazo esperado é saudável, fora dele é atraso. Inventar "Sucesso" onde a
 * origem não afirma nada seria dar uma garantia que o sistema não tem.
 */
class SyncStatusService
{
    public const OK = 'ok';
    public const ATENCAO = 'atencao';
    public const ERRO = 'erro';
    public const ATRASADO = 'atrasado';
    public const DESCONHECIDO = 'desconhecido';

    /*
     | Módulos que dão direito de ver cada fonte. Espelham o middleware das
     | rotas correspondentes: CMDB usa 'module_access:cmdb,network_datacenter',
     | AD e e-mails vivem sob 'module_access:servicedesk', e assim por diante.
     */
    private const MODULOS_CMDB = ['cmdb', 'network_datacenter'];

    private const MODULOS_ORGANIZACAO = ['coordenacao', 'admissao', 'administrativo'];

    /**
     * Fontes visíveis para o usuário.
     *
     * Cada sincronização é vinculada ao módulo que já protege a tela
     * correspondente — quem não enxerga o CMDB não precisa saber do estado do
     * vCenter. O vínculo espelha o middleware das rotas, e não uma lista à
     * parte, para os dois não divergirem com o tempo.
     *
     * Sem usuário (ou sem nenhum módulo), a lista vem vazia em vez de completa.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function fontes(?User $user = null): Collection
    {
        $user ??= \Illuminate\Support\Facades\Auth::user();

        return $this->todasAsFontes()
            ->filter(fn (array $fonte) => $this->visivelPara($user, $fonte))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function todasAsFontes(): Collection
    {
        return collect([
            $this->vcenter(),
            $this->organizacao(),
            $this->colaboradores(),
            $this->activeDirectory(),
            $this->googleWorkspace(),
            $this->office365(),
            $this->auditoriaBackups(),
        ])->filter()->values();
    }

    /**
     * O usuário enxerga a fonte se tiver QUALQUER um dos módulos ligados a ela.
     */
    private function visivelPara(?User $user, array $fonte): bool
    {
        if ($user === null) {
            return false;
        }

        $modulos = $fonte['modulos'] ?? [];

        return $modulos === [] || $user->canAnyModule($modulos);
    }

    // ------------------------------------------------- origens com status

    /**
     * vCenter: o único conector com log próprio hoje.
     *
     * O conector manda todo item para a fila de revisão — é assim que o ativo
     * nasce por aprovação, e não sozinho. Isso não é aviso: o que merece
     * destaque é item sem correspondência ou falha, e a contagem do que está
     * esperando alguém olhar.
     */
    private function vcenter(): ?array
    {
        if (! Schema::hasTable('cmdb_sync_logs')) {
            return null;
        }

        $log = DB::table('cmdb_sync_logs')->orderByDesc('id')->first();

        if (! $log) {
            return $this->fonte('vcenter', 'Sincronização vCenter', null, self::DESCONHECIDO, 'Nunca executada.',
                modulos: self::MODULOS_CMDB);
        }

        $status = match (mb_strtolower((string) $log->status)) {
            'success', 'ok', 'completed' => self::OK,
            'warning' => self::ATENCAO,
            default => self::ERRO,
        };

        return $this->fonte(
            'vcenter',
            'Sincronização vCenter',
            $log->finished_at ?? $log->started_at,
            $status,
            $log->error_message ?: ($log->message ?: null),
            'cmdb.sync-logs.index',
            $this->numeros([
                'encontrados' => $log->assets_found ?? null,
                'criados' => $log->assets_created ?? null,
                'atualizados' => $log->assets_updated ?? null,
                'aguardando revisão' => $this->descobertasPendentes(),
            ]),
            modulos: self::MODULOS_CMDB
        );
    }

    /**
     * Descobertas esperando decisão humana.
     *
     * Vai no painel como número, não como alerta: são itens em fila normal de
     * trabalho, não sintoma de sincronização com problema.
     */
    private function descobertasPendentes(): ?int
    {
        if (! Schema::hasTable('cmdb_discovered_assets')) {
            return null;
        }

        $referencia = \App\Services\CmdbDiscoveryQueryService::tiposDeReferencia();

        return DB::table('cmdb_discovered_assets')
            ->whereIn('status', ['pending', 'suggested', 'reopened'])
            ->when($referencia !== [], fn ($q) => $q->whereNotIn('source_type', $referencia))
            ->count() ?: null;
    }

    private function organizacao(): ?array
    {
        if (! Schema::hasTable('organization_sync_batches')) {
            return null;
        }

        $lote = DB::table('organization_sync_batches')->orderByDesc('id')->first();

        if (! $lote) {
            return $this->fonte('organizacao', 'Sincronização Organização', null, self::DESCONHECIDO, 'Nunca executada.',
                modulos: self::MODULOS_ORGANIZACAO);
        }

        $status = match (mb_strtolower((string) $lote->status)) {
            'success', 'completed' => self::OK,
            'partial', 'warning' => self::ATENCAO,
            'running', 'pending' => self::ATENCAO,
            default => self::ERRO,
        };

        return $this->fonte(
            'organizacao',
            'Sincronização Organização',
            $lote->finished_at ?? $lote->started_at,
            $status,
            $lote->error_message ?: null,
            null,
            $this->numeros([
                'unidades' => $lote->units_found ?? null,
                'departamentos' => $lote->departments_found ?? null,
            ]),
            modulos: self::MODULOS_ORGANIZACAO
        );
    }

    // -------------------------------------- origens só com data (frescura)

    private function colaboradores(): ?array
    {
        return $this->porFrescura(
            'colaboradores',
            'Sincronização Colaboradores (RH)',
            'hr_collaborators',
            'last_synced_at',
            26,
            modulos: self::MODULOS_ORGANIZACAO
        );
    }

    private function activeDirectory(): ?array
    {
        return $this->porFrescura(
            'ad',
            'Sincronização Active Directory',
            'ad_users',
            'last_sync',
            26,
            modulos: ['servicedesk']
        );
    }

    private function googleWorkspace(): ?array
    {
        return $this->porFrescura(
            'google',
            'Sincronização E-mails (Google Workspace)',
            'coreti_google_emails',
            'importado_em',
            26,
            modulos: ['servicedesk']
        );
    }

    private function office365(): ?array
    {
        return $this->porFrescura(
            'office',
            'Sincronização Office 365',
            'office_licenses',
            'updated_at',
            26,
            // Tela em ServiceDesk → Office; o rateio vive no Administrativo.
            modulos: ['servicedesk', 'administrativo']
        );
    }

    private function auditoriaBackups(): ?array
    {
        return $this->porFrescura(
            'backups',
            'Auditoria de Backups',
            'cmdb_database_backups',
            'received_at',
            26,
            'cmdb.database-backups.index',
            self::MODULOS_CMDB
        );
    }

    /**
     * Status derivado da idade do dado mais recente.
     */
    private function porFrescura(
        string $chave,
        string $rotulo,
        string $tabela,
        string $coluna,
        int $horasEsperadas,
        ?string $rota = null,
        array $modulos = [],
    ): ?array {
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
            return null;
        }

        $ultimo = DB::table($tabela)->max($coluna);

        if (! $ultimo) {
            return $this->fonte($chave, $rotulo, null, self::DESCONHECIDO,
                'Nenhum registro sincronizado ainda.', $rota, modulos: $modulos);
        }

        $quando = Carbon::parse($ultimo);
        $atrasado = $quando->lessThan(now()->subHours($horasEsperadas));

        return $this->fonte(
            $chave,
            $rotulo,
            $quando,
            $atrasado ? self::ATRASADO : self::OK,
            $atrasado
                ? sprintf('Sem atualização há mais de %dh.', $horasEsperadas)
                : null,
            $rota,
            null,
            false,
            $modulos
        );
    }

    // ------------------------------------------------------------ auxiliar

    private function fonte(
        string $chave,
        string $rotulo,
        Carbon|string|null $quando,
        string $status,
        ?string $detalhe = null,
        ?string $rota = null,
        ?string $numeros = null,
        bool $statusProprio = true,
        array $modulos = [],
    ): array {
        $quando = $quando ? Carbon::parse($quando) : null;

        return [
            'chave' => $chave,
            'rotulo' => $rotulo,
            'status' => $status,
            'rotulo_status' => self::rotuloStatus($status),
            'executado_em' => $quando,
            'ha_quanto_tempo' => $quando?->diffForHumans(),
            'detalhe' => $detalhe,
            'numeros' => $numeros,
            'rota' => $rota && \Illuminate\Support\Facades\Route::has($rota) ? route($rota) : null,
            // Falso quando a origem só informa a data, e o status é inferido.
            'status_proprio' => $statusProprio,
            'modulos' => $modulos,
        ];
    }

    private function numeros(array $valores): ?string
    {
        $partes = [];

        foreach ($valores as $rotulo => $valor) {
            if ($valor !== null) {
                $partes[] = $valor.' '.$rotulo;
            }
        }

        return $partes === [] ? null : implode(' · ', $partes);
    }

    public static function rotuloStatus(string $status): string
    {
        return match ($status) {
            self::OK => 'Sucesso',
            self::ATENCAO => 'Atenção',
            self::ERRO => 'Erro',
            self::ATRASADO => 'Atrasado',
            default => 'Sem informação',
        };
    }

    /**
     * Classes completas — o Tailwind não gera nome de classe montado por
     * interpolação, então nada de "bg-{$cor}-100" aqui.
     */
    public static function classes(string $status): array
    {
        return match ($status) {
            self::OK => ['ponto' => 'bg-emerald-500', 'selo' => 'bg-emerald-100 text-emerald-800', 'borda' => 'border-emerald-200'],
            self::ATENCAO => ['ponto' => 'bg-amber-500', 'selo' => 'bg-amber-100 text-amber-800', 'borda' => 'border-amber-200'],
            self::ERRO => ['ponto' => 'bg-rose-500', 'selo' => 'bg-rose-100 text-rose-800', 'borda' => 'border-rose-200'],
            self::ATRASADO => ['ponto' => 'bg-orange-500', 'selo' => 'bg-orange-100 text-orange-800', 'borda' => 'border-orange-200'],
            default => ['ponto' => 'bg-slate-400', 'selo' => 'bg-slate-100 text-slate-700', 'borda' => 'border-slate-200'],
        };
    }
}
