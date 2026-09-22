<?php

namespace App\Services\Ad;

use App\Models\AdNonCollaboratorAccount;
use App\Models\AdOfficeAccountException;
use App\Models\AdOfficeRule;
use App\Models\AdUser;
use Illuminate\Support\Collection;

/**
 * Regra do campo "Escritório" do AD: OU do setor → código do centro de custo.
 *
 * O Active Directory não tem atributo calculado. A regra mora aqui, o CoreTI
 * mostra a prévia do que mudaria, e um script aplica no domínio.
 */
class AdOfficeRuleService
{
    /**
     * Caminho legível da OU de lotação, extraído do DistinguishedName.
     *
     * O DN vem do mais específico ao mais geral e inclui níveis que não são
     * setor — "Usuários" é pasta de organização. Devolve os dois níveis mais
     * específicos que sobram, que é o que identifica a lotação:
     *
     *   CN=Fulano,OU=E-Commerce,OU=Sede,DC=cocari  →  "Sede / E-Commerce"
     */
    public function caminhoDaOu(?string $distinguishedName): ?string
    {
        if (! preg_match_all('/OU=([^,]+)/', (string) $distinguishedName, $m)) {
            return null;
        }

        $ignorados = array_map(
            fn ($n) => mb_strtolower($n),
            (array) config('ad.escritorio.niveis_ignorados', [])
        );

        $niveis = array_values(array_filter(
            $m[1],
            fn ($n) => ! in_array(mb_strtolower(trim($n)), $ignorados, true)
        ));

        if ($niveis === []) {
            return null;
        }

        // Do mais geral ao mais específico, como a pessoa lê na árvore.
        $doGeralAoEspecifico = array_reverse($niveis);

        return implode(' / ', array_slice($doGeralAoEspecifico, -2));
    }

    /** @var array<string, string>|null */
    private ?array $excecoes = null;

    /** @var array<int, string>|null */
    private ?array $naoColaboradores = null;

    /** @return array<int, string> */
    private function naoColaboradores(): array
    {
        return $this->naoColaboradores ??= AdNonCollaboratorAccount::nomes();
    }

    /**
     * Contas com exceção individual cadastrada, em minúsculo => motivo.
     *
     * @return array<string, string>
     */
    public function excecoesPorConta(): array
    {
        return $this->excecoes ??= AdOfficeAccountException::query()
            ->get(['nome_usuario', 'motivo'])
            ->mapWithKeys(fn ($e) => [mb_strtolower(trim($e->nome_usuario)) => $e->motivo])
            ->all();
    }

    /**
     * A conta fica de fora da regra?
     *
     * Dois motivos: padrão estrutural (contas modelo e de integração, no
     * config) ou exceção individual cadastrada — gente cujo centro de custo
     * segue a função e não o lugar, ou conta tratada caso a caso.
     */
    public function contaIgnorada(AdUser $conta): bool
    {
        $usuario = mb_strtolower(trim((string) $conta->nome_usuario));

        if (isset($this->excecoesPorConta()[$usuario])) {
            return true;
        }

        // Quem não é colaborador também não recebe centro de custo.
        if (in_array($usuario, $this->naoColaboradores(), true)) {
            return true;
        }

        foreach ((array) config('ad.escritorio.ignorar_contas', []) as $regra) {
            [$campo, $padrao] = array_pad(explode(':', (string) $regra, 2), 2, null);

            if ($padrao === null) {
                continue;
            }

            $valor = mb_strtolower(trim((string) ($conta->{$campo} ?? '')));

            if ($valor !== '' && fnmatch(mb_strtolower($padrao), $valor)) {
                return true;
            }
        }

        return false;
    }

    /** Contas vigentes e ativas que a regra alcança. */
    public function contasAlcancadas(): Collection
    {
        return $this->contasVigentes()
            ->reject(fn (AdUser $c) => $this->contaIgnorada($c))
            ->values();
    }

    /** Contas ativas e ainda presentes no domínio, antes de qualquer exclusão. */
    private function contasVigentes(): Collection
    {
        return AdUser::query()
            ->where('e_ativo', true)
            ->vigentes()
            ->get(['id', 'nome_usuario', 'nome_completo', 'centro_custo', 'distinguished_name']);
    }

