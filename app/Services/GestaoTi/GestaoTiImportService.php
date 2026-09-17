<?php

namespace App\Services\GestaoTi;

use App\Models\AssetAssignment;
use App\Models\AssetAssignmentItem;
use App\Models\CorpDevice;
use App\Models\CorpLine;
use App\Models\Equipment;
use App\Models\HrCollaborator;
use App\Models\OrganizationUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importa linhas, aparelhos, equipamentos e vínculos do gestaoTI.
 *
 * A origem é lida por GestaoTiReadOnlyConnection, que recusa qualquer comando
 * de escrita — o gestaoTI segue em produção e não é tocado.
 *
 * O que não couber vira divergência no relatório, nunca gravação silenciosa
 * nem descarte calado. Casos conhecidos da base atual: quatro números de linha
 * repetidos, um IMEI em dois aparelhos, um número de 11 dígitos e três termos
 * cujo PDF sumiu do disco.
 */
class GestaoTiImportService
{
    /** Os dois tenants do gestaoTI e o escopo correspondente aqui. */
    private const TENANT_PARA_ESCOPO = [3 => 'cerrado', 4 => 'sede'];

    /**
     * Nomes de filial que o gestaoTI usa e que não batem com o catálogo de
     * unidades da intranet. Conferidos um a um contra os dados reais.
     */
    private const ALIAS_FILIAL = [
        'cristalina i' => 'Cristalina',
        'campo alegre de goias i' => 'Campo Alegre de Goiás',
        'campo alegre de goias ii (cafe)' => 'UBC - Unidade de Beneficiamento de Café - Cerrado',
    ];

    public const ETAPAS = ['linhas', 'aparelhos', 'equipamentos', 'vinculos'];

    /** @var array<string, array<string, int>> */
    private array $contadores = [];

    /** @var array<int, array<string, mixed>> */
    private array $divergencias = [];

    /** @var array<string, int>|null */
    private ?array $unidadesPorNome = null;

    /** @var array<string, int>|null */
    private ?array $colaboradoresPorMatricula = null;

    private bool $dryRun = true;

