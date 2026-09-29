<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdUser extends Model
{
    protected $table = 'ad_users';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'e_ativo' => 'boolean',
        'criado_em' => 'datetime',
        'ultima_modificacao_senha' => 'datetime',
        'last_sync' => 'datetime',
        'data_desativacao' => 'datetime',
        'excluido' => 'boolean',
        'excluido_em' => 'datetime',
    ];

    /** Contas ainda presentes no Active Directory. */
    public function scopeVigentes($query)
    {
        return $query->where('excluido', false);
    }

    /** Contas já removidas do AD, mantidas apenas como histórico. */
    public function scopeExcluidas($query)
    {
        return $query->where('excluido', true);
    }

    /**
     * O login pertence a uma pessoa, ou é equipamento/serviço/modelo?
     *
     * Scanner, GED de unidade, conta de serviço e conta modelo não têm
     * matrícula nem cargo e nunca terão. Fica no modelo, e não num serviço,
     * porque é característica da própria conta: o painel e o relatório
     * detalhado precisam da mesma resposta, cada um do seu jeito — um filtra
     * uma coleção já carregada, o outro filtra no SQL.
     */
    public function ehDePessoa(): bool
    {
        $login = mb_strtolower(trim((string) $this->nome_usuario));

        if ($login === '') {
            return true;
        }

        foreach (self::prefixosNaoPessoa() as $prefixo) {
            if (str_starts_with($login, $prefixo)) {
                return false;
            }
        }

        return true;
    }

    /** Versão em SQL de {@see ehDePessoa()}. */
    public function scopeDePessoa($query)
    {
        foreach (self::prefixosNaoPessoa() as $prefixo) {
            $query->where('nome_usuario', 'not like', $prefixo.'%');
        }

        return $query;
    }

    /** @return array<int, string> */
    public static function prefixosNaoPessoa(): array
    {
        return array_values(array_filter(array_map(
            fn ($p): string => mb_strtolower(trim((string) $p)),
            (array) config('ad.relatorio.prefixos_nao_pessoa', [])
        )));
    }
}