    /**
     * Quem a regra alcança e quem fica de fora, com o motivo.
     *
     * A tela precisa disso para os números serem explicáveis: sem o recorte,
     * "205 contas mudariam" não diz nada sobre as outras 1.500.
     *
     * @return array{alcancadas:int, fora:int, motivos:array<string,int>}
     */
    public function escopo(): array
    {
        $vigentes = $this->contasVigentes();
        $excecoes = $this->excecoesPorConta();
        $naoColaboradores = $this->naoColaboradores();

        $motivos = ['Não é colaborador' => 0, 'Conta de sistema ou modelo' => 0, 'Exceção individual' => 0];
        $alcancadas = 0;

        foreach ($vigentes as $conta) {
            $usuario = mb_strtolower(trim((string) $conta->nome_usuario));

            if (isset($excecoes[$usuario])) {
                $motivos['Exceção individual']++;
            } elseif (in_array($usuario, $naoColaboradores, true)) {
                $motivos['Não é colaborador']++;
            } elseif ($this->contaIgnorada($conta)) {
                $motivos['Conta de sistema ou modelo']++;
            } else {
                $alcancadas++;
            }
        }

        return [
            'alcancadas' => $alcancadas,
            'fora' => $vigentes->count() - $alcancadas,
            'motivos' => array_filter($motivos),
        ];
    }

    /**
     * Levantamento por OU: quantas contas, qual código domina hoje, quantas
     * divergem dele.
     *
     * @return array<string, array{contas:int, dominante:?string, divergentes:int, distribuicao:array<string,int>}>
     */
    public function levantamento(): array
    {
        $porOu = [];

        foreach ($this->contasAlcancadas() as $conta) {
            $ou = $this->caminhoDaOu($conta->distinguished_name);

            if ($ou === null) {
                continue;
            }

            $codigo = trim((string) $conta->centro_custo);
            $porOu[$ou] ??= ['contas' => 0, 'distribuicao' => []];
            $porOu[$ou]['contas']++;

            if ($codigo !== '') {
                $porOu[$ou]['distribuicao'][$codigo] = ($porOu[$ou]['distribuicao'][$codigo] ?? 0) + 1;
            }
        }

        foreach ($porOu as $ou => $dados) {
            $dist = $dados['distribuicao'];
            arsort($dist);

            $dominante = $dist === [] ? null : (string) array_key_first($dist);
            $preenchidas = array_sum($dist);

            $porOu[$ou]['distribuicao'] = $dist;
            $porOu[$ou]['dominante'] = $dominante;
            // Divergentes conta só quem TEM código diferente do dominante.
            // Conta vazia não diverge: ela ainda não foi preenchida.
            $porOu[$ou]['divergentes'] = $dominante === null ? 0 : $preenchidas - $dist[$dominante];
        }

        ksort($porOu);

        return $porOu;
    }

    /**
     * Cria ou atualiza a sugestão de cada OU, sem tocar em decisão já tomada.
     *
     * @return array{criadas:int, atualizadas:int, intocadas:int}
     */
    public function semear(): array
    {
        $criadas = $atualizadas = $intocadas = 0;

        foreach ($this->levantamento() as $ou => $dados) {
            $regra = AdOfficeRule::firstOrNew(['ou_caminho' => $ou]);

            $novos = [
                'sugestao_code' => $dados['dominante'],
                'contas_na_ou' => $dados['contas'],
                'contas_divergentes' => $dados['divergentes'],
            ];

            if (! $regra->exists) {
                // Nasce com a sugestão preenchida, mas sem confirmação: a tela
                // mostra "a confirmar" até alguém olhar.
                $novos['centro_custo_code'] = $dados['dominante'];
                $regra->fill(['ou_caminho' => $ou] + $novos)->save();
                $criadas++;

                continue;
            }

            /*
             | Regra já decidida por uma pessoa só recebe a atualização dos
             | números. Sobrescrever o código apagaria a decisão a cada
             | execução do comando.
             */
            if ($regra->confirmado_em !== null || $regra->ignorar) {
                $regra->fill($novos)->save();
                $intocadas++;

                continue;
            }

            $regra->fill($novos + ['centro_custo_code' => $regra->centro_custo_code ?? $dados['dominante']])->save();
            $atualizadas++;
        }

        return compact('criadas', 'atualizadas', 'intocadas');
    }

    /**
     * Contas cujo Escritório mudaria se as regras fossem aplicadas.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function previa(): Collection
    {
        $regras = AdOfficeRule::all()->keyBy('ou_caminho');

        return $this->contasAlcancadas()
            ->map(function (AdUser $conta) use ($regras) {
                $ou = $this->caminhoDaOu($conta->distinguished_name);
                $regra = $ou === null ? null : $regras->get($ou);

                if ($regra === null || ! $regra->aplicavel()) {
                    return null;
                }

                $atual = trim((string) $conta->centro_custo);
                $desejado = $regra->valorParaAd();

                if ($atual === $desejado) {
                    return null;
                }

                return [
                    'nome_usuario' => $conta->nome_usuario,
                    'nome_completo' => $conta->nome_completo,
                    'distinguished_name' => $conta->distinguished_name,
                    'ou_caminho' => $ou,
                    'de' => $atual === '' ? null : $atual,
                    'para' => $desejado,
                ];
            })
            ->filter()
            ->values();
    }
}