    /**
     * @param  array<int, string>  $apenas
     * @return array{contadores: array, divergencias: array, dry_run: bool}
     */
    public function importar(bool $dryRun = true, array $apenas = []): array
    {
        $this->dryRun = $dryRun;
        $this->contadores = [];
        $this->divergencias = [];

        $etapas = $apenas === [] ? self::ETAPAS : array_values(array_intersect(self::ETAPAS, $apenas));

        // Em simulação tudo roda de verdade e é desfeito no fim: os números do
        // relatório são os reais, não uma estimativa.
        DB::beginTransaction();

        try {
            foreach ($etapas as $etapa) {
                match ($etapa) {
                    'linhas' => $this->importarLinhas(),
                    'aparelhos' => $this->importarAparelhos(),
                    'equipamentos' => $this->importarEquipamentos(),
                    'vinculos' => $this->importarVinculos(),
                };
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'contadores' => $this->contadores,
            'divergencias' => $this->divergencias,
            'dry_run' => $dryRun,
        ];
    }

    // ------------------------------------------------------------------
    // Etapas
    // ------------------------------------------------------------------

    private function importarLinhas(): void
    {
        $vistos = [];

        foreach (GestaoTiReadOnlyConnection::get()->table('linhas')->orderBy('id')->get() as $origem) {
            $this->conta('linhas', 'lidos');

            $identificacao = "({$origem->ddd}) {$origem->numero}";
            $escopo = $this->escopo($origem->tenant_id);

            if ($escopo === null) {
                $this->divergir('linhas', $origem->id, $identificacao, "Tenant {$origem->tenant_id} não corresponde a Sede nem Cerrado.");

                continue;
            }

            // A unicidade de (ddd, numero) é nova: no gestaoTI nada impedia o
            // mesmo número duas vezes. A primeira ocorrência entra; as demais
            // viram divergência para alguém decidir qual é a boa.
            $chave = $origem->ddd.$origem->numero;
            if (isset($vistos[$chave])) {
                $this->divergir('linhas', $origem->id, $identificacao, "Número repetido na origem (já importado da linha #{$vistos[$chave]}). Não importado.");

                continue;
            }
            $vistos[$chave] = $origem->id;

            if (! preg_match('/^\d{8,9}$/', (string) $origem->numero)) {
                $this->divergir('linhas', $origem->id, $identificacao, 'Número com '.strlen((string) $origem->numero).' dígitos, fora do padrão de 8 ou 9. Importado como está.', 'aviso');
            }

            $this->gravar(CorpLine::class, ['origem_gestaoti_id' => $origem->id], [
                'scope' => $escopo,
                'operadora' => $origem->operadora,
                'ddd' => $origem->ddd,
                'numero' => $origem->numero,
                'status' => $origem->status,
                'data_cancelamento' => $origem->data_cancelamento,
                'nome_plano' => $origem->nome_plano,
                'valor_mensal' => $origem->valor_mensal,
                'observacao' => $origem->observacao,
            ], 'linhas');
        }
    }

    private function importarAparelhos(): void
    {
        $vistos = [];

        foreach (GestaoTiReadOnlyConnection::get()->table('celulares')->orderBy('id')->get() as $origem) {
            $this->conta('aparelhos', 'lidos');

            $identificacao = trim("{$origem->marca} {$origem->modelo}")." · IMEI {$origem->imei_1}";
            $escopo = $this->escopo($origem->tenant_id);

            if ($escopo === null) {
                $this->divergir('aparelhos', $origem->id, $identificacao, "Tenant {$origem->tenant_id} não corresponde a Sede nem Cerrado.");

                continue;
            }

            $imei = preg_replace('/\D/', '', (string) $origem->imei_1) ?: '';

            if (isset($vistos[$imei])) {
                $this->divergir('aparelhos', $origem->id, $identificacao, "IMEI repetido na origem (já importado do aparelho #{$vistos[$imei]}). Não importado.");

                continue;
            }
            $vistos[$imei] = $origem->id;

            $this->gravar(CorpDevice::class, ['origem_gestaoti_id' => $origem->id], [
                'scope' => $escopo,
                'marca' => $origem->marca,
                'modelo' => $origem->modelo,
                'imei_1' => $imei,
                'imei_2' => preg_replace('/\D/', '', (string) $origem->imei_2) ?: null,
                'numero_serie' => $origem->numero_serie,
                'status' => $origem->status,
                'estado_aparelho' => $origem->estado_aparelho,
                'observacao' => $origem->observacao,
            ], 'aparelhos');
        }
    }

    private function importarEquipamentos(): void
    {
        $vistos = [];

        foreach (GestaoTiReadOnlyConnection::get()->table('equipamentos')->orderBy('id')->get() as $origem) {
            $this->conta('equipamentos', 'lidos');

            $identificacao = "{$origem->patrimonio} · {$origem->nome_equipamento}";
            $escopo = $this->escopo($origem->tenant_id);

            if ($escopo === null) {
                $this->divergir('equipamentos', $origem->id, $identificacao, "Tenant {$origem->tenant_id} não corresponde a Sede nem Cerrado.");

                continue;
            }

            $patrimonio = trim((string) $origem->patrimonio);

            if ($patrimonio === '') {
                $this->divergir('equipamentos', $origem->id, $identificacao, 'Sem número de patrimônio. Não importado.');

                continue;
            }

            if (isset($vistos[$patrimonio])) {
                $this->divergir('equipamentos', $origem->id, $identificacao, "Patrimônio repetido na origem (já importado do equipamento #{$vistos[$patrimonio]}). Não importado.");

                continue;
            }
            $vistos[$patrimonio] = $origem->id;

            $unidadeId = $this->unidadePorFilial($origem->filial);

            if ($unidadeId === null && trim((string) $origem->filial) !== '') {
                $this->divergir('equipamentos', $origem->id, $identificacao, "Filial \"{$origem->filial}\" não encontrada no catálogo de unidades. Importado sem unidade.", 'aviso');
            }

            $this->gravar(Equipment::class, ['origem_gestaoti_id' => $origem->id], [
                'scope' => $escopo,
                'tipo' => $origem->tipo,
                'nome' => $origem->nome_equipamento,
                'patrimonio' => $patrimonio,
                'numero_serie' => $origem->numero_serie,
                'endereco_ip' => filter_var($origem->endereco_ip, FILTER_VALIDATE_IP) ? $origem->endereco_ip : null,
                'organization_unit_id' => $unidadeId,
                'centro_custo_codigo' => $origem->centro_custo_codigo,
                'centro_custo_nome' => $origem->centro_custo_nome,
                // Sem unidade correspondente, o texto da origem ao menos não se
                // perde: fica como localização livre.
                'localizacao' => $unidadeId === null ? (trim((string) $origem->filial) ?: null) : $origem->localizacao_temporaria,
                'status' => $origem->status,
                'observacoes' => $origem->observacoes,
            ], 'equipamentos');
        }
    }

    private function importarVinculos(): void
    {
        $this->importarVinculosDe('vinculos');
        $this->importarVinculosDe('vinculo_notebooks');
    }

    private function importarVinculosDe(string $tabelaOrigem): void
    {
        $conexao = GestaoTiReadOnlyConnection::get();

        $registros = $conexao->table($tabelaOrigem.' as v')
            ->leftJoin('colaboradores_intranet as c', 'c.id', '=', 'v.colaborador_intranet_id')
            ->select('v.*', 'c.matricula', 'c.nome as colaborador_nome')
            ->orderBy('v.id')
            ->get();

        foreach ($registros as $origem) {
            $this->conta('vinculos', 'lidos');

            $identificacao = trim(($origem->colaborador_nome ?? 'sem colaborador').' · '.$tabelaOrigem.' #'.$origem->id);
            $escopo = $this->escopo($origem->tenant_id);

            if ($escopo === null) {
                $this->divergir('vinculos', $origem->id, $identificacao, "Tenant {$origem->tenant_id} não corresponde a Sede nem Cerrado.");

                continue;
            }

            $colaboradorId = $this->colaboradorPorMatricula($origem->matricula);

            if ($colaboradorId === null) {
                $this->divergir('vinculos', $origem->id, $identificacao, 'Matrícula '.($origem->matricula ?? '—').' não encontrada entre os colaboradores sincronizados do RH. Não importado.');

                continue;
            }

            $itens = $this->itensDoVinculo($tabelaOrigem, $origem, $identificacao);

            if ($itens === null) {
                continue; // divergência já registrada
            }

            $dataVinculo = $origem->data_vinculo ? Carbon::parse($origem->data_vinculo) : null;

            if ($dataVinculo === null) {
                $this->divergir('vinculos', $origem->id, $identificacao, 'Sem data de vínculo. Não importado.');

                continue;
            }

            $ativo = (bool) ($origem->ativo ?? true);

            // O vínculo pode ser de colaborador que já saiu: é histórico e deve
            // ser preservado. Mas ativo + pessoa desligada é a inconsistência
            // que mais some com aparelho, então fica sinalizada.
            if ($ativo && ! $this->colaboradorEstaAtivo($colaboradorId)) {
                $this->divergir('vinculos', $origem->id, $identificacao, 'Vínculo ativo de colaborador que não está ativo no RH. Importado e sinalizado na tela.', 'aviso');
            }

            $assignment = $this->gravarVinculo($tabelaOrigem, $origem, $escopo, $colaboradorId, $dataVinculo, $ativo, $identificacao);

            if ($assignment === null) {
                continue;
            }

            if (! $this->dryRun) {
                $assignment->items()->delete();

                foreach ($itens as $coluna => $itemId) {
                    AssetAssignmentItem::query()->create([
                        'asset_assignment_id' => $assignment->id,
                        $coluna => $itemId,
                    ]);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    /**
     * Itens do vínculo, já conferidos contra o que foi importado.
     *
     * @return array<string, int>|null  null = divergência registrada
     */
    private function itensDoVinculo(string $tabelaOrigem, object $origem, string $identificacao): ?array
    {
        $itens = [];

        if ($tabelaOrigem === 'vinculos') {
            $linhaId = $this->idImportado(CorpLine::class, $origem->linha_id);

            if ($linhaId === null) {
                $this->divergir('vinculos', $origem->id, $identificacao, "Linha #{$origem->linha_id} não foi importada (número repetido ou tenant fora do mapa). Vínculo não importado.");

                return null;
            }

            $itens['corp_line_id'] = $linhaId;

            if ($origem->celular_id !== null) {
                $aparelhoId = $this->idImportado(CorpDevice::class, $origem->celular_id);

                if ($aparelhoId === null) {
                    $this->divergir('vinculos', $origem->id, $identificacao, "Aparelho #{$origem->celular_id} não foi importado (IMEI repetido?). Vínculo não importado.");

                    return null;
                }

                $itens['corp_device_id'] = $aparelhoId;
            }

            return $itens;
        }

        $equipamentoId = $this->idImportado(Equipment::class, $origem->equipamento_id);

        if ($equipamentoId === null) {
            $this->divergir('vinculos', $origem->id, $identificacao, "Equipamento #{$origem->equipamento_id} não foi importado. Vínculo não importado.");

            return null;
        }

        return ['equipment_id' => $equipamentoId];
    }

    private function gravarVinculo(string $tabelaOrigem, object $origem, string $escopo, int $colaboradorId, Carbon $dataVinculo, bool $ativo, string $identificacao): ?AssetAssignment
    {
        $termo = $this->copiarTermo($origem, $tabelaOrigem, $identificacao);

        $atributos = [
            'scope' => $escopo,
            'hr_collaborator_id' => $colaboradorId,
            'collaborator_matricula' => $origem->matricula,
            'data_vinculo' => $dataVinculo,
            'data_vencimento' => $origem->data_vencimento
                ? Carbon::parse($origem->data_vencimento)
                : $dataVinculo->copy()->addYears(AssetAssignment::VIGENCIA_ANOS),
            'observacoes' => $origem->observacoes ?? null,
            'ativo' => $ativo,
            'termo_assinado_path' => $termo,
            'termo_assinado_em' => $termo && ($origem->termo_assinado_em ?? null)
                ? Carbon::parse($origem->termo_assinado_em)
                : null,
            'motivo_desvinculo' => $origem->motivo_desvinculo ?? null,
            'observacoes_desvinculo' => $origem->observacoes_desvinculo ?? null,
            'data_desvinculo' => ($origem->data_desvinculo ?? null) ? Carbon::parse($origem->data_desvinculo) : null,
        ];

        $chave = ['origem_gestaoti_tabela' => $tabelaOrigem, 'origem_gestaoti_id' => $origem->id];

        if ($this->dryRun) {
            $existe = AssetAssignment::query()->where($chave)->exists();
            $this->conta('vinculos', $existe ? 'atualizados' : 'criados');

            // Em simulação a transação será desfeita, mas o registro precisa
            // existir agora para que os itens tenham a que se ligar.
            return AssetAssignment::query()->updateOrCreate($chave, $atributos);
        }

        $assignment = AssetAssignment::query()->updateOrCreate($chave, $atributos);
        $this->conta('vinculos', $assignment->wasRecentlyCreated ? 'criados' : 'atualizados');

        return $assignment;
    }

    /**
     * Copia o PDF do termo assinado para a área privada do CoreTI.
     *
     * Leitura do disco do gestaoTI, gravação só aqui. Em simulação nada é
     * copiado — o arquivo ficaria órfão quando a transação fosse desfeita.
     */
    private function copiarTermo(object $origem, string $tabelaOrigem, string $identificacao): ?string
    {
        $caminhoOrigem = $origem->termo_assinado_path ?? null;

        if (! $caminhoOrigem) {
            return null;
        }

        $absoluto = rtrim((string) config('gestaoti.storage_path'), '/').'/'.ltrim($caminhoOrigem, '/');

        if (! is_file($absoluto)) {
            $this->divergir('vinculos', $origem->id, $identificacao, "Termo assinado \"{$caminhoOrigem}\" não existe no disco do gestaoTI. Importado sem o anexo.", 'aviso');

            return null;
        }

        $destino = 'termos-vinculo/gestaoti-'.$tabelaOrigem.'-'.$origem->id.'.pdf';

        if ($this->dryRun) {
            return $destino;
        }

        Storage::disk('local')->put($destino, file_get_contents($absoluto));

        return $destino;
    }

    /**
     * @param  class-string  $modelo
     * @param  array<string, mixed>  $chave
     * @param  array<string, mixed>  $atributos
     */
    private function gravar(string $modelo, array $chave, array $atributos, string $etapa): void
    {
        $registro = $modelo::query()->updateOrCreate($chave, $atributos);
        $this->conta($etapa, $registro->wasRecentlyCreated ? 'criados' : 'atualizados');
    }

    /** @param  class-string  $modelo */
    private function idImportado(string $modelo, int|string|null $origemId): ?int
    {
        if ($origemId === null) {
            return null;
        }

        $id = $modelo::query()->where('origem_gestaoti_id', $origemId)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function escopo(int|string|null $tenantId): ?string
    {
        return self::TENANT_PARA_ESCOPO[(int) $tenantId] ?? null;
    }

    private function colaboradorEstaAtivo(int $colaboradorId): bool
    {
        return HrCollaborator::query()
            ->whereKey($colaboradorId)
            ->where('active', true)
            ->where('terminated', false)
            ->exists();
    }

    private function colaboradorPorMatricula(?string $matricula): ?int
    {
        if ($matricula === null || trim($matricula) === '') {
            return null;
        }

        $this->colaboradoresPorMatricula ??= HrCollaborator::query()
            ->pluck('id', 'employee_number')
            ->mapWithKeys(fn ($id, $mat) => [$this->normalizarMatricula((string) $mat) => (int) $id])
            ->all();

        return $this->colaboradoresPorMatricula[$this->normalizarMatricula($matricula)] ?? null;
    }

    /** Matrícula às vezes vem com zero à esquerda de um lado e não do outro. */
    private function normalizarMatricula(string $matricula): string
    {
        $digitos = preg_replace('/\D/', '', $matricula) ?: '';

        return ltrim($digitos, '0') ?: '0';
    }

    private function unidadePorFilial(?string $filial): ?int
    {
        $filial = trim((string) $filial);

        if ($filial === '') {
            return null;
        }

        $this->unidadesPorNome ??= OrganizationUnit::query()
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $nome) => [$this->normalizarNome((string) $nome) => (int) $id])
            ->all();

        $chave = $this->normalizarNome($filial);
        $chave = isset(self::ALIAS_FILIAL[$chave])
            ? $this->normalizarNome(self::ALIAS_FILIAL[$chave])
            : $chave;

        return $this->unidadesPorNome[$chave] ?? null;
    }

    /** Sem acento, sem caixa, sem espaço duplicado — "Silvânia" acha "Silvania". */
    private function normalizarNome(string $nome): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtolower(Str::ascii($nome)))) ?? '';
    }

    private function conta(string $etapa, string $chave): void
    {
        $this->contadores[$etapa][$chave] = ($this->contadores[$etapa][$chave] ?? 0) + 1;
    }

    private function divergir(string $etapa, int|string $origemId, string $identificacao, string $motivo, string $nivel = 'erro'): void
    {
        $this->divergencias[] = [
            'etapa' => $etapa,
            'nivel' => $nivel,
            'origem_id' => $origemId,
            'identificacao' => $identificacao,
            'motivo' => $motivo,
        ];

        $this->conta($etapa, $nivel === 'erro' ? 'nao_importados' : 'avisos');
    }
}
