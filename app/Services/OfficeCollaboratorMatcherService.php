<?php

namespace App\Services;

use App\Models\HrCollaborator;
use App\Models\OrganizationCostCenter;
use App\Models\OrganizationDepartment;
use App\Models\OrganizationUnit;
use App\Models\OrganizationPosition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OfficeCollaboratorMatcherService
{
    private const MIN_SIMILARITY = 80.0;
    private const MIN_DIFFERENCE = 5.0;
    private const CONNECTOR_TOKENS = ['de', 'da', 'do', 'das', 'dos', 'e'];

    /**
     * @var array<string, \\Illuminate\\Support\\Collection<int, array<string, mixed>>>
     */
    private array $collaboratorsBySource = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $metadataBySource = [];

    /** @var array<string, array<string, array<int, array<string, mixed>>>> */
    private array $indexesBySource = [];

    /** @var array<string, Collection<int, array<string, mixed>>> */
    private array $adIdentityMatches = [];

    /**
     * @return array<string, mixed>
     */
    public function match(?string $officeName, ?string $officeEmail = null): array
    {
        $officeName = trim((string) $officeName);
        $officeEmail = mb_strtolower(trim((string) $officeEmail));
        $normalizedOfficeName = $this->normalizeName($officeName);
        $simplifiedOfficeName = $this->simplifyName($officeName);

        if ($officeEmail !== '') {
            $emailResult = $this->matchEmailInSource('primary', $officeEmail, $normalizedOfficeName, $simplifiedOfficeName);
            if (($emailResult['status'] ?? null) === 'matched') {
                $emailResult['evaluated_sources'] = [
                    'hr_collaborators' => $this->compactSourceResult($emailResult),
                ];

                return $emailResult;
            }

            // O AD corporativo nem sempre possui o mesmo e-mail persistido
            // nas fontes de RH, mas mantém o login nome.sobrenome. Esse
            // identificador é a chave exata do usuário no Office.
            $adResult = $this->matchAdUserByIdentity($officeEmail, $normalizedOfficeName, $simplifiedOfficeName);
            if (($adResult['status'] ?? null) === 'matched') {
                $adResult['evaluated_sources'] = [
                    'ad_users' => $this->compactSourceResult($adResult),
                ];

                return $adResult;
            }
        }

        if ($normalizedOfficeName === '') {
            return $this->notFoundResult(
                'Nome do Office vazio ou inválido para comparação.',
                $normalizedOfficeName,
                $simplifiedOfficeName,
                0,
                null,
                null,
                null,
                []
            );
        }

        $primaryResult = $this->matchInSource('primary', $officeName, $normalizedOfficeName, $simplifiedOfficeName);
        $evaluatedSources = [
            'hr_collaborators' => $this->compactSourceResult($primaryResult),
        ];

        $fallbackResult = null;
        if ($this->shouldEvaluateFallback($primaryResult)) {
            $fallbackResult = $this->matchInSource('fallback', $officeName, $normalizedOfficeName, $simplifiedOfficeName);
            $fallbackView = (string) ($fallbackResult['source_view'] ?? 'vWcadColaboradorEmpresa');
            $evaluatedSources[$fallbackView] = $this->compactSourceResult($fallbackResult);
        }

        $final = $this->chooseBetweenSources($primaryResult, $fallbackResult, $evaluatedSources);
        $final['evaluated_sources'] = $evaluatedSources;

        return $final;
    }

    /**
     * @return array<string, mixed>
     */
    private function matchEmailInSource(string $sourceKey, string $officeEmail, string $normalizedOfficeName, string $simplifiedOfficeName): array
    {
        $metadata = $this->metadataForSource($sourceKey);
        $sourceView = (string) ($metadata['source_view'] ?? $metadata['source'] ?? 'hr_collaborators');
        $username = mb_strtolower(trim((string) Str::before($officeEmail, '@')));
        $indexes = $this->indexes($sourceKey);
        $matches = collect($indexes['email'][$officeEmail] ?? [])
            ->merge($username !== '' ? collect($indexes['username'][$username] ?? []) : collect())
            ->unique(fn (array $item): string => (string) ($item['local_id'] ?? $item['source_id'] ?? $item['nome'] ?? spl_object_id((object) $item)))
            ->values();

        if ($matches->count() === 1) {
            return $this->matchedResult(
                $matches->first(),
                100.0,
                'email',
                $normalizedOfficeName,
                $simplifiedOfficeName,
                1,
                null,
                'Correspondência por e-mail/login exato no snapshot local.',
                'email_exact',
                $sourceView
            );
        }

        if ($matches->count() > 1) {
            return $this->ambiguousResult(
                'E-mail/login do Office encontrou mais de um colaborador no snapshot local.',
                $matches->take(5)->map(fn (array $item): array => $this->candidatePayload($item, 100.0))->all(),
                100.0,
                $normalizedOfficeName,
                $simplifiedOfficeName,
                $matches->count(),
                $matches->first(),
                $matches->get(1),
                'email_ambiguous',
                $sourceView
            );
        }

        return $this->notFoundResult(
            'E-mail/login do Office não encontrado no snapshot local.',
            $normalizedOfficeName,
            $simplifiedOfficeName,
            0,
            null,
            null,
            $sourceView,
            []
        );
    }

    /**
     * Resolve o usuário pelo e-mail ou pelo login nome.sobrenome do AD.
     * O valor de unidade_setor é carregado como localização organizacional;
     * a resolução de centro/departamento continua sendo feita depois pelo
     * fluxo organizacional, sem inventar um centro de custo.
     *
     * @return array<string, mixed>
     */
    private function matchAdUserByIdentity(string $officeEmail, string $normalizedOfficeName, string $simplifiedOfficeName): array
    {
        $username = mb_strtolower(trim((string) Str::before($officeEmail, '@')));
        $query = DB::table('ad_users')
            ->where(function ($query) use ($officeEmail, $username): void {
                $query->whereRaw('LOWER(CONVERT(TRIM(email) USING utf8mb4)) = ?', [$officeEmail]);
                if ($username !== '') {
                    $query->orWhereRaw('LOWER(CONVERT(TRIM(nome_usuario) USING utf8mb4)) = ?', [$username]);
                }
            })
            ->orderByDesc('e_ativo')
            ->orderByDesc('id')
            ->get(['id', 'nome_completo', 'nome_usuario', 'email', 'unidade_setor', 'e_ativo']);

        $sourceView = 'ad_users';
        $items = $query->map(function (object $row) use ($sourceView): array {
            $location = $this->nullableString($row->unidade_setor ?? null);

            return [
                'source_view' => $sourceView,
                'local_id' => $row->id,
                'source_id' => null,
                'employee_number' => null,
                'email' => mb_strtolower(trim((string) ($row->email ?? ''))),
                'username' => mb_strtolower(trim((string) ($row->nome_usuario ?? ''))),
                'nome' => trim((string) ($row->nome_completo ?? $row->nome_usuario ?? '')),
                'nome_unicoop' => $location,
                'nome_setor' => $location,
                'ativo' => (int) ($row->e_ativo ?? 0),
                'desligado' => (int) (! ($row->e_ativo ?? false)),
                'is_preferred' => (bool) ($row->e_ativo ?? false),
            ];
        })->values();

        if ($items->count() === 1) {
            $item = $items->first();

            return $this->matchedResult(
                $item,
                100.0,
                'ad_username_exact',
                $normalizedOfficeName,
                $simplifiedOfficeName,
                1,
                null,
                'Correspondência por login nome.sobrenome exato no Active Directory.',
                'ad_username_exact',
                $sourceView
            );
        }

        if ($items->count() > 1) {
            return $this->ambiguousResult(
                'Login do Office encontrou mais de um usuário no Active Directory.',
                $items->take(5)->map(fn (array $item): array => $this->candidatePayload($item, 100.0))->all(),
                100.0,
                $normalizedOfficeName,
                $simplifiedOfficeName,
                $items->count(),
                $items->first(),
                $items->get(1),
                'ad_username_ambiguous',
                $sourceView
            );
        }

        return $this->notFoundResult(
            'Login do Office não encontrado no Active Directory.',
            $normalizedOfficeName,
            $simplifiedOfficeName,
            0,
            null,
            null,
            $sourceView,
            []
        );
    }

    public function normalizeName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        if ($value === '') {
            return '';
        }

        $value = Str::of($value)->ascii()->value();
        $value = preg_replace('/[^\pL\pN\s]+/u', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;

        return trim((string) $value);
    }

    public function simplifyName(?string $value): string
    {
        $normalized = $this->normalizeName($value);
        if ($normalized === '') {
            return '';
        }

        return collect(explode(' ', $normalized))
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => $token !== '' && ! in_array($token, self::CONNECTOR_TOKENS, true))
            ->implode(' ');
    }

    /**
     * Metadata da fonte principal para compatibilidade com comandos existentes.
     *
     * @return array<string, mixed>
     */
    public function sourceMetadata(): array
    {
        return $this->metadataForSource('primary');
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackSourceMetadata(): array
    {
        return $this->metadataForSource('fallback');
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnoseView(int $sampleLimit = 20, int $costCenterLimit = 30): array
    {
        $primary = $this->diagnoseSource('primary', $sampleLimit, $costCenterLimit);
        $fallback = $this->diagnoseSource('fallback', $sampleLimit, $costCenterLimit);

        return [
            'metadata' => $primary['metadata'],
            'counts' => $primary['counts'],
            'samples' => $primary['samples'],
            'cost_center_samples' => $primary['cost_center_samples'],
            'nome_setor_samples' => $primary['nome_setor_samples'],
            'fallback_metadata' => $fallback['metadata'],
            'fallback_counts' => $fallback['counts'],
            'fallback_samples' => $fallback['samples'],
            'fallback_cost_center_samples' => $fallback['cost_center_samples'],
            'fallback_nome_setor_samples' => $fallback['nome_setor_samples'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sourceDefinitions(): array
    {
        return [
            'primary' => [
                'label' => 'vWcadColaborador',
                'aliases' => ['vWcadColaborador'],
                'field_map' => [
                    'name_field' => ['Nome'],
                    'id_pessoa_field' => ['IdPessoa'],
                    'matricula_field' => ['Matricula'],
                    'centro_custo_field' => ['CentroCusto'],
                    'centro_custo_nome_field' => ['NomeSetor'],
                    'id_unicoop_field' => ['IdUnicoop'],
                    'nome_unicoop_field' => ['NomeUnicoop'],
                    'id_area_field' => ['IdArea'],
                    'nome_area_field' => ['NomeArea'],
                    'id_departamento_field' => ['IdDepartamento'],
                    'nome_departamento_field' => ['NomeDepartamento'],
                    'id_setor_field' => ['IdSetor'],
                    'nome_setor_field' => ['NomeSetor'],
                    'cargo_nome_field' => ['NomeCargo'],
                    'empresa_nome_field' => ['NomeEmpresa'],
                    'empresa_id_cocari_field' => ['IdEmpresaCocari'],
                    'ativo_field' => ['Ativo'],
                    'desligado_field' => ['Desligado'],
                    'data_desligamento_field' => ['DataDesligamento'],
                ],
            ],
            'fallback' => [
                'label' => 'vWcadColaboradorEmpresa',
                'aliases' => ['vwCadColaboradorEmpresa', 'vWcadColaboradorEmpresa'],
                'field_map' => [
                    'name_field' => ['Nome'],
                    'id_pessoa_field' => ['CodColaborador'],
                    'matricula_field' => ['CodColaborador'],
                    'centro_custo_field' => ['CentroCusto'],
                    'centro_custo_nome_field' => ['NomeDepartamento'],
                    'id_unicoop_field' => ['IdUnicoop'],
                    'nome_unicoop_field' => ['NomeUnidade'],
                    'id_area_field' => ['IdArea'],
                    'nome_area_field' => ['NomeArea'],
                    'id_departamento_field' => ['IdDepartamento'],
                    'nome_departamento_field' => ['NomeDepartamento'],
                    'id_setor_field' => ['IdSetor'],
                    'nome_setor_field' => ['NomeDepartamento'],
                    'cargo_nome_field' => ['NomeCargo'],
                    'empresa_nome_field' => ['NomeEmpresa'],
                    'empresa_id_cocari_field' => ['IdEmpresaCocari'],
                    'ativo_field' => ['Ativo'],
                    'desligado_field' => ['Desligado'],
                    'data_desligamento_field' => ['DataDesligamento'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataForSource(string $sourceKey): array
    {
        if (isset($this->metadataBySource[$sourceKey])) {
            return $this->metadataBySource[$sourceKey];
        }

        if ($sourceKey !== 'primary') {
            return $this->metadataBySource[$sourceKey] = [
                'source' => 'snapshot_local',
                'source_view' => null,
                'operation_source' => null,
                'columns' => [],
                'name_candidates' => [],
                'message' => 'Fallback externo desativado no cutover organizacional.',
            ];
        }

        return $this->metadataBySource[$sourceKey] = [
                'source' => 'hr_collaborators',
                'source_view' => 'hr_collaborators',
                'operation_source' => 'hr_collaborators',
                'columns' => ['source_id', 'employee_number', 'name', 'email', 'cost_center_source_id', 'unit_source_id', 'area_source_id', 'department_source_id'],
                'name_candidates' => [['name' => 'name', 'filled' => null]],
            'name_field' => 'nome',
            'id_pessoa_field' => 'matricula',
            'matricula_field' => 'matricula',
            'centro_custo_field' => 'centro_custo',
            'centro_custo_nome_field' => 'centro_custo_nome',
            'id_unicoop_field' => 'unicoop',
            'nome_unicoop_field' => 'unidade_nome',
            'id_area_field' => 'area',
            'nome_area_field' => 'area_nome',
            'id_departamento_field' => 'organization_department_id',
            'nome_departamento_field' => 'departamento_nome',
            'id_setor_field' => 'organization_unit_id',
            'nome_setor_field' => 'setor_nome',
            'cargo_nome_field' => 'cargo_nome',
            'empresa_nome_field' => 'empresa_nome',
            'empresa_id_cocari_field' => 'empresa_id_cocari',
            'ativo_field' => 'ativo',
            'desligado_field' => 'desligado',
            'data_desligamento_field' => 'data_desligamento',
            'message' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnoseSource(string $sourceKey, int $sampleLimit, int $costCenterLimit): array
    {
        $metadata = $this->metadataForSource($sourceKey);
        $collaborators = $this->collaborators($sourceKey);

        $counts = [
            'total' => 0,
            'name_filled' => 0,
            'id_pessoa_filled' => 0,
            'centro_custo_filled' => 0,
            'nome_setor_filled' => 0,
            'active_total' => 0,
            'not_disabled_total' => 0,
            'unique_names' => 0,
        ];

        $samples = [];
        $costCenterSamples = [];
        $nomeSetorSamples = [];

        $counts['total'] = $collaborators->count();
        $counts['name_filled'] = $collaborators->where('nome', '!=', null)->count();
        $counts['id_pessoa_filled'] = $collaborators->where('id_pessoa', '!=', null)->count();
        $counts['centro_custo_filled'] = $collaborators->where('centro_custo', '!=', null)->count();
        $counts['nome_setor_filled'] = $collaborators->where('nome_setor', '!=', null)->count();
        $counts['active_total'] = $collaborators->where('ativo', 1)->count();
        $counts['not_disabled_total'] = $counts['active_total'];
        $counts['unique_names'] = $collaborators->pluck('normalized_name')->unique()->count();
        $metadata['name_candidates'] = collect($metadata['name_candidates'] ?? [])
            ->map(fn (array $item): array => [
                'name' => $item['name'] ?? '-',
                'filled' => $counts['name_filled'],
            ])
            ->all();
        $samples = $collaborators->take($sampleLimit)->values()->all();
        $costCenterSamples = $collaborators->pluck('centro_custo')->filter()->unique()->take($costCenterLimit)->values()->all();
        $nomeSetorSamples = $collaborators->pluck('nome_setor')->filter()->unique()->take($costCenterLimit)->values()->all();

        return [
            'metadata' => $metadata,
            'counts' => $counts,
            'samples' => $samples,
            'cost_center_samples' => $costCenterSamples,
            'nome_setor_samples' => $nomeSetorSamples,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function matchInSource(string $sourceKey, string $officeName, string $normalizedOfficeName, string $simplifiedOfficeName): array
    {
        $metadata = $this->metadataForSource($sourceKey);
        $sourceView = $metadata['source'] ?? null;
        $collaborators = $this->collaborators($sourceKey);
        $indexes = $this->indexes($sourceKey);

        if ($sourceView === null || $collaborators->isEmpty()) {
            return $this->notFoundResult(
                'Nenhum colaborador disponível para comparação nesta fonte.',
                $normalizedOfficeName,
                $simplifiedOfficeName,
                0,
                null,
                null,
                $sourceView,
                []
            );
        }

        $exactMatches = $this->preferBestCandidates(collect($indexes['normalized_name'][$normalizedOfficeName] ?? []));

        if ($exactMatches->count() > 1) {
            $exactScored = $exactMatches->map(fn (array $item): array => $this->decorateExactCandidate($item, 100.0))->values();
            $exactDecision = $this->resolveBestCandidate($exactScored, $normalizedOfficeName, $simplifiedOfficeName, $exactMatches->count(), 'Nome exato normalizado', $sourceView);
            if ($exactDecision !== null) {
                return $exactDecision;
            }
        } elseif ($exactMatches->count() === 1) {
            $item = $this->decorateExactCandidate($exactMatches->first(), 100.0);

            return $this->matchedResult(
                $item,
                100.0,
                'exact',
                $normalizedOfficeName,
                $simplifiedOfficeName,
                1,
                null,
                ($item['is_preferred'] ?? false) ? 'Nome exato normalizado e colaborador ativo.' : 'Nome exato normalizado.',
                ($item['is_preferred'] ?? false) ? 'exact_active' : 'exact_name',
                $sourceView,
                [$this->candidatePayload($item, 100.0)]
            );
        }

        $simplifiedMatches = $this->preferBestCandidates(collect($indexes['simplified_name'][$simplifiedOfficeName] ?? []));

        if ($simplifiedMatches->count() > 1) {
            $simplifiedScored = $simplifiedMatches->map(fn (array $item): array => $this->decorateExactCandidate($item, 98.0))->values();
            $simplifiedDecision = $this->resolveBestCandidate($simplifiedScored, $normalizedOfficeName, $simplifiedOfficeName, $simplifiedMatches->count(), 'Nome simplificado', $sourceView);
            if ($simplifiedDecision !== null) {
                return $simplifiedDecision;
            }
        } elseif ($simplifiedMatches->count() === 1) {
            $item = $this->decorateExactCandidate($simplifiedMatches->first(), 98.0);

            return $this->matchedResult(
                $item,
                98.0,
                'simplified',
                $normalizedOfficeName,
                $simplifiedOfficeName,
                1,
                null,
                ($item['is_preferred'] ?? false) ? 'Nome simplificado equivalente e colaborador ativo.' : 'Nome simplificado equivalente.',
                ($item['is_preferred'] ?? false) ? 'active_priority' : 'simplified_name',
                $sourceView,
                [$this->candidatePayload($item, 98.0)]
            );
        }

        $candidates = $this->filterCandidatesByTokens($collaborators, $normalizedOfficeName, $simplifiedOfficeName, $sourceKey);
        $scored = $candidates
            ->map(fn (array $item): array => $this->scoreCandidate($item, $normalizedOfficeName, $simplifiedOfficeName))
            ->filter(fn (array $item): bool => $this->passesSafetyRules($item, $normalizedOfficeName, $simplifiedOfficeName))
            ->sortByDesc(fn (array $item): string => $this->decisionSortKey($item))
            ->values();

        $best = $scored->first();
        $second = $scored->get(1);
        $eligible = $scored->filter(fn (array $item): bool => (float) ($item['score'] ?? 0) >= $this->minimumSimilarityFor($normalizedOfficeName))->values();

        if ($eligible->isEmpty()) {
            return $this->notFoundResult(
                'Nenhum candidato com similaridade mínima e cobertura de tokens suficiente.',
                $normalizedOfficeName,
                $simplifiedOfficeName,
                $candidates->count(),
                $best,
                $second,
                $sourceView,
                $best ? [$this->candidatePayload($best, (float) ($best['score'] ?? 0))] : []
            );
        }

        $decision = $this->resolveBestCandidate($eligible, $normalizedOfficeName, $simplifiedOfficeName, $candidates->count(), 'Similaridade', $sourceView);
        if ($decision !== null) {
            return $decision;
        }

        $bestEligible = $eligible->first();

        return $this->matchedResult(
            $bestEligible,
            (float) ($bestEligible['score'] ?? 0),
            'similarity',
            $normalizedOfficeName,
            $simplifiedOfficeName,
            $candidates->count(),
            $second,
            'Colaborador encontrado com correspondência confiável.',
            'similarity',
            $sourceView,
            [$this->candidatePayload($bestEligible, (float) ($bestEligible['score'] ?? 0))]
        );
    }

    /**
     * @return \\Illuminate\\Support\\Collection<int, array<string, mixed>>
     */
    private function collaborators(string $sourceKey): Collection
    {
        if (isset($this->collaboratorsBySource[$sourceKey])) {
            return $this->collaboratorsBySource[$sourceKey];
        }

        $metadata = $this->metadataForSource($sourceKey);
        $source = $metadata['operation_source'] ?? null;
        $nameField = $metadata['name_field'] ?? null;
        if ($source === null || $nameField === null) {
            return $this->collaboratorsBySource[$sourceKey] = collect();
        }

        try {
            $rows = $this->localCollaboratorRows();

            $definitionLabel = (string) ($metadata['source'] ?? $source);
            $this->collaboratorsBySource[$sourceKey] = $rows
                ->map(function (object $row) use ($metadata, $definitionLabel): ?array {
                    $name = trim((string) data_get($row, (string) ($metadata['name_field'] ?? '')));
                    $normalizedName = $this->normalizeName($name);
                    if ($normalizedName === '') {
                        return null;
                    }

                    $simplifiedName = $this->simplifyName($name);
                    $ativo = $this->truthyFlag(data_get($row, (string) ($metadata['ativo_field'] ?? 'Ativo')));
                    $desligado = $this->resolveDisabledFlag(
                        data_get($row, (string) ($metadata['desligado_field'] ?? '')),
                        data_get($row, (string) ($metadata['data_desligamento_field'] ?? ''))
                    );

                    $centroCustoNome = $this->nullableString(data_get($row, (string) ($metadata['centro_custo_nome_field'] ?? '')))
                        ?? $this->nullableString(data_get($row, (string) ($metadata['nome_departamento_field'] ?? '')));
                    $nomeDepartamento = $this->nullableString(data_get($row, (string) ($metadata['nome_departamento_field'] ?? '')));
                    $nomeSetor = $this->nullableString(data_get($row, (string) ($metadata['nome_setor_field'] ?? '')))
                        ?? $nomeDepartamento
                        ?? $centroCustoNome;

                    return [
                        'source_view' => $definitionLabel,
                        'local_id' => $this->nullableString(data_get($row, 'local_id')),
                        'source_id' => $this->nullableString(data_get($row, 'source_id')),
                        'employee_number' => $this->nullableString(data_get($row, 'employee_number')),
                        'email' => mb_strtolower(trim((string) data_get($row, 'email'))),
                        'username' => mb_strtolower(trim((string) data_get($row, 'username'))),
                        'nome' => $name,
                        'normalized_name' => $normalizedName,
                        'simplified_name' => $simplifiedName,
                        'first_name' => $this->firstToken($simplifiedName),
                        'last_name' => $this->lastToken($simplifiedName),
                        'centro_custo' => $this->nullableString(data_get($row, (string) ($metadata['centro_custo_field'] ?? ''))),
                        'centro_custo_nome' => $centroCustoNome,
                        'id_pessoa' => $this->nullableString(data_get($row, (string) ($metadata['id_pessoa_field'] ?? ''))),
                        'matricula' => $this->nullableString(data_get($row, (string) ($metadata['matricula_field'] ?? ''))),
                        'id_unicoop' => $this->nullableString(data_get($row, (string) ($metadata['id_unicoop_field'] ?? ''))),
                        'nome_unicoop' => $this->nullableString(data_get($row, (string) ($metadata['nome_unicoop_field'] ?? ''))),
                        'id_area' => $this->nullableString(data_get($row, (string) ($metadata['id_area_field'] ?? ''))),
                        'nome_area' => $this->nullableString(data_get($row, (string) ($metadata['nome_area_field'] ?? ''))),
                        'id_departamento' => $this->nullableString(data_get($row, (string) ($metadata['id_departamento_field'] ?? ''))),
                        'nome_departamento' => $nomeDepartamento,
                        'id_setor' => $this->nullableString(data_get($row, (string) ($metadata['id_setor_field'] ?? ''))),
                        'nome_setor' => $nomeSetor,
                        'unit_source_id' => $this->nullableString(data_get($row, 'unit_source_id')),
                        'area_source_id' => $this->nullableString(data_get($row, 'area_source_id')),
                        'department_source_id' => $this->nullableString(data_get($row, 'department_source_id')),
                        'sector_source_id' => $this->nullableString(data_get($row, 'sector_source_id')),
                        'cost_center_source_id' => $this->nullableString(data_get($row, 'cost_center_source_id')),
                        'organization_unit_id' => data_get($row, 'organization_unit_id'),
                        'organization_department_id' => data_get($row, 'organization_department_id'),
                        'organization_cost_center_id' => data_get($row, 'organization_cost_center_id'),
                        'cargo_nome' => $this->nullableString(data_get($row, (string) ($metadata['cargo_nome_field'] ?? ''))),
                        'empresa_nome' => $this->nullableString(data_get($row, (string) ($metadata['empresa_nome_field'] ?? ''))),
                        'empresa_id_cocari' => $this->nullableString(data_get($row, (string) ($metadata['empresa_id_cocari_field'] ?? ''))),
                        'data_desligamento' => $this->nullableString(data_get($row, (string) ($metadata['data_desligamento_field'] ?? ''))),
                        'ativo' => $ativo ? 1 : 0,
                        'desligado' => $desligado ? 1 : 0,
                        'is_preferred' => $ativo && ! $desligado,
                    ];
                })
                ->filter()
                ->sortByDesc(fn (array $item): string => sprintf('%d|%d|%s', (int) ($item['is_preferred'] ?? 0), 1 - (int) ($item['desligado'] ?? 0), (string) ($item['nome'] ?? '')))
                ->unique(fn (array $item): string => ($item['id_pessoa'] ?? '') !== '' ? 'id:' . $item['id_pessoa'] : 'name:' . $item['normalized_name'] . '|' . ($item['centro_custo'] ?? ''))
                ->values();
        } catch (\Throwable $e) {
            Log::warning('office collaborator load unavailable', [
                'source' => $metadata['source'] ?? $sourceKey,
                'error' => $e->getMessage(),
            ]);

            $this->collaboratorsBySource[$sourceKey] = collect();
        }

        return $this->collaboratorsBySource[$sourceKey];
    }

    /**
     * Indexa identificadores usados pelo caminho mais frequente do matcher.
     * A lista completa continua disponível para a busca segura por similaridade.
     *
     * @return array<string, array<string, array<int, array<string, mixed>>>>
     */
    private function indexes(string $sourceKey): array
    {
        if (isset($this->indexesBySource[$sourceKey])) {
            return $this->indexesBySource[$sourceKey];
        }

        $indexes = [
            'email' => [],
            'username' => [],
            'normalized_name' => [],
            'simplified_name' => [],
            'token' => [],
        ];

        foreach ($this->collaborators($sourceKey) as $item) {
            foreach (['email', 'username', 'normalized_name', 'simplified_name'] as $field) {
                $value = mb_strtolower(trim((string) ($item[$field] ?? '')));
                if ($value === '') {
                    continue;
                }
                $indexes[$field][$value][] = $item;
            }
            foreach ($this->relevantTokens((string) ($item['simplified_name'] ?? '')) as $token) {
                $indexes['token'][$token][] = $item;
            }
        }

        return $this->indexesBySource[$sourceKey] = $indexes;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function localCollaboratorRows(): Collection
    {
        $costCentersBySourceId = OrganizationCostCenter::query()
            ->where('source_system', 'intranet')
            ->get()
            ->groupBy('source_id')
            ->map(function (Collection $rows): ?OrganizationCostCenter {
                return $rows->sortBy(fn (OrganizationCostCenter $row): int => $row->source_type === 'sic' ? 0 : 1)->first();
            });

        $unitsBySourceId = OrganizationUnit::query()
            ->where('source_system', 'intranet')
            ->get()
            ->keyBy(fn (OrganizationUnit $unit): string => (string) $unit->source_id);

        $departmentsBySourceId = OrganizationDepartment::query()
            ->where('source_system', 'intranet')
            ->get()
            ->keyBy(fn (OrganizationDepartment $department): string => (string) $department->source_id);

        $departmentsByArea = OrganizationDepartment::query()
            ->where('source_system', 'intranet')
            ->whereNotNull('source_area_id')
            ->get()
            ->keyBy(fn (OrganizationDepartment $department): string => (string) $department->source_area_id);

        $positionsBySourceId = Schema::hasTable('organization_positions')
            ? OrganizationPosition::query()->where('source_system', 'intranet')->get()->keyBy(fn (OrganizationPosition $position): string => (string) $position->source_id)
            : collect();

        $googleByUsername = DB::table('coreti_google_emails')
            ->select([
                'email',
                'nome',
                'nome_usuario',
                'matricula',
                'nome_local',
                'centro_custo',
                'centro_custo_nome',
                'unicoop',
                'area',
                'mapeamento_status',
            ])
            ->get()
            ->keyBy(fn (object $row): string => mb_strtolower(trim((string) ($row->nome_usuario ?: Str::before((string) $row->email, '@')))));

        return HrCollaborator::query()
            ->where('source_system', 'intranet')
            ->orderBy('name')
            ->get([
                'id',
                'source_id',
                'employee_number',
                'name',
                'email',
                'cost_center_source_id',
                'unit_source_id',
                'area_source_id',
                'department_source_id',
                'sector_source_id',
                'position_source_id',
                'company_source_id',
                'active',
                'terminated',
                'termination_date',
                'raw_data',
            ])
            ->map(function (HrCollaborator $collaborator) use ($costCentersBySourceId, $unitsBySourceId, $departmentsBySourceId, $departmentsByArea, $positionsBySourceId, $googleByUsername): object {
                $email = mb_strtolower(trim((string) ($collaborator->email ?? '')));
                $username = mb_strtolower(trim((string) Str::before($email, '@')));
                $google = $googleByUsername->get($username);
                $costCenter = $collaborator->cost_center_source_id
                    ? $costCentersBySourceId->get((string) $collaborator->cost_center_source_id)
                    : null;
                $unitSourceId = $this->nullableString($costCenter?->unit_source_id)
                    ?? $this->nullableString($collaborator->unit_source_id);
                $areaSourceId = $this->nullableString($costCenter?->accounting_area_source_id)
                    ?? $this->nullableString($collaborator->area_source_id);
                if (! $costCenter && $unitSourceId !== null && $areaSourceId !== null) {
                    $derivedCostCenterSourceId = ((string) ((int) $unitSourceId)) . str_pad((string) ((int) $areaSourceId), 3, '0', STR_PAD_LEFT);
                    $costCenter = $costCentersBySourceId->get($derivedCostCenterSourceId);
                }
                $unit = $unitSourceId ? $unitsBySourceId->get($unitSourceId) : null;
                $department = $collaborator->department_source_id
                    ? $departmentsBySourceId->get((string) $collaborator->department_source_id)
                    : null;
                $department ??= $areaSourceId ? $departmentsByArea->get($areaSourceId) : null;
                $position = $collaborator->position_source_id ? $positionsBySourceId->get((string) $collaborator->position_source_id) : null;
                $raw = is_array($collaborator->raw_data) ? $collaborator->raw_data : [];
                $cargoName = $this->nullableString($position?->name)
                    ?? $this->nullableString($raw['NomeCargo'] ?? $raw['cargo_nome'] ?? null);

                return (object) [
                    'local_id' => $collaborator->id,
                    'source_id' => $collaborator->source_id,
                    'employee_number' => $collaborator->employee_number,
                    'email' => $email,
                    'username' => $username,
                    'nome' => $collaborator->name ?: $google?->nome,
                    'matricula' => $collaborator->source_id ?: $collaborator->employee_number ?: $google?->matricula,
                    'centro_custo' => $this->formatCostCenter($unitSourceId, $areaSourceId)
                        ?? $this->nullableString($collaborator->cost_center_source_id)
                        ?? $google?->centro_custo,
                    'centro_custo_nome' => $this->nullableString($costCenter?->name)
                        ?? $this->nullableString($department?->name)
                        ?? $google?->centro_custo_nome
                        ?? $google?->nome_local,
                    'unicoop' => $unitSourceId,
                    'area' => $areaSourceId,
                    'unidade_nome' => $this->nullableString($unit?->name),
                    'area_nome' => $this->nullableString($department?->name),
                    'organization_unit_id' => $unit?->id,
                    'organization_department_id' => $department?->id,
                    'organization_cost_center_id' => $costCenter?->id,
                    'organization_position_id' => $position?->id,
                    'unit_source_id' => $this->nullableString($collaborator->unit_source_id),
                    'area_source_id' => $this->nullableString($collaborator->area_source_id),
                    'department_source_id' => $this->nullableString($collaborator->department_source_id),
                    'sector_source_id' => $this->nullableString($collaborator->sector_source_id),
                    'cost_center_source_id' => $this->nullableString($collaborator->cost_center_source_id),
                    'departamento_nome' => $this->nullableString($department?->name),
                    'setor_nome' => $this->nullableString($department?->name) ?? $this->nullableString($costCenter?->name),
                    'cargo_nome' => $cargoName,
                    'empresa_nome' => null,
                    'empresa_id_cocari' => $this->nullableString($collaborator->company_source_id),
                    'ativo' => $collaborator->active && ! $collaborator->terminated ? 1 : 0,
                    'desligado' => $collaborator->terminated ? 1 : 0,
                    'data_desligamento' => $collaborator->termination_date?->toDateString(),
                ];
            });
    }

    private function formatCostCenter(?string $unitSourceId, ?string $areaSourceId): ?string
    {
        $unitSourceId = $this->nullableString($unitSourceId);
        $areaSourceId = $this->nullableString($areaSourceId);

        if ($unitSourceId === null || $areaSourceId === null) {
            return null;
        }

        return str_pad($unitSourceId, 2, '0', STR_PAD_LEFT) . '.' . str_pad($areaSourceId, 3, '0', STR_PAD_LEFT);
    }

    private function shouldEvaluateFallback(array $primaryResult): bool
    {
        if (($primaryResult['status'] ?? null) === 'not_found') {
            return true;
        }

        if (($primaryResult['status'] ?? null) === 'ambiguous') {
            return true;
        }

        $collaborator = $primaryResult['collaborator'] ?? null;

        return is_array($collaborator) && ! (($collaborator['ativo'] ?? 0) === 1 && ($collaborator['desligado'] ?? 0) === 0);
    }

    /**
     * @param array<string, mixed>|null $fallbackResult
     * @param array<string, array<string, mixed>> $evaluatedSources
     * @return array<string, mixed>
     */
    private function chooseBetweenSources(array $primaryResult, ?array $fallbackResult, array $evaluatedSources): array
    {
        if ($fallbackResult === null) {
            $primaryResult['evaluated_sources'] = $evaluatedSources;

            return $primaryResult;
        }

        if (($primaryResult['status'] ?? null) === 'matched' && $this->isPreferredActiveMatch($primaryResult)) {
            $primaryResult['evaluated_sources'] = $evaluatedSources;

            return $primaryResult;
        }

        if (($primaryResult['status'] ?? null) === 'not_found' && ($fallbackResult['status'] ?? null) !== 'not_found') {
            $fallbackResult['evaluated_sources'] = $evaluatedSources;

            return $fallbackResult;
        }

        if (($primaryResult['status'] ?? null) === 'ambiguous' && $this->isPreferredActiveMatch($fallbackResult)) {
            $fallbackResult['evaluated_sources'] = $evaluatedSources;

            return $fallbackResult;
        }

        if (($primaryResult['status'] ?? null) === 'matched' && ! $this->isPreferredActiveMatch($primaryResult) && $this->isExactActiveMatch($fallbackResult)) {
            $fallbackResult['evaluated_sources'] = $evaluatedSources;

            return $fallbackResult;
        }

        if (($primaryResult['status'] ?? null) === 'not_found') {
            $fallbackResult['evaluated_sources'] = $evaluatedSources;

            return $fallbackResult;
        }

        $primaryResult['evaluated_sources'] = $evaluatedSources;

        return $primaryResult;
    }

    private function isPreferredActiveMatch(?array $result): bool
    {
        if (($result['status'] ?? null) !== 'matched' || ! is_array($result['collaborator'] ?? null)) {
            return false;
        }

        $collaborator = $result['collaborator'];

        return ((int) ($collaborator['ativo'] ?? 0) === 1) && ((int) ($collaborator['desligado'] ?? 0) === 0);
    }

    private function isExactActiveMatch(?array $result): bool
    {
        return ($result['status'] ?? null) === 'matched' && in_array((string) ($result['decision_code'] ?? ''), ['exact_active', 'exact_name'], true) && $this->isPreferredActiveMatch($result);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function compactSourceResult(array $result): array
    {
        return [
            'status' => $result['status'] ?? null,
            'score' => $result['score'] ?? null,
            'decision_code' => $result['decision_code'] ?? null,
            'source_view' => $result['source_view'] ?? null,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $collaborators
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function filterCandidatesByTokens(Collection $collaborators, string $normalizedOfficeName, string $simplifiedOfficeName, ?string $sourceKey = null): Collection
    {
        $tokens = $this->relevantTokens($simplifiedOfficeName !== '' ? $simplifiedOfficeName : $normalizedOfficeName);
        if ($tokens->isEmpty()) {
            return $this->preferBestCandidates($collaborators);
        }

        $required = collect([$tokens->first(), $tokens->last()])->filter()->unique()->values();

        // Use o indice de tokens criado uma vez por execucao. O fallback
        // abaixo preserva a busca por substring quando nao houver indice.
        if ($sourceKey !== null) {
            $tokenIndex = $this->indexes($sourceKey)['token'] ?? [];
            $indexed = null;
            foreach ($required as $token) {
                $set = collect($tokenIndex[$token] ?? [])
                    ->keyBy(fn (array $item): string => (string) ($item['local_id'] ?? $item['source_id'] ?? $item['nome'] ?? ''));
                if ($set->isEmpty()) {
                    $indexed = collect();
                    break;
                }
                $indexed = $indexed === null ? $set : $indexed->intersectByKeys($set);
            }
            if ($indexed !== null && $indexed->isNotEmpty()) {
                return $this->preferBestCandidates($indexed->values());
            }
        }

        $filtered = $collaborators->filter(function (array $item) use ($required): bool {
            foreach ($required as $token) {
                if (! str_contains((string) ($item['simplified_name'] ?? ''), $token) && ! str_contains((string) ($item['normalized_name'] ?? ''), $token)) {
                    return false;
                }
            }

            return true;
        })->values();

        if ($filtered->isNotEmpty()) {
            return $this->preferBestCandidates($filtered);
        }

        return $this->preferBestCandidates(
            $collaborators->filter(function (array $item) use ($tokens): bool {
                $matches = 0;
                foreach ($tokens as $token) {
                    if (str_contains((string) ($item['simplified_name'] ?? ''), $token) || str_contains((string) ($item['normalized_name'] ?? ''), $token)) {
                        $matches++;
                    }
                }

                return $matches >= min(2, max(1, $tokens->count()));
            })->values()
        );
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $items
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function preferBestCandidates(Collection $items): Collection
    {
        return $items
            ->sortByDesc(fn (array $item): string => sprintf('%d|%d|%s', (int) ($item['is_preferred'] ?? 0), 1 - (int) ($item['desligado'] ?? 0), (string) ($item['nome'] ?? '')))
            ->values();
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function scoreCandidate(array $candidate, string $normalizedOfficeName, string $simplifiedOfficeName): array
    {
        $comparisonTarget = $simplifiedOfficeName !== '' ? $simplifiedOfficeName : $normalizedOfficeName;
        $candidateTarget = ($candidate['simplified_name'] ?? '') !== '' ? (string) $candidate['simplified_name'] : (string) $candidate['normalized_name'];

        similar_text($normalizedOfficeName, (string) ($candidate['normalized_name'] ?? ''), $normalizedScore);
        similar_text($comparisonTarget, $candidateTarget, $simplifiedScore);

        $officeTokens = $this->relevantTokens($comparisonTarget);
        $candidateTokens = $this->relevantTokens($candidateTarget);
        $sharedTokens = $officeTokens->intersect($candidateTokens)->values();
        $tokenCoverage = $officeTokens->count() > 0
            ? round(($sharedTokens->count() / $officeTokens->count()) * 100, 2)
            : 0.0;

        $firstNameMatch = $this->firstToken($comparisonTarget) !== '' && $this->firstToken($comparisonTarget) === ($candidate['first_name'] ?? '');
        $lastNameMatch = $this->lastToken($comparisonTarget) !== '' && $this->lastToken($comparisonTarget) === ($candidate['last_name'] ?? '');

        $score = max((float) $normalizedScore, (float) $simplifiedScore);
        if ($firstNameMatch) {
            $score += 2.5;
        }
        if ($lastNameMatch) {
            $score += 3.5;
        }
        if ($tokenCoverage >= 75) {
            $score += 2.0;
        } elseif ($tokenCoverage >= 50) {
            $score += 1.0;
        }
        if (($candidate['is_preferred'] ?? false) === true) {
            $score += 1.0;
        } elseif (($candidate['desligado'] ?? 0) === 1) {
            $score -= 2.0;
        }

        $candidate['raw_score'] = round((float) $normalizedScore, 2);
        $candidate['simplified_score'] = round((float) $simplifiedScore, 2);
        $candidate['token_coverage'] = round((float) $tokenCoverage, 2);
        $candidate['first_name_match'] = $firstNameMatch;
        $candidate['last_name_match'] = $lastNameMatch;
        $candidate['exact_normalized_match'] = ($candidate['normalized_name'] ?? '') === $normalizedOfficeName;
        $candidate['exact_simplified_match'] = ($candidate['simplified_name'] ?? '') === $simplifiedOfficeName;
        $candidate['matched_tokens'] = $sharedTokens->count();
        $candidate['score'] = round(min(100.0, $score), 2);

        return $candidate;
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $candidates
     * @return array<string, mixed>|null
     */
    private function resolveBestCandidate(Collection $candidates, string $normalizedOfficeName, string $simplifiedOfficeName, int $likeCandidatesCount, string $context, ?string $sourceView): ?array
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        $bestPool = $candidates;
        $decisionCode = 'similarity';
        $decisionReason = $context . ' com melhor compatibilidade geral.';

        $activePool = $bestPool->where('ativo', 1)->values();
        if ($activePool->isNotEmpty()) {
            $bestPool = $activePool;
            $decisionCode = 'active_priority';
            $decisionReason = 'Colaborador ativo priorizado.';
        }

        $notDisabledPool = $bestPool->where('desligado', 0)->values();
        if ($notDisabledPool->isNotEmpty()) {
            $bestPool = $notDisabledPool;
            $decisionReason = $decisionCode === 'active_priority'
                ? 'Colaborador ativo e não desligado priorizado.'
                : 'Colaborador não desligado priorizado.';
        }

        $exactActive = $bestPool->filter(fn (array $item): bool => ($item['normalized_name'] ?? '') === $normalizedOfficeName)->values();
        if ($exactActive->count() === 1) {
            $winner = $exactActive->first();

            return $this->matchedResult(
                $winner,
                (float) ($winner['score'] ?? 100.0),
                'exact',
                $normalizedOfficeName,
                $simplifiedOfficeName,
                $likeCandidatesCount,
                $bestPool->get(1),
                'Nome exato normalizado e colaborador ativo.',
                'exact_active',
                $sourceView,
                [$this->candidatePayload($winner, (float) ($winner['score'] ?? 100.0))]
            );
        }

        $ranked = $bestPool->sortByDesc(fn (array $item): string => $this->decisionSortKey($item))->values();
        $winner = $ranked->first();
        $runnerUp = $ranked->get(1);

        if ($winner === null) {
            return null;
        }

        if ($runnerUp === null) {
            return $this->matchedResult(
                $winner,
                (float) ($winner['score'] ?? 0),
                'similarity',
                $normalizedOfficeName,
                $simplifiedOfficeName,
                $likeCandidatesCount,
                null,
                $this->buildDecisionReason($winner, $decisionReason),
                $decisionCode,
                $sourceView,
                [$this->candidatePayload($winner, (float) ($winner['score'] ?? 0))]
            );
        }

        $samePriority = $this->decisionRankTuple($winner) === $this->decisionRankTuple($runnerUp);
        $scoreDifference = (float) ($winner['score'] ?? 0) - (float) ($runnerUp['score'] ?? 0);

        if ($samePriority && $scoreDifference < self::MIN_DIFFERENCE) {
            $ambiguousCode = ((int) ($winner['ativo'] ?? 0) === 1 && (int) ($runnerUp['ativo'] ?? 0) === 1)
                ? 'ambiguous_active_tie'
                : 'ambiguous_equivalent_score';

            return $this->ambiguousResult(
                'Dois candidatos equivalentes permaneceram após priorização por ativo, desligamento, tokens e score.',
                $ranked->take(5)->map(fn (array $item): array => $this->candidatePayload($item, (float) ($item['score'] ?? 0)))->all(),
                (float) ($winner['score'] ?? 0),
                $normalizedOfficeName,
                $simplifiedOfficeName,
                $likeCandidatesCount,
                $winner,
                $runnerUp,
                $ambiguousCode,
                $sourceView
            );
        }

        return $this->matchedResult(
            $winner,
            (float) ($winner['score'] ?? 0),
            'similarity',
            $normalizedOfficeName,
            $simplifiedOfficeName,
            $likeCandidatesCount,
            $runnerUp,
            $this->buildDecisionReason($winner, $decisionReason),
            $decisionCode,
            $sourceView,
            [$this->candidatePayload($winner, (float) ($winner['score'] ?? 0)), $this->candidatePayload($runnerUp, (float) ($runnerUp['score'] ?? 0))]
        );
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function decorateExactCandidate(array $candidate, float $score): array
    {
        $candidate['raw_score'] = $score;
        $candidate['simplified_score'] = $score;
        $candidate['token_coverage'] = 100.0;
        $candidate['first_name_match'] = true;
        $candidate['last_name_match'] = true;
        $candidate['exact_normalized_match'] = true;
        $candidate['exact_simplified_match'] = true;
        $candidate['matched_tokens'] = count(array_filter(explode(' ', (string) ($candidate['simplified_name'] ?? ''))));
        $candidate['score'] = $score;

        return $candidate;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function decisionSortKey(array $item): string
    {
        return sprintf(
            '%d|%d|%d|%d|%d|%03d|%08.2f|%08.2f|%s',
            (int) ($item['exact_normalized_match'] ?? false),
            (int) ($item['ativo'] ?? 0),
            1 - (int) ($item['desligado'] ?? 0),
            (int) ($item['first_name_match'] ?? false),
            (int) ($item['last_name_match'] ?? false),
            (int) round((float) ($item['token_coverage'] ?? 0)),
            (float) ($item['score'] ?? 0),
            (float) ($item['raw_score'] ?? 0),
            (string) ($item['nome'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function decisionRankTuple(array $item): string
    {
        return implode('|', [
            (int) ($item['exact_normalized_match'] ?? false),
            (int) ($item['ativo'] ?? 0),
            1 - (int) ($item['desligado'] ?? 0),
            (int) ($item['first_name_match'] ?? false),
            (int) ($item['last_name_match'] ?? false),
            (int) round((float) ($item['token_coverage'] ?? 0)),
            (int) ($item['matched_tokens'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function buildDecisionReason(array $item, string $prefix): string
    {
        $parts = [$prefix];

        if (($item['exact_normalized_match'] ?? false) === true) {
            $parts[] = 'Nome exato normalizado.';
        } elseif (($item['first_name_match'] ?? false) === true && ($item['last_name_match'] ?? false) === true) {
            $parts[] = 'Mesmo primeiro nome e último sobrenome.';
        }

        $parts[] = sprintf('Cobertura de tokens %.2f%% e score %.2f%%.', (float) ($item['token_coverage'] ?? 0), (float) ($item['score'] ?? 0));

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function passesSafetyRules(array $candidate, string $normalizedOfficeName, string $simplifiedOfficeName): bool
    {
        $comparisonTarget = $simplifiedOfficeName !== '' ? $simplifiedOfficeName : $normalizedOfficeName;
        $tokenCount = $this->relevantTokens($comparisonTarget)->count();
        $minimumSimilarity = $this->minimumSimilarityFor($normalizedOfficeName);

        if ((float) ($candidate['score'] ?? 0) < $minimumSimilarity) {
            return false;
        }
        if ($tokenCount >= 2 && ! ($candidate['first_name_match'] ?? false)) {
            return false;
        }
        if ($tokenCount >= 3 && ! ($candidate['last_name_match'] ?? false) && (float) ($candidate['token_coverage'] ?? 0) < 75.0) {
            return false;
        }
        if ($tokenCount <= 2 && (float) ($candidate['token_coverage'] ?? 0) < 100.0) {
            return false;
        }

        return true;
    }

    private function minimumSimilarityFor(string $normalizedOfficeName): float
    {
        $tokenCount = $this->relevantTokens($normalizedOfficeName)->count();

        return $tokenCount <= 2 ? 90.0 : self::MIN_SIMILARITY;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<int, string>
     */
    private function buildSelectFields(array $metadata): array
    {
        return collect([
            $metadata['name_field'] ?? null,
            $metadata['id_pessoa_field'] ?? null,
            $metadata['matricula_field'] ?? null,
            $metadata['centro_custo_field'] ?? null,
            $metadata['centro_custo_nome_field'] ?? null,
            $metadata['id_unicoop_field'] ?? null,
            $metadata['nome_unicoop_field'] ?? null,
            $metadata['id_area_field'] ?? null,
            $metadata['nome_area_field'] ?? null,
            $metadata['id_departamento_field'] ?? null,
            $metadata['nome_departamento_field'] ?? null,
            $metadata['id_setor_field'] ?? null,
            $metadata['nome_setor_field'] ?? null,
            $metadata['cargo_nome_field'] ?? null,
            $metadata['empresa_nome_field'] ?? null,
            $metadata['empresa_id_cocari_field'] ?? null,
            $metadata['ativo_field'] ?? null,
            $metadata['desligado_field'] ?? null,
            $metadata['data_desligamento_field'] ?? null,
        ])->filter()->unique()->values()->all();
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed>|null $second
     * @param array<int, array<string, mixed>> $candidates
     * @return array<string, mixed>
     */
    private function matchedResult(array $item, float $score, string $type, string $normalizedOfficeName, string $simplifiedOfficeName, int $likeCandidatesCount, ?array $second = null, ?string $message = null, ?string $decisionCode = null, ?string $sourceView = null, array $candidates = []): array
    {
        $bestCandidate = $this->candidatePayload($item, $score);
        $message ??= 'Colaborador encontrado com correspondência confiável.';
        if (! ($item['is_preferred'] ?? false) && ! str_contains($message, 'fora do conjunto preferencial')) {
            $message .= ' Registro encontrado fora do conjunto preferencial de ativos.';
        }

        return [
            'status' => 'matched',
            'type' => $type,
            'decision_code' => $decisionCode ?? $type,
            'score' => round($score, 2),
            'message' => $message,
            'source' => $sourceView,
            'source_view' => $sourceView,
            'source_name_field' => $this->metadataForSource(in_array($sourceView, ['hr_collaborators', 'vWcadColaborador'], true) ? 'primary' : 'fallback')['name_field'] ?? null,
            'normalized_office_name' => $normalizedOfficeName,
            'simplified_office_name' => $simplifiedOfficeName,
            'like_candidates_count' => $likeCandidatesCount,
            'best_candidate' => $bestCandidate,
            'second_candidate' => $second ? $this->candidatePayload($second, (float) ($second['score'] ?? 0)) : null,
            'collaborator' => $this->collaboratorPayload($item),
            'candidates' => $candidates !== [] ? $candidates : [$bestCandidate],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, mixed>|null $best
     * @param array<string, mixed>|null $second
     * @return array<string, mixed>
     */
    private function ambiguousResult(string $message, array $candidates, ?float $score, string $normalizedOfficeName, string $simplifiedOfficeName, int $likeCandidatesCount, ?array $best = null, ?array $second = null, ?string $decisionCode = null, ?string $sourceView = null): array
    {
        return [
            'status' => 'ambiguous',
            'type' => 'ambiguous',
            'decision_code' => $decisionCode ?? 'ambiguous',
            'score' => $score !== null ? round($score, 2) : null,
            'message' => $message,
            'source' => $sourceView,
            'source_view' => $sourceView,
            'source_name_field' => $this->metadataForSource(in_array($sourceView, ['hr_collaborators', 'vWcadColaborador'], true) ? 'primary' : 'fallback')['name_field'] ?? null,
            'normalized_office_name' => $normalizedOfficeName,
            'simplified_office_name' => $simplifiedOfficeName,
            'like_candidates_count' => $likeCandidatesCount,
            'best_candidate' => $best ? $this->candidatePayload($best, (float) ($best['score'] ?? 0)) : ($candidates[0] ?? null),
            'second_candidate' => $second ? $this->candidatePayload($second, (float) ($second['score'] ?? 0)) : ($candidates[1] ?? null),
            'collaborator' => null,
            'candidates' => $candidates,
        ];
    }

    /**
     * @param array<string, mixed>|null $best
     * @param array<string, mixed>|null $second
     * @param array<int, array<string, mixed>> $candidates
     * @return array<string, mixed>
     */
    private function notFoundResult(string $message, string $normalizedOfficeName, string $simplifiedOfficeName, int $likeCandidatesCount, ?array $best, ?array $second, ?string $sourceView, array $candidates): array
    {
        return [
            'status' => 'not_found',
            'type' => 'similarity',
            'decision_code' => 'not_found',
            'score' => $best['score'] ?? null,
            'message' => $message,
            'source' => $sourceView,
            'source_view' => $sourceView,
            'source_name_field' => $sourceView ? ($this->metadataForSource(in_array($sourceView, ['hr_collaborators', 'vWcadColaborador'], true) ? 'primary' : 'fallback')['name_field'] ?? null) : null,
            'normalized_office_name' => $normalizedOfficeName,
            'simplified_office_name' => $simplifiedOfficeName,
            'like_candidates_count' => $likeCandidatesCount,
            'best_candidate' => $best ? $this->candidatePayload($best, (float) ($best['score'] ?? 0)) : null,
            'second_candidate' => $second ? $this->candidatePayload($second, (float) ($second['score'] ?? 0)) : null,
            'collaborator' => null,
            'candidates' => $candidates,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function collaboratorPayload(array $item): array
    {
        return [
            'source_view' => $item['source_view'] ?? null,
            'local_id' => $this->nullableString($item['local_id'] ?? null),
            'source_id' => $this->nullableString($item['source_id'] ?? null),
            'employee_number' => $this->nullableString($item['employee_number'] ?? null),
            'email' => $this->nullableString($item['email'] ?? null),
            'nome' => (string) ($item['nome'] ?? ''),
            'centro_custo' => $this->nullableString($item['centro_custo'] ?? null),
            'centro_custo_nome' => $this->nullableString($item['centro_custo_nome'] ?? null),
            'id_pessoa' => $this->nullableString($item['id_pessoa'] ?? null),
            'matricula' => $this->nullableString($item['matricula'] ?? null),
            'id_unicoop' => $this->nullableString($item['id_unicoop'] ?? null),
            'nome_unicoop' => $this->nullableString($item['nome_unicoop'] ?? null),
            'id_area' => $this->nullableString($item['id_area'] ?? null),
            'nome_area' => $this->nullableString($item['nome_area'] ?? null),
            'id_departamento' => $this->nullableString($item['id_departamento'] ?? null),
            'nome_departamento' => $this->nullableString($item['nome_departamento'] ?? null),
            'id_setor' => $this->nullableString($item['id_setor'] ?? null),
            'nome_setor' => $this->nullableString($item['nome_setor'] ?? null),
            'unit_source_id' => $this->nullableString($item['unit_source_id'] ?? null),
            'area_source_id' => $this->nullableString($item['area_source_id'] ?? null),
            'department_source_id' => $this->nullableString($item['department_source_id'] ?? null),
            'sector_source_id' => $this->nullableString($item['sector_source_id'] ?? null),
            'cost_center_source_id' => $this->nullableString($item['cost_center_source_id'] ?? null),
            'organization_unit_id' => $item['organization_unit_id'] ?? null,
            'organization_department_id' => $item['organization_department_id'] ?? null,
            'organization_cost_center_id' => $item['organization_cost_center_id'] ?? null,
            'cargo_nome' => $this->nullableString($item['cargo_nome'] ?? null),
            'empresa_nome' => $this->nullableString($item['empresa_nome'] ?? null),
            'empresa_id_cocari' => $this->nullableString($item['empresa_id_cocari'] ?? null),
            'data_desligamento' => $this->nullableString($item['data_desligamento'] ?? null),
            'ativo' => (int) ($item['ativo'] ?? 0),
            'desligado' => (int) ($item['desligado'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function candidatePayload(array $item, float $score): array
    {
        return [
            'source_view' => $item['source_view'] ?? null,
            'nome' => (string) ($item['nome'] ?? ''),
            'id_pessoa' => $this->nullableString($item['id_pessoa'] ?? null),
            'matricula' => $this->nullableString($item['matricula'] ?? null),
            'score' => round($score, 2),
            'raw_score' => isset($item['raw_score']) ? round((float) $item['raw_score'], 2) : null,
            'simplified_score' => isset($item['simplified_score']) ? round((float) $item['simplified_score'], 2) : null,
            'token_coverage' => isset($item['token_coverage']) ? round((float) $item['token_coverage'], 2) : null,
            'centro_custo' => $this->nullableString($item['centro_custo'] ?? null),
            'centro_custo_nome' => $this->nullableString($item['centro_custo_nome'] ?? null),
            'id_unicoop' => $this->nullableString($item['id_unicoop'] ?? null),
            'nome_unicoop' => $this->nullableString($item['nome_unicoop'] ?? null),
            'id_area' => $this->nullableString($item['id_area'] ?? null),
            'nome_departamento' => $this->nullableString($item['nome_departamento'] ?? null),
            'cargo_nome' => $this->nullableString($item['cargo_nome'] ?? null),
            'empresa_nome' => $this->nullableString($item['empresa_nome'] ?? null),
            'empresa_id_cocari' => $this->nullableString($item['empresa_id_cocari'] ?? null),
            'data_desligamento' => $this->nullableString($item['data_desligamento'] ?? null),
            'ativo' => (int) ($item['ativo'] ?? 0),
            'desligado' => (int) ($item['desligado'] ?? 0),
        ];
    }

    /**
     * @param array<int, string> $columns
     * @return array<int, array{name:string,filled:int}>
     */
    private function detectNameCandidates(array $columns, $connection, string $source): array
    {
        $patterns = ['Nome', 'NomePessoa', 'NomeColaborador', 'Colaborador', 'Funcionario', 'NomeFuncionario', 'Pessoa', 'RazaoSocial'];

        return collect($columns)
            ->filter(function (string $column) use ($patterns): bool {
                foreach ($patterns as $pattern) {
                    if (mb_strtolower($column) === mb_strtolower($pattern)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn (string $column): array => ['name' => $column, 'filled' => $this->countFilled($connection, $source, $column)])
            ->sortByDesc('filled')
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $columns
     */
    private function pickExistingColumn(array $columns, array $preferred): ?string
    {
        foreach ($preferred as $candidate) {
            foreach ($columns as $column) {
                if (mb_strtolower($column) === mb_strtolower($candidate)) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function countFilled($connection, string $source, string $column): int
    {
        $rows = $this->runQuery($connection, 'SELECT COUNT(*) AS aggregate FROM ' . $this->quoteIdentifier($source) . ' WHERE TRIM(COALESCE(CAST(' . $this->quoteIdentifier($column) . ' AS CHAR), "")) <> ""');

        return (int) (($rows[0]->aggregate ?? 0));
    }

    private function countDistinctFilled($connection, string $source, string $column): int
    {
        $rows = $this->runQuery($connection, 'SELECT COUNT(DISTINCT ' . $this->quoteIdentifier($column) . ') AS aggregate FROM ' . $this->quoteIdentifier($source) . ' WHERE TRIM(COALESCE(CAST(' . $this->quoteIdentifier($column) . ' AS CHAR), "")) <> ""');

        return (int) (($rows[0]->aggregate ?? 0));
    }

    private function countByEquality($connection, string $source, string $column, string $expected): int
    {
        $rows = $this->runQuery($connection, 'SELECT COUNT(*) AS aggregate FROM ' . $this->quoteIdentifier($source) . ' WHERE TRIM(COALESCE(CAST(' . $this->quoteIdentifier($column) . ' AS CHAR), "")) = ' . $this->quoteSqlString($expected));

        return (int) (($rows[0]->aggregate ?? 0));
    }

    private function countAll($connection, string $source): int
    {
        $rows = $this->runQuery($connection, 'SELECT COUNT(*) AS aggregate FROM ' . $this->quoteIdentifier($source));

        return (int) (($rows[0]->aggregate ?? 0));
    }

    /** @param array<int, string> $columns */
    private function buildIdentifierList(array $columns): string
    {
        return collect($columns)->map(fn (string $column): string => $this->quoteIdentifier($column))->implode(', ');
    }

    private function quoteIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    private function quoteSqlString(string $value): string
    {
        return '\'' . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . '\'';
    }

    /** @return array<int, object> */
    private function runQuery($connection, string $sql): array
    {
        try {
            $statement = $connection->getPdo()->query($sql);

            return $statement ? $statement->fetchAll(\PDO::FETCH_OBJ) : [];
        } catch (\Throwable $e) {
            if (($connection->getName() ?? null) !== 'intranet_cocari') {
                throw $e;
            }

            return $this->runMysqliQuery($connection, $sql, $e);
        }
    }

    /** @return array<int, object> */
    private function runMysqliQuery($connection, string $sql, ?\Throwable $previous = null): array
    {
        $config = config('database.connections.' . $connection->getName());
        if (! is_array($config)) {
            throw $previous ?? new \RuntimeException('Configuração da conexão intranet indisponível.');
        }

        $mysqli = mysqli_init();
        if (! $mysqli) {
            throw $previous ?? new \RuntimeException('Falha ao inicializar mysqli.');
        }

        mysqli_options($mysqli, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        @mysqli_real_connect(
            $mysqli,
            (string) ($config['host'] ?? '127.0.0.1'),
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            (string) ($config['database'] ?? ''),
            (int) ($config['port'] ?? 3306)
        );

        if (mysqli_connect_errno() !== 0) {
            throw $previous ?? new \RuntimeException('Falha de conexão mysqli: ' . mysqli_connect_error());
        }

        $mysqli->set_charset((string) ($config['charset'] ?? 'utf8mb4'));
        $result = $mysqli->query($sql);
        if ($result === false) {
            $error = $mysqli->error;
            $mysqli->close();
            throw $previous ?? new \RuntimeException($error);
        }
        if ($result === true) {
            $mysqli->close();

            return [];
        }

        $rows = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        $result->free();
        $mysqli->close();

        return $rows;
    }

    private function truthyFlag(mixed $value): bool
    {
        $value = trim((string) $value);

        return in_array($value, ['1', 'true', 'sim', 'yes'], true);
    }

    private function resolveDisabledFlag(mixed $desligadoValue, mixed $dataDesligamentoValue): bool
    {
        $desligado = trim((string) $desligadoValue);
        if ($desligado !== '') {
            return $this->truthyFlag($desligado);
        }

        $date = trim((string) $dataDesligamentoValue);

        return ! in_array($date, ['', '\\N', '0000-00-00', 'NULL'], true);
    }

    private function firstToken(string $value): string
    {
        $tokens = explode(' ', trim($value));

        return $tokens[0] ?? '';
    }

    private function lastToken(string $value): string
    {
        $tokens = array_values(array_filter(explode(' ', trim($value))));

        return $tokens !== [] ? (string) end($tokens) : '';
    }

    /** @return \\Illuminate\\Support\\Collection<int, string> */
    private function relevantTokens(string $value): Collection
    {
        return collect(explode(' ', $this->normalizeName($value)))
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => $token !== '' && mb_strlen($token) >= 2)
            ->reject(fn (string $token): bool => in_array($token, self::CONNECTOR_TOKENS, true))
            ->values();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && $value !== '\\N' ? $value : null;
    }
}
