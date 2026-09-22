<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmdbDatabaseBackup extends Model
{
    protected $table = 'cmdb_database_backups';

    protected $fillable = [
        'host',
        'database_name',
        'backup_type',
        'status',
        'sucesso',
        'ip_address',
        'arquivo',
        'tamanho',
        'observacao',
        'inicio_backup',
        'fim_backup',
        'data_backup',
        'data_verificacao',
        'duracao_segundos',
        'asset_id',
        'asset_match_method',
        'jira_issue_key',
        'jira_issue_url',
        'jira_dispatched_at',
        'jira_error',
        'payload',
        'fingerprint',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'sucesso' => 'boolean',
            'tamanho' => 'decimal:3',
            'duracao_segundos' => 'integer',
            'inicio_backup' => 'datetime',
            'fim_backup' => 'datetime',
            'data_backup' => 'datetime',
            'data_verificacao' => 'datetime',
            'jira_dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CmdbAsset::class, 'asset_id');
    }

    public function scopeFalhas(Builder $query): Builder
    {
        return $query->where('sucesso', false);
    }

    public function scopeSucessos(Builder $query): Builder
    {
        return $query->where('sucesso', true);
    }

    public function scopeDoPar(Builder $query, string $host, string $database): Builder
    {
        return $query->where('host', $host)->where('database_name', $database);
    }

    /**
     * Identificação legível do par monitorado.
     */
    public function par(): string
    {
        return $this->host.' / '.$this->database_name;
    }

    public function duracaoLegivel(): string
    {
        $segundos = $this->duracao_segundos;

        if ($segundos === null) {
            return '-';
        }

        if ($segundos < 60) {
            return $segundos.'s';
        }

        $minutos = intdiv($segundos, 60);

        if ($minutos < 60) {
            return $minutos.'min '.($segundos % 60).'s';
        }

        return intdiv($minutos, 60).'h '.($minutos % 60).'min';
    }

    public function tamanhoLegivel(): string
    {
        if ($this->tamanho === null) {
            return '-';
        }

        // O agente envia o tamanho em GB (ex.: 0.45).
        $gb = (float) $this->tamanho;

        return $gb < 1
            ? number_format($gb * 1024, 0, ',', '.').' MB'
            : number_format($gb, 2, ',', '.').' GB';
    }
}
