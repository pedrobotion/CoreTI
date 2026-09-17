<?php

namespace App\Models;

use App\Models\Concerns\HasOperationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Impressora do parque, espelhada da EKM Insight API.
 *
 * O CoreTI não cadastra impressora à mão: a Insight é a fonte mestre do
 * inventário, por ser a única com 100% do parque. O que o CoreTI acrescenta
 * é o recorte Sede × Cerrado e o vínculo com a unidade organizacional, que
 * o portal não conhece.
 */
class Printer extends Model
{
    use HasOperationalScope;

    /** Situações de monitoramento que o portal reporta. */
    public const MONITOR_HABILITADA = 'Y';
    public const MONITOR_SO_CONSUMIVEIS = 'I';
    public const MONITOR_SO_RELATORIOS = 'J';
    public const MONITOR_DESABILITADA = 'X';

    public const MONITOR_LABELS = [
        self::MONITOR_HABILITADA => 'Monitorada',
        self::MONITOR_SO_CONSUMIVEIS => 'Só consumíveis',
        self::MONITOR_SO_RELATORIOS => 'Só relatórios',
        self::MONITOR_DESABILITADA => 'Desabilitada',
    ];

    /** Abaixo disso o toner pede atenção. */
    public const NIVEL_CRITICO = 10;
    public const NIVEL_ALERTA = 20;

    /** Sem contato há mais que isso, algo está errado com a coleta. */
    public const DIAS_SEM_CONTATO_ALERTA = 3;

    protected $table = 'printers';

    protected $fillable = [
        'insight_device_id', 'insight_customer_id', 'scope', 'organization_unit_id',
        'serial_number', 'asset_number', 'sticker_number',
        'manufacturer', 'model', 'sku', 'mib_description', 'firmware',
        'ip_address', 'mac_address', 'hostname',
        'monitor_status', 'monitor_name',
        'insight_location', 'insight_zone', 'insight_zone_id',
        'comment', 'discovery_date', 'last_contact', 'registered',
        'raw_data', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'discovery_date' => 'datetime',
            'last_contact' => 'datetime',
            'registered' => 'datetime',
            'last_synced_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function meters(): HasMany
    {
        return $this->hasMany(PrinterMeter::class)->orderByDesc('billing_date');
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(PrinterSupply::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(PrinterAlert::class)->orderByDesc('detectado_em');
    }

    public function consumableRequests(): HasMany
    {
        return $this->hasMany(PrinterConsumableRequest::class)->orderByDesc('solicitado_em');
    }

    public function getNomeAttribute(): string
    {
        $descricao = trim(($this->manufacturer ?? '').' '.($this->model ?? ''));

        return $descricao !== '' ? $descricao : ($this->serial_number ?: "Dispositivo {$this->insight_device_id}");
    }

    public function getMonitorLabelAttribute(): string
    {
        return self::MONITOR_LABELS[$this->monitor_status] ?? '—';
    }

    /** Onde ela está: a unidade quando mapeada, o texto do portal quando não. */
    public function getLocalAttribute(): string
    {
        return $this->unit?->name ?: ($this->insight_location ?: '—');
    }

    /**
     * Coleta parada.
     *
     * Impressora que não reporta há dias não está necessariamente offline —
     * pode ser o coletor da unidade que caiu. De qualquer forma, o contador
     * dela deixou de existir para efeito de fatura.
     */
    public function semContatoRecente(): bool
    {
        return $this->last_contact === null
            || $this->last_contact->diffInDays(now()) >= self::DIAS_SEM_CONTATO_ALERTA;
    }

    /** @param  Builder<static>  $query */
    public function scopeSemUnidade(Builder $query): Builder
    {
        return $query->whereNull('organization_unit_id');
    }

    /** @param  Builder<static>  $query */
    public function scopeMonitoradas(Builder $query): Builder
    {
        return $query->whereIn('monitor_status', [self::MONITOR_HABILITADA, self::MONITOR_SO_CONSUMIVEIS]);
    }
}
