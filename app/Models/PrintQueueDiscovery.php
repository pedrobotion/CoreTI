<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma fila de impressão vista num servidor, com o que o SNMP revelou dela.
 *
 * É a visão independente da rede — o contraponto ao que a Simpress afirma.
 * Onde as duas discordam, quem está em campo (esta tabela) costuma estar
 * certo, e a Simpress é que precisa ser corrigida.
 */
class PrintQueueDiscovery extends Model
{
    protected $table = 'print_queue_discoveries';

    public const SNMP_OK = 'ok';
    public const SNMP_SEM_RESPOSTA = 'sem_resposta';
    public const SNMP_SEM_SERIAL = 'sem_serial';
    public const SNMP_NAO_CONSULTADO = 'nao_consultado';

    protected $fillable = [
        'servidor', 'fila', 'ip_porta', 'driver',
        'serial_number', 'modelo_snmp', 'snmp_status', 'snmp_detalhe', 'snmp_verificado_em',
        'printer_id', 'ip_divergente', 'ip_simpress', 'visto_em',
    ];

    protected function casts(): array
    {
        return [
            'snmp_verificado_em' => 'datetime',
            'visto_em' => 'datetime',
            'ip_divergente' => 'boolean',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    /** Casou com uma impressora da Simpress pelo serial. */
    public function scopeCasadas(Builder $query): Builder
    {
        return $query->whereNotNull('printer_id');
    }

    /** Tem serial lido mas nenhuma impressora da Simpress com esse serial. */
    public function scopeSemCorrespondencia(Builder $query): Builder
    {
        return $query->whereNotNull('serial_number')->whereNull('printer_id');
    }

    /** O IP no portal difere do que o servidor usa. */
    public function scopeComDivergenciaDeIp(Builder $query): Builder
    {
        return $query->where('ip_divergente', true);
    }

    /**
     * Filas a consultar numa rodada.
     *
     * Nunca consultadas + as que não responderam antes: impressora offline
     * hoje pode responder amanhã, e sem reconsultar o agendamento diário
     * nunca mais tentaria. As que casaram e conferem não voltam à fila —
     * relê-las todo dia seria desperdício de SNMP no que já está resolvido.
     */
    public function scopeAConsultar(Builder $query): Builder
    {
        return $query->whereNotNull('ip_porta')
            ->whereIn('snmp_status', [self::SNMP_NAO_CONSULTADO, self::SNMP_SEM_RESPOSTA]);
    }

    /**
     * Relê também o que já casou, para pegar mudança de IP na impressora.
     *
     * Uso pontual: o dia a dia usa aConsultar(), que poupa o que está estável.
     */
    public function scopeParaReconsultarTudo(Builder $query): Builder
    {
        return $query->whereNotNull('ip_porta');
    }
}
