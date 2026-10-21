<?php

namespace App\Services;

use App\Models\PrintCollectorStatus;
use App\Models\User;
use App\Services\Insight\InsightApiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    private const MODULOS_ORGANIZACAO = ['coordenacao', 'administrativo'];

    private const MODULOS_IMPRESSORAS = ['impressoras'];

    /** Maior intervalo entre execuções (5h30) mais folga. */
    private const HORAS_ESPERADAS_IMPRESSORAS = 8;

    /**
     * Silêncio tolerado de um coletor de impressão.
     *
     * A tarefa roda de hora em hora. Seis horas cobrem reinício de servidor e
     * janela de manutenção sem alarme falso, e ainda deixam muita margem
     * antes do prazo que importa: o log do Windows retém cerca de seis DIAS,
     * e coleta parada além disso vira perda definitiva.
     */
    private const HORAS_ESPERADAS_COLETOR = 6;

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
            $this->impressoras(),
            $this->logDeImpressao(),
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
    /**
     * Impressoras: frescura do dado mais o teto da API de origem.
     *
     * A EKM Insight é de terceiro e tem teto diário nosso. Se o teto estourar,
     * as execuções agendadas passam a falhar sem que nada mude na base — o
     * dado simplesmente envelhece. Olhar só a data levaria horas para acusar
     * isso, e a causa não apareceria. Por isso o teto entra aqui.
     */
    private function impressoras(): ?array
    {
        if (! Schema::hasTable('printers')) {
            return null;
        }

        $ultimo = DB::table('printers')->max('last_synced_at');

        $numeros = $this->numeros([
            'impressoras' => DB::table('printers')->count() ?: null,
            'consumíveis' => Schema::hasTable('printer_supplies')
                ? (DB::table('printer_supplies')->count() ?: null)
                : null,
        ]);

        if (! $ultimo) {
            return $this->fonte('impressoras', 'Sincronização Impressoras', null, self::DESCONHECIDO,
                'Nenhuma sincronização executada ainda.', 'impressoras.index', $numeros,
                modulos: self::MODULOS_IMPRESSORAS);
        }

        $consumo = app(InsightApiClient::class)->consumoDeHoje();

        if ($consumo['restantes'] <= 0) {
            return $this->fonte('impressoras', 'Sincronização Impressoras', $ultimo, self::ERRO,
                sprintf('Teto diário de %d chamadas à Insight API atingido: as próximas execuções não vão rodar hoje.',
                    $consumo['teto']),
                'impressoras.index', $numeros, modulos: self::MODULOS_IMPRESSORAS);
        }

        $quando = Carbon::parse($ultimo);

        /*
         | São 4 execuções por dia e o maior intervalo entre elas é de 5h30
         | (07:00 → 12:30). A folga até 8h evita alarme falso logo depois do
         | intervalo normal, e ainda acusa uma execução perdida.
         */
        if ($quando->lessThan(now()->subHours(self::HORAS_ESPERADAS_IMPRESSORAS))) {
            return $this->fonte('impressoras', 'Sincronização Impressoras', $quando, self::ATRASADO,
                sprintf('Sem atualização há mais de %dh — alguma execução agendada pode ter falhado.',
                    self::HORAS_ESPERADAS_IMPRESSORAS),
                'impressoras.index', $numeros, modulos: self::MODULOS_IMPRESSORAS);
        }

        return $this->fonte('impressoras', 'Sincronização Impressoras', $quando, self::OK,
            sprintf('%d de %d chamadas à Insight API usadas hoje.', $consumo['usadas'], $consumo['teto']),
            'impressoras.index', $numeros, modulos: self::MODULOS_IMPRESSORAS);
    }

    /**
     * Coletores de log de impressão dos servidores Windows.
     *
     * Olha o último CONTATO, não o último trabalho impresso: domingo sem
     * impressão nenhuma é normal, coletor parado não é, e só o contato
     * separa os dois.
     */
    private function logDeImpressao(): ?array
    {
        if (! Schema::hasTable('print_collector_status')) {
            return null;
        }

        $esperados = collect(config('impressao.servidores', []))
            ->map(fn ($s): string => mb_strtoupper(trim((string) $s)))
            ->filter();

        if ($esperados->isEmpty()) {
            return null;
        }

        $contatos = PrintCollectorStatus::query()->get()->keyBy('servidor');
        $limite = now()->subHours(self::HORAS_ESPERADAS_COLETOR);

        $mudos = $esperados->filter(function (string $nome) use ($contatos, $limite): bool {
            $contato = $contatos->get($nome);

            return $contato === null || $contato->ultimo_contato_em->lessThan($limite);
        })->values();

        $ultimo = $contatos->max('ultimo_contato_em');

        $numeros = $this->numeros([
            'servidores reportando' => $esperados->count() - $mudos->count(),
            'trabalhos' => Schema::hasTable('print_jobs')
                ? (DB::table('print_jobs')->count() ?: null)
                : null,
        ]);

        if ($contatos->isEmpty()) {
            return $this->fonte('impressao_log', 'Coleta de Impressões', null, self::DESCONHECIDO,
                'Nenhum servidor enviou ainda. Configure a tarefa agendada em '.$esperados->implode(', ').'.',
                'dashboards.impressoes', $numeros, modulos: self::MODULOS_IMPRESSORAS);
        }

        if ($mudos->isNotEmpty()) {
            return $this->fonte('impressao_log', 'Coleta de Impressões', $ultimo, self::ERRO,
                sprintf(
                    'Sem contato de %s há mais de %dh. O log do Windows retém poucos dias: coleta parada vira histórico perdido.',
                    $mudos->implode(', '),
                    self::HORAS_ESPERADAS_COLETOR
                ),
                'dashboards.impressoes', $numeros, modulos: self::MODULOS_IMPRESSORAS);
        }

        return $this->fonte('impressao_log', 'Coleta de Impressões', $ultimo, self::OK,
            null, 'dashboards.impressoes', $numeros, modulos: self::MODULOS_IMPRESSORAS);
    }

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
                // Separador de milhar: "2.265 consumíveis" se lê, "2265" não.
                $partes[] = (is_int($valor) ? number_format($valor, 0, ',', '.') : $valor).' '.$rotulo;
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
