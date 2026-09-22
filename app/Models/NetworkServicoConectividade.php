<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class NetworkServicoConectividade extends Model
{
    protected $table = 'network_servicos_conectividade';

    protected $fillable = [
        'contrato',
        'contratante_id',
        'razao_social',
        'velocidade_nominal',
        'produto_id',
        'rede',
        'mascara',
        'gateway',
        'broadcast',
        'faixa',
        'qtd',
        'endereco_instalacao',
        'estacao_a',
        'endereco',
        'numero',
        'bairro',
        'municipio',
        'uf',
        'contato',
        'outros_contatos',
        'unidade_id',
        'departamento_id',
        'organization_unit_id',
        'organization_department_id',
        'local_tipo',
        'local_nome',
        'observacao',
        'status',
        'ficha_original_name',
        'ficha_storage_path',
        'ficha_mime_type',
        'ficha_uploaded_at',
        'ficha_uploaded_by',
        'ia_status',
        'ia_resultado',
        'ia_processado_em',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'ia_resultado' => 'array',
            'ficha_uploaded_at' => 'datetime',
            'ia_processado_em' => 'datetime',
        ];
    }

    public function contratante(): BelongsTo
    {
        return $this->belongsTo(NetworkContratante::class, 'contratante_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(NetworkProduto::class, 'produto_id');
    }

    public function fichaUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ficha_uploaded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

}
