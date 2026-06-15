<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BancadaEquipment extends Model
{
    protected $table = 'bancada_equipments';

    protected $fillable = [
        'tipo_equipamento',
        'plaqueta',
        'unidade_setor',
        'origem_tipo',
        'delivery_route_id',
        'sent_to_cd_at',
        'sent_to_cd_by',
        'expected_separation_date',
        'expected_loading_date',
        'expected_delivery_date',
        'data_chegada',
        'data_saida',
        'status',
        'entrada_status',
        'nota_documento_entrada',
        'nota_numero_entrada',
        'data_emissao_entrada',
        'nota_valor_entrada',
        'nota_anexo_entrada',
        'nota_documento_saida',
        'nota_numero_saida',
        'nota_anexo_saida',
        'nota_saida_emitida_em',
        'entrada_realizada_em',
        'observacao',
        'tic',
        'peca_nome',
        'peca_quantidade',
        'peca_origem',
        'peca_link_compra',
        'service_tag',
        'peca_fluxo_status',
        'peca_admin_realizado_em',
        'peca_recebida_confirmada_em',
        'backup_pronto_emprestimo',
        'backup_data_formatado',
        'backup_localizacao',
        'terceiros_nota_orcamento',
        'terceiros_problema',
        'terceiros_empresa',
        'terceiros_cnpj',
        'terceiros_nota_remessa',
        'terceiros_os_numero',
        'terceiros_orcamento_anexo',
        'terceiros_observacoes',
        'terceiros_orcamento_status',
        'terceiros_resultado',
        'terceiros_fluxo_status',
        'terceiros_enviado_em',
        'terceiros_valor_reparo',
        'terceiros_retorno_em',
        'terceiros_retorno_informado_em',
        'terceiros_retorno_informado_by',
        'terceiros_retorno_fisico_em',
        'terceiros_retorno_fisico_by',
        'terceiros_retorno_fisico_observacao',
        'plaqueta_retirada',
        'plaqueta_retirada_at',
        'plaqueta_retirada_by',
        'baixa_realizada',
        'baixa_realizada_at',
        'baixa_realizada_by',
    ];

    protected function casts(): array
    {
        return [
            'data_chegada' => 'date',
            'data_saida' => 'date',
            'data_emissao_entrada' => 'date',
            'delivery_route_id' => 'integer',
            'sent_to_cd_at' => 'datetime',
            'expected_separation_date' => 'date',
            'expected_loading_date' => 'date',
            'expected_delivery_date' => 'date',
            'nota_valor_entrada' => 'decimal:2',
            'nota_saida_emitida_em' => 'datetime',
            'entrada_realizada_em' => 'datetime',
            'peca_quantidade' => 'integer',
            'peca_admin_realizado_em' => 'datetime',
            'peca_recebida_confirmada_em' => 'datetime',
            'backup_pronto_emprestimo' => 'boolean',
            'backup_data_formatado' => 'date',
            'terceiros_valor_reparo' => 'decimal:2',
            'terceiros_enviado_em' => 'datetime',
            'terceiros_retorno_em' => 'datetime',
            'terceiros_retorno_informado_em' => 'datetime',
            'terceiros_retorno_fisico_em' => 'datetime',
            'plaqueta_retirada' => 'boolean',
            'plaqueta_retirada_at' => 'datetime',
            'baixa_realizada' => 'boolean',
            'baixa_realizada_at' => 'datetime',
        ];
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BancadaEquipmentStatusHistory::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BancadaEquipmentEvent::class, 'bancada_equipment_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BancadaEquipmentAttachment::class, 'bancada_equipment_id');
    }

    public function plaquetaRetiradaByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plaqueta_retirada_by');
    }

    public function baixaRealizadaByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'baixa_realizada_by');
    }

    public function deliveryRoute(): BelongsTo
    {
        return $this->belongsTo(BancadaMaloteRoute::class, 'delivery_route_id');
    }

    public function sentToCdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_to_cd_by');
    }

    public function terceirosRetornoInformadoByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terceiros_retorno_informado_by');
    }

    public function terceirosRetornoFisicoByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terceiros_retorno_fisico_by');
    }

    public function thirdPartyWorkflowStage(): string
    {
        if ($this->terceiros_retorno_fisico_em) {
            return 'aguardando_retorno_fisico';
        }

        if (
            $this->terceiros_retorno_informado_em
            || $this->terceiros_retorno_em
            || in_array((string) $this->terceiros_fluxo_status, [
                'aguardando_retorno_fisico_aprovado',
                'aguardando_retorno_fisico_reprovado',
                'retorno_positivo',
                'retorno_negativo',
            ], true)
        ) {
            return 'aguardando_retorno_fisico';
        }

        if (
            $this->terceiros_enviado_em
            || in_array((string) $this->terceiros_fluxo_status, [
                'enviado_aguardando_informacoes',
                'enviado_aguardando_retorno',
            ], true)
        ) {
            return 'aguardando_informacoes';
        }

        return 'aguardando_envio';
    }

    public function thirdPartyLatestAttachmentOfType(string $type): ?BancadaEquipmentAttachment
    {
        return $this->attachments
            ->first(function (BancadaEquipmentAttachment $attachment) use ($type): bool {
                return $attachment->attachment_type === $type;
            });
    }
}
