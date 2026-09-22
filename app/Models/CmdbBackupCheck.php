<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbBackupCheck extends Model
{
    protected $table = 'cmdb_backup_checks';

    protected $fillable = [
        'target_id', 'executado_em', 'status', 'detalhe',
        'arquivo_existe', 'arquivo_tamanho_bytes', 'arquivo_modificado_em', 'idade_horas',
        'duracao_ms', 'erro_conexao', 'incident_id', 'origem',
        'divergente', 'divergencia_detalhe',
        'jira_issue_key', 'jira_issue_url', 'jira_dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'executado_em' => 'datetime',
            'arquivo_existe' => 'boolean',
            'arquivo_tamanho_bytes' => 'integer',
            'arquivo_modificado_em' => 'datetime',
            'idade_horas' => 'integer',
            'duracao_ms' => 'integer',
            'divergente' => 'boolean',
            'jira_dispatched_at' => 'datetime',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(CmdbBackupTarget::class, 'target_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(CmdbBackupIncident::class, 'incident_id');
    }

    public function statusLabel(): string
    {
        return \App\Support\Backups\BackupStatus::rotulo($this->status);
    }

    public function statusTom(): string
    {
        return \App\Support\Backups\BackupStatus::tom($this->status);
    }

    public function idadeLegivel(): string
    {
        if ($this->idade_horas === null) {
            return '-';
        }

        return $this->idade_horas < 24
            ? $this->idade_horas.'h'
            : intdiv($this->idade_horas, 24).'d '.($this->idade_horas % 24).'h';
    }

    public function scopeProblemas(Builder $query): Builder
    {
        return $query->where('status', '!=', \App\Support\Backups\BackupStatus::OK);
    }

    public function ok(): bool
    {
        return $this->status === \App\Support\Backups\BackupStatus::OK;
    }

    public function tamanhoLegivel(): string
    {
        $bytes = $this->arquivo_tamanho_bytes;

        if ($bytes === null) {
            return '-';
        }

        foreach ([['GB', 1024 ** 3], ['MB', 1024 ** 2], ['KB', 1024]] as [$unidade, $divisor]) {
            if ($bytes >= $divisor) {
                return number_format($bytes / $divisor, 2, ',', '.').' '.$unidade;
            }
        }

        return $bytes.' B';
    }
}
