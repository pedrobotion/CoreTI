<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lote de requisição de peças ao Centro de Distribuição (um chamado Jira
 * consolidado). Ver a migration create_bancada_cd_requisicoes_table.
 */
class BancadaCdRequisicao extends Model
{
    protected $table = 'bancada_cd_requisicoes';

    public const STATUS_ABERTA = 'aberta';
    public const STATUS_RESOLVIDA = 'resolvida';
    public const STATUS_COLETADA = 'coletada';

    protected $fillable = [
        'jira_issue_key',
        'jira_issue_url',
        'status',
        'aberta_em',
        'resolvida_em',
        'coleta_liberada_em',
        'coletada_em',
        'coletada_por',
        'total_itens',
    ];

    protected function casts(): array
    {
        return [
            'aberta_em' => 'datetime',
            'resolvida_em' => 'datetime',
            'coleta_liberada_em' => 'date',
            'coletada_em' => 'datetime',
            'total_itens' => 'integer',
        ];
    }

    /** @return HasMany<BancadaEquipment> */
    public function equipments(): HasMany
    {
        return $this->hasMany(BancadaEquipment::class, 'cd_requisicao_id');
    }

    /** @return BelongsTo<User, BancadaCdRequisicao> */
    public function coletadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coletada_por');
    }

    /** Lotes resolvidos aguardando a coleta (o que o sino mostra). */
    public function scopeAguardandoColeta($query)
    {
        return $query->where('status', self::STATUS_RESOLVIDA);
    }

    /**
     * Regra fixa de liberação da coleta: dia do pedido + 1 dia útil
     * (segunda → terça, quarta → quinta). Se cair em fim de semana, pula
     * para a próxima segunda.
     */
    public static function coletaLiberadaPara(CarbonInterface $pedido): CarbonImmutable
    {
        $dia = CarbonImmutable::parse($pedido)->startOfDay()->addDay();

        while ($dia->isWeekend()) {
            $dia = $dia->addDay();
        }

        return $dia;
    }
}
