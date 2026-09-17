<?php

namespace App\Models;

use App\Models\Concerns\HasOperationalScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelefoniaFatura extends Model
{
    use HasOperationalScope;

    /** Diferença aceita entre o total declarado e a soma das linhas. */
    public const TOLERANCIA_TOTAL = 0.05;

    protected $table = 'telefonia_faturas';

    protected $fillable = [
        'scope',
        'operadora',
        'numero_conta',
        'competencia',
        'vencimento',
        'valor_total_declarado',
        'valor_total_calculado',
        'quantidade_linhas',
        'arquivo_pdf',
        'arquivo_nome_original',
        'hash_arquivo',
        'status_processamento',
        'observacao_parser',
        'importado_por',
    ];

    protected function casts(): array
    {
        return [
            'competencia' => 'date',
            'vencimento' => 'date',
            'valor_total_declarado' => 'decimal:2',
            'valor_total_calculado' => 'decimal:2',
            'quantidade_linhas' => 'integer',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(TelefoniaFaturaLinha::class, 'telefonia_fatura_id');
    }

    public function importadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importado_por');
    }

    public function getCompetenciaLabelAttribute(): string
    {
        if ($this->competencia === null) {
            return '—';
        }

        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

        return $meses[$this->competencia->month - 1].'/'.$this->competencia->year;
    }

    /**
     * O que a operadora declarou bate com a soma das linhas que o parser leu?
     *
     * Quando não bate, alguma linha não foi reconhecida no PDF — e a conferência
     * de quem paga deixa de fechar.
     */
    public function totaisBatem(): bool
    {
        if ($this->valor_total_declarado === null || $this->valor_total_calculado === null) {
            return false;
        }

        return abs((float) $this->valor_total_declarado - (float) $this->valor_total_calculado) < self::TOLERANCIA_TOTAL;
    }

    public function diferencaTotal(): float
    {
        return round((float) $this->valor_total_calculado - (float) $this->valor_total_declarado, 2);
    }
}
