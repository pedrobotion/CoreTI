<?php

namespace App\Models;

use App\Models\CoretiCostCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdOfficeRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'ignorar' => 'boolean',
        'confirmado_em' => 'datetime',
    ];

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CoretiCostCenter::class, 'centro_custo_code', 'code');
    }

    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public const TIPO_CENTRO_CUSTO = 'centro_custo';
    public const TIPO_CNPJ = 'cnpj';

    /**
     * O que efetivamente vai escrito no campo Escritório do AD.
     *
     * Nem sempre é código de centro de custo: empresa terceira recebe o CNPJ.
     */
    public function valorParaAd(): ?string
    {
        if ($this->tipo_valor === self::TIPO_CNPJ) {
            $valor = trim((string) $this->valor_livre);

            return $valor === '' ? null : $valor;
        }

        return $this->centro_custo_code;
    }

    /** A regra está pronta para gerar mudança no AD? */
    public function aplicavel(): bool
    {
        return ! $this->ignorar && $this->valorParaAd() !== null;
    }

    public function situacao(): string
    {
        if ($this->ignorar) {
            return 'ignorada';
        }

        if ($this->valorParaAd() === null) {
            return 'sem_definicao';
        }

        return $this->confirmado_em !== null ? 'confirmada' : 'sugerida';
    }

    public function rotulo(): string
    {
        return match ($this->situacao()) {
            'ignorada' => 'Não mexer',
            'sem_definicao' => 'Falta definir',
            'sugerida' => 'Sugestão a confirmar',
            'confirmada' => 'Confirmada',
        };
    }

    /** Classes literais: o Tailwind só enxerga nomes de classe completos. */
    public function classesBadge(): string
    {
        return match ($this->situacao()) {
            'ignorada' => 'bg-slate-100 text-slate-700',
            'sem_definicao' => 'bg-red-100 text-red-800',
            'sugerida' => 'bg-amber-100 text-amber-800',
            'confirmada' => 'bg-emerald-100 text-emerald-800',
        };
    }
}
