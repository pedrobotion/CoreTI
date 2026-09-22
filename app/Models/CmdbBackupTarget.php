<?php

namespace App\Models;

use App\Support\Backups\BackupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Backup que o CoreTI espera encontrar — a fonte da verdade da auditoria.
 *
 * O alvo existe por cadastro, não por evento recebido: se nenhum agente,
 * webhook ou n8n existir, a auditoria continua sabendo o que verificar.
 */
class CmdbBackupTarget extends Model
{
    protected $table = 'cmdb_backup_targets';

    public const PERIODICIDADES = [
        'horaria' => 'A cada hora',
        'diaria' => 'Diária',
        'semanal' => 'Semanal',
        'mensal' => 'Mensal',
    ];

    /** Quantos checks saudáveis entram na média do tamanho esperado. */
    public const AMOSTRA_TAMANHO_HISTORICO = 5;

    public const TIPOS_BACKUP = [
        'progress' => 'Progress / OpenEdge',
        'mysql' => 'MySQL / MariaDB',
        'postgres' => 'PostgreSQL',
        'sqlserver' => 'SQL Server',
        'arquivo' => 'Arquivo / diretório',
        'outro' => 'Outro',
    ];

    protected $fillable = [
        'chave', 'nome',
        'host', 'database_name', 'arquivo', 'log_path',
        'tipo_backup', 'metodo_verificacao',
        'periodicidade', 'horario_esperado', 'tolerancia_horas',
        'ssh_host', 'ssh_user', 'ssh_port',
        'min_tamanho_bytes', 'estrategia_tamanho', 'max_idade_horas',
        'ativo', 'origem_cadastro', 'observacao',
        'last_checked_at', 'last_status', 'asset_id',
        'ultima_janela_auditada', 'monitorar_a_partir_de',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ssh_port' => 'integer',
            'tolerancia_horas' => 'integer',
            'min_tamanho_bytes' => 'integer',
            'max_idade_horas' => 'integer',
            'last_checked_at' => 'datetime',
            'ultima_janela_auditada' => 'datetime',
            'monitorar_a_partir_de' => 'datetime',
        ];
    }

    /**
     * A chave de unicidade é derivada, nunca informada à mão.
     */
    protected static function booted(): void
    {
        static::saving(function (self $target): void {
            $target->chave = hash('sha256', implode('|', [
                mb_strtolower(trim((string) $target->host)),
                mb_strtolower(trim((string) $target->database_name)),
                trim((string) $target->arquivo),
            ]));
        });
    }

    public function checks(): HasMany
    {
        return $this->hasMany(CmdbBackupCheck::class, 'target_id')->latest('executado_em');
    }

    public function ultimoCheck(): HasOne
    {
        return $this->hasOne(CmdbBackupCheck::class, 'target_id')->latestOfMany('executado_em');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(CmdbBackupIncident::class, 'target_id')->latest('aberto_em');
    }

    public function incidenteAberto(): HasOne
    {
        return $this->hasOne(CmdbBackupIncident::class, 'target_id')
            ->where('status', CmdbBackupIncident::STATUS_ABERTO)
            ->latestOfMany('aberto_em');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function nomeExibicao(): string
    {
        return $this->nome ?: ($this->host.' / '.$this->database_name);
    }

    public function tipoLabel(): string
    {
        return self::TIPOS_BACKUP[$this->tipo_backup] ?? ($this->tipo_backup ?: '-');
    }

    public function periodicidadeLabel(): string
    {
        return self::PERIODICIDADES[$this->periodicidade] ?? ($this->periodicidade ?: '-');
    }

    public function statusLabel(): string
    {
        return BackupStatus::rotulo($this->last_status);
    }

    public function statusTom(): string
    {
        return BackupStatus::tom($this->last_status);
    }

    // ---------------------------------------------------------------- conexão

    public function enderecoSsh(): string
    {
        return trim((string) ($this->ssh_host ?: $this->host));
    }

    public function usuarioSsh(): string
    {
        return trim((string) ($this->ssh_user ?: config('backups.ssh.user', 'coreti-monitor')));
    }

    public function portaSsh(): int
    {
        return (int) ($this->ssh_port ?: config('backups.ssh.port', 22));
    }

    // ----------------------------------------------------------------- limites

    public function idadeMaximaHoras(): int
    {
        return (int) ($this->max_idade_horas ?: config('backups.stale_after_hours', 36));
    }

    /**
     * Tamanho mínimo aceitável, em bytes.
     *
     * Manual sempre prevalece. No modo automático, deriva do histórico de
     * verificações válidas deste alvo — não de eventos de agente, que podem
     * não existir.
     */
    public function tamanhoMinimoBytes(): ?int
    {
        if ($this->estrategia_tamanho === 'manual') {
            return $this->min_tamanho_bytes ?: null;
        }

        if ($this->min_tamanho_bytes) {
            return $this->min_tamanho_bytes;
        }

        return $this->tamanhoMinimoHistorico();
    }

    /**
     * Média dos últimos backups observados como saudáveis, multiplicada pela
     * fração configurada.
     *
     * Ponto de evolução: hoje é média × fração. A estrutura já isola o cálculo
     * para que uma detecção por variação histórica (desvio padrão, tendência)
     * substitua apenas este método.
     */
    public function tamanhoMinimoHistorico(): ?int
    {
        /*
         | LIMIT com agregação no SQL não faz o que parece: o banco calcula
         | AVG sobre TODAS as linhas e só então aplica o LIMIT ao resultado
         | (uma única linha). O histórico inteiro entrava na conta.
         |
         | Aqui os cinco tamanhos mais recentes são carregados e a média é
         | feita sobre eles, explicitamente.
         */
        $tamanhos = $this->checks()
            ->where('status', BackupStatus::OK)
            ->whereNotNull('arquivo_tamanho_bytes')
            ->where('arquivo_tamanho_bytes', '>', 0)
            ->orderByDesc('executado_em')
            ->orderByDesc('id')
            ->limit(self::AMOSTRA_TAMANHO_HISTORICO)
            ->pluck('arquivo_tamanho_bytes');

        if ($tamanhos->isEmpty()) {
            return null;
        }

        $media = $tamanhos->avg();

        $fracao = (float) config('backups.ssh.fracao_tamanho_minimo', 0.5);

        return (int) round(((float) $media) * $fracao);
    }

    public function origemTamanhoMinimo(): string
    {
        if ($this->estrategia_tamanho === 'manual' || $this->min_tamanho_bytes) {
            return 'manual';
        }

        return $this->tamanhoMinimoHistorico() !== null ? 'histórico' : 'sem referência';
    }

    // -------------------------------------------------------------- agendamento

    /*
     | Três conceitos distintos, deliberadamente não intercambiáveis:
     |
     | horario_esperado + tolerancia_horas
     |     Definem a JANELA: quando a execução daquele período já deveria ter
     |     terminado. Antes do fim da janela, nada é cobrado — um backup das
     |     02:00 não está atrasado à 01:00.
     |
     | max_idade_horas
     |     Proteção sobre a IDADE REAL do artefato encontrado, independente da
     |     janela. Pega o caso "a janela de hoje fechou, existe arquivo, mas
     |     ele é de anteontem".
     |
     | ultima_janela_auditada
     |     Qual janela o SCHEDULER já cobriu. Verificação manual não mexe aqui,
     |     por isso não desloca o ciclo automático.
     */

    /**
     * Âncora do monitoramento: janelas anteriores a esta data são ignoradas,
     * o que evita acusar atraso de um período anterior ao cadastro do alvo.
     */
    public function ancoraDeMonitoramento(): Carbon
    {
        return ($this->monitorar_a_partir_de ?? $this->created_at ?? now())->copy();
    }

    /**
     * Fim da janela esperada mais recente que JÁ FECHOU.
     *
     * Null quando ainda não fechou nenhuma janela desde o início do
     * monitoramento — é o caso do alvo recém-cadastrado.
     */
    public function fimDaJanelaMaisRecente(?Carbon $agora = null): ?Carbon
    {
        $agora ??= now();

        if (! $this->horario_esperado) {
            return null;
        }

        [$hora, $minuto] = array_pad(explode(':', (string) $this->horario_esperado), 2, '0');
        $hora = (int) $hora;
        $minuto = (int) $minuto;
        $tolerancia = (int) ($this->tolerancia_horas ?? 0);

        $ocorrencia = $this->ultimaOcorrenciaEsperada($agora, $hora, $minuto);

        // Se a janela dessa ocorrência ainda não fechou, a relevante é a anterior.
        if ($ocorrencia->copy()->addHours($tolerancia)->greaterThan($agora)) {
            $ocorrencia = $this->recuarUmPeriodo($ocorrencia);
        }

        $fim = $ocorrencia->addHours($tolerancia);

        return $fim->lessThan($this->ancoraDeMonitoramento()) ? null : $fim;
    }

    /**
     * Última ocorrência esperada do backup, conforme a periodicidade.
     *
     * Semanal e mensal se ancoram na data de início do monitoramento, para que
     * "toda semana" e "todo mês" tenham um dia definido em vez de implícito.
     */
    private function ultimaOcorrenciaEsperada(Carbon $agora, int $hora, int $minuto): Carbon
    {
        $ancora = $this->ancoraDeMonitoramento();

        if ($this->periodicidade === 'horaria') {
            // Na periodicidade horária só o minuto do horário esperado importa.
            $ocorrencia = $agora->copy()->minute($minuto)->second(0);

            return $ocorrencia->greaterThan($agora) ? $ocorrencia->subHour() : $ocorrencia;
        }

        if ($this->periodicidade === 'semanal') {
            $ocorrencia = $agora->copy()->setTime($hora, $minuto)
                ->startOfDay()->addHours($hora)->addMinutes($minuto);
            // Alinha ao mesmo dia da semana da âncora.
            while ($ocorrencia->dayOfWeek !== $ancora->dayOfWeek || $ocorrencia->greaterThan($agora)) {
                $ocorrencia->subDay();
            }

            return $ocorrencia;
        }

        if ($this->periodicidade === 'mensal') {
            $dia = min((int) $ancora->day, (int) $agora->copy()->endOfMonth()->day);
            $ocorrencia = $agora->copy()->day($dia)->setTime($hora, $minuto);

            if ($ocorrencia->greaterThan($agora)) {
                $anterior = $agora->copy()->subMonthNoOverflow();
                $dia = min((int) $ancora->day, (int) $anterior->copy()->endOfMonth()->day);
                $ocorrencia = $anterior->day($dia)->setTime($hora, $minuto);
            }

            return $ocorrencia;
        }

        // Diária (padrão).
        $ocorrencia = $agora->copy()->setTime($hora, $minuto);

        return $ocorrencia->greaterThan($agora) ? $ocorrencia->subDay() : $ocorrencia;
    }

    private function recuarUmPeriodo(Carbon $ocorrencia): Carbon
    {
        return match ($this->periodicidade) {
            'horaria' => $ocorrencia->copy()->subHour(),
            'semanal' => $ocorrencia->copy()->subWeek(),
            'mensal' => $ocorrencia->copy()->subMonthNoOverflow(),
            default => $ocorrencia->copy()->subDay(),
        };
    }

    /**
     * O scheduler deve auditar este alvo agora?
     *
     * Verificação manual nunca passa por aqui — ela é sempre permitida.
     */
    public function deveVerificarAgora(?Carbon $agora = null): bool
    {
        $agora ??= now();

        if (! $this->ativo) {
            return false;
        }

        if ($this->monitorar_a_partir_de !== null && $this->monitorar_a_partir_de->greaterThan($agora)) {
            return false;
        }

        // Problema em aberto é reavaliado antes da periodicidade normal, para
        // que a recuperação seja detectada rápido.
        if (BackupStatus::ehProblema($this->last_status)) {
            return $this->last_checked_at === null
                || $this->last_checked_at->lessThanOrEqualTo(
                    $agora->copy()->subMinutes((int) config('backups.reverificacao_problema_minutos', 55))
                );
        }

        if ($this->horario_esperado) {
            $fimDaJanela = $this->fimDaJanelaMaisRecente($agora);

            // Nenhuma janela fechou ainda desde o início do monitoramento.
            if ($fimDaJanela === null) {
                return false;
            }

            return $this->ultima_janela_auditada === null
                || $this->ultima_janela_auditada->lessThan($fimDaJanela);
        }

        // Sem horário esperado, cai no intervalo simples — baseado na janela
        // auditada, não em last_checked_at, para o manual não deslocar o ciclo.
        $referencia = $this->ultima_janela_auditada ?? $this->last_checked_at;

        if ($referencia === null) {
            return true;
        }

        $intervaloHoras = match ($this->periodicidade) {
            'horaria' => 1,
            'semanal' => 24 * 7,
            'mensal' => 24 * 30,
            default => 24,
        };

        return $referencia->lessThanOrEqualTo($agora->copy()->subHours($intervaloHoras));
    }

    /**
     * Frase para a interface distinguir "ainda dentro da janela" de
     * "janela fechou".
     */
    public function situacaoDaJanela(?Carbon $agora = null): string
    {
        $agora ??= now();

        if (! $this->horario_esperado) {
            return 'Sem janela definida; auditado por periodicidade.';
        }

        $fim = $this->fimDaJanelaMaisRecente($agora);

        if ($fim === null) {
            return 'Aguardando a primeira janela desde o início do monitoramento.';
        }

        return sprintf(
            'Janela mais recente encerrada em %s (esperado %s + %dh de tolerância).',
            $fim->format('d/m/Y H:i'),
            substr((string) $this->horario_esperado, 0, 5),
            (int) ($this->tolerancia_horas ?? 0)
        );
    }
}
