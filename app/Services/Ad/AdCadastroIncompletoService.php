<?php

namespace App\Services\Ad;

use App\Models\AdNonCollaboratorAccount;
use App\Models\AdUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Contas do AD com cadastro incompleto.
 *
 * Olha só contas ativas e ainda presentes no domínio: conta desativada ou já
 * excluída não é pendência de cadastro, é histórico.
 */
class AdCadastroIncompletoService
{
    /**
     * Campo → rótulo. A chave é o nome da coluna em ad_users.
     *
     * @return array<string, string>
     */
    public const CAMPOS = [
        'matricula' => 'Matrícula',
        'unidade_principal' => 'Unidade',
        'Cargo' => 'Cargo',
        'centro_custo' => 'Centro de custo',
    ];

    /**
     * Contas que não são de colaborador.
     *
     * Este relatório existe para achar PESSOA com cadastro incompleto. Um
     * scanner não tem matrícula nem cargo e nunca terá: as 100 contas
     * scanner.* entrariam como pendência permanente e afogariam o que
     * realmente precisa ser preenchido.
     *
     * Isso não as dispensa de centro de custo — elas consomem custo do setor
     * onde ficam. Quem resolve isso é a regra por OU, em AdOfficeRuleService,
     * que trata scanner.* como conta válida de propósito.
     */
    private const PREFIXOS_NAO_PESSOA = ['scanner.', 'svc', 'srv', 'ftp', 'ged.', 'ctx', 'dnadmin'];

    public function consulta(array $camposExigidos = [], bool $incluirForaDeEscopo = false): Builder
    {
        $campos = $camposExigidos === [] ? array_keys(self::CAMPOS) : $camposExigidos;

        $query = AdUser::query()->where('e_ativo', true)->vigentes();

        if (! $incluirForaDeEscopo) {
            // Lista mantida na tela: balança, barraca, impressora, guarita,
            // conta de serviço e conta de máquina não são pessoas.
            $naoColaboradores = AdNonCollaboratorAccount::nomes();

            if ($naoColaboradores !== []) {
                $query->whereNotIn(DB::raw('LOWER(nome_usuario)'), $naoColaboradores);
            }

            foreach (self::PREFIXOS_NAO_PESSOA as $prefixo) {
                $query->where('nome_usuario', 'not like', $prefixo.'%');
            }

            /*
             | Concessionárias e Terceiros seguem outro modelo de cadastro.
             | O DN traz cada nível como "OU=Nome,", então a comparação inclui
             | a vírgula: sem ela, "Terceiros" casaria com "Terceirizados".
             */
            foreach ((array) config('ad.relatorio.ous_fora_de_escopo', []) as $ou) {
                /*
                 | COALESCE porque NULL NOT LIKE '...' devolve NULL em SQL, e
                 | NULL não passa no WHERE: conta sem DistinguishedName seria
                 | descartada em silêncio, que é o oposto do pretendido.
                 */
                $query->whereRaw('COALESCE(`distinguished_name`, "") NOT LIKE ?', ['%OU='.$ou.',%']);
            }
        }

        // Falta QUALQUER um dos campos pedidos.
        $query->where(function ($q) use ($campos): void {
            foreach ($campos as $campo) {
                $q->orWhere(function ($sub) use ($campo): void {
                    $sub->whereRaw('TRIM(COALESCE(`'.$campo.'`, "")) = ""');

                    /*
                     | Campo dispensado não é pendência. A Rodocoop é empresa
                     | afiliada: os colaboradores não têm matrícula nossa, e
                     | cobrá-la geraria linhas que ninguém pode resolver.
                     */
                    foreach ($this->ousQueDispensam($campo) as $ou) {
                        $sub->whereRaw('COALESCE(`distinguished_name`, "") NOT LIKE ?', ['%OU='.$ou.',%']);
                    }
                });
            }
        });

        return $query->orderBy('nome_usuario');
    }

    /** @return array<string, int> */
    public function contagens(bool $incluirForaDeEscopo = false): array
    {
        $contagens = [];

        foreach (self::CAMPOS as $campo => $rotulo) {
            $contagens[$campo] = $this->consulta([$campo], $incluirForaDeEscopo)->count();
        }

        $contagens['qualquer'] = $this->consulta([], $incluirForaDeEscopo)->count();

        return $contagens;
    }

    /**
     * OUs que dispensam este campo.
     *
     * @return array<int, string>
     */
    public function ousQueDispensam(string $campo): array
    {
        $ous = [];

        foreach ((array) config('ad.relatorio.campos_dispensados', []) as $ou => $campos) {
            if (in_array($campo, (array) $campos, true)) {
                $ous[] = $ou;
            }
        }

        return $ous;
    }

    private function dispensado(AdUser $conta, string $campo): bool
    {
        $dn = (string) ($conta->distinguished_name ?? '');

        foreach ($this->ousQueDispensam($campo) as $ou) {
            if (str_contains($dn, 'OU='.$ou.',')) {
                return true;
            }
        }

        return false;
    }

    /** Quais campos faltam nesta conta, já descontando os dispensados. */
    public function faltando(AdUser $conta): Collection
    {
        return collect(self::CAMPOS)
            ->filter(fn ($rotulo, $campo) => trim((string) ($conta->{$campo} ?? '')) === ''
                && ! $this->dispensado($conta, $campo))
            ->values();
    }
}
