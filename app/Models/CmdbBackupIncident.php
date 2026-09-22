<?php

namespace App\Models;

use App\Support\Backups\BackupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um problema contínuo de um alvo.
 *
 * Existe para separar "o problema" de "cada vez que ele foi observado". Sem
 * isso, o scheduler abriria um chamado no Jira a cada execução enquanto a
 * falha persistisse. A chave lógica é (alvo + tipo de falha) enquanto aberto.
 */
class CmdbBackupIncident extends Model
{
    protected $table = 'cmdb_backup_incidents';

    public const STATUS_ABERTO = 'aberto';

    /** Retorno real a OK — o backup voltou a funcionar. */
    public const STATUS_RECUPERADO = 'recuperado';

    /**
     * O problema não acabou: mudou de natureza (ex.: atrasado -> ausente).
     * Distinto de recuperado para que SLA e MTTR não contem como resolvido.
     */
    public const STATUS_SUBSTITUIDO = 'substituido';

    public const MOTIVO_RECUPERACAO = 'recuperacao';
    public const MOTIVO_TIPO_ALTERADO = 'tipo_falha_alterado';

    public const JIRA_CRIADO = 'criado';
    public const JIRA_JA_EXISTENTE = 'ja_existente';
    public const JIRA_NAO_CONFIGURADO = 'jira_nao_configurado';
    public const JIRA_ERRO = 'erro';
    public const JIRA_EM_COOLDOWN = 'cooldown';

    protected $fillable = [
        'target_id', 'tipo_falha', 'status',
        'aberto_em', 'ultima_ocorrencia_em', 'recuperado_em', 'ocorrencias',
        'encerrado_em', 'motivo_encerramento', 'detalhe',
        'jira_status', 'jira_issue_key', 'jira_issue_url', 'jira_criado_em',
        'jira_ultima_tentativa_em', 'jira_proxima_tentativa_em',
        'jira_erro', 'jira_recuperacao_comentada',
    ];

    protected function casts(): array
    {
        return [
            'aberto_em' => 'datetime',
            'ultima_ocorrencia_em' => 'datetime',
            'recuperado_em' => 'datetime',
            'encerrado_em' => 'datetime',
            'jira_criado_em' => 'datetime',
            'jira_ultima_tentativa_em' => 'datetime',
            'jira_proxima_tentativa_em' => 'datetime',
            'ocorrencias' => 'integer',
            'jira_recuperacao_comentada' => 'boolean',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(CmdbBackupTarget::class, 'target_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(CmdbBackupCheck::class, 'incident_id');
    }

    public function scopeAbertos(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ABERTO);
    }

    public function estaAberto(): bool
    {
        return $this->status === self::STATUS_ABERTO;
    }

    /** Só isto conta como "o backup voltou ao normal". */
    public function foiRecuperado(): bool
    {
        return $this->status === self::STATUS_RECUPERADO;
    }

    public function foiSubstituido(): bool
    {
        return $this->status === self::STATUS_SUBSTITUIDO;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ABERTO => 'Aberto',
            self::STATUS_RECUPERADO => 'Recuperado',
            self::STATUS_SUBSTITUIDO => 'Substituído por outra falha',
            default => $this->status,
        };
    }

    /**
     * Tempo real de indisponibilidade — base para MTTR.
     *
     * Só faz sentido em incidente recuperado: no substituído, o problema
     * continuou sob outro incidente.
     */
    public function duracaoDaIndisponibilidadeHoras(): ?int
    {
        if (! $this->foiRecuperado() || ! $this->recuperado_em) {
            return null;
        }

        return (int) $this->aberto_em->diffInHours($this->recuperado_em);
    }

    /** O Jira pode ser tentado agora, respeitando o cooldown temporal? */
    public function podeTentarJira(?\Illuminate\Support\Carbon $agora = null): bool
    {
        $agora ??= now();

        return $this->jira_proxima_tentativa_em === null
            || $this->jira_proxima_tentativa_em->lessThanOrEqualTo($agora);
    }

    public function tipoFalhaLabel(): string
    {
        return BackupStatus::rotulo($this->tipo_falha);
    }

    /**
     * Primeira ocorrência ou repetição de um problema já conhecido?
     */
    public function ehRecorrente(): bool
    {
        return $this->ocorrencias > 1;
    }

    public function jiraStatusLabel(): string
    {
        return match ($this->jira_status) {
            self::JIRA_CRIADO => 'Chamado criado',
            self::JIRA_JA_EXISTENTE => 'Chamado já existente',
            self::JIRA_NAO_CONFIGURADO => 'Jira não configurado',
            self::JIRA_EM_COOLDOWN => 'Aguardando janela de reabertura',
            self::JIRA_ERRO => 'Erro ao criar chamado',
            default => 'Sem chamado',
        };
    }

    public function duracaoHoras(): ?int
    {
        $fim = $this->encerrado_em ?? $this->recuperado_em ?? now();

        return $this->aberto_em ? (int) $this->aberto_em->diffInHours($fim) : null;
    }
}
