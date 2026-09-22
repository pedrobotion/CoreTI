<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\HrCollaborator;
use App\Models\OrganizationPosition;

class OfficeUserSyncService
{
    /** @var array<string, string|null> */
    private array $adLocationCache = [];

    private bool $adLocationsLoaded = false;

    /** @var array<string, string|null>|null */
    private ?array $serviceDeskMatriculaByIdentity = null;

    public function __construct(
        private readonly OfficeCollaboratorMatcherService $matcher,
        private readonly CentroCustoParserService $centroCustoParser,
        private readonly OfficeCostCenterResolverService $costCenterResolver,
        private readonly OfficeOrganizationResolverService $organizationResolver,
        private readonly AdUserCostCenterResolverService $adUserCostCenterResolver,
    ) {
    }

    /**
     * @param array{
     *     nome?:?string,
     *     email?:?string,
     *     upn?:?string,
     *     external_id?:?string,
     *     department?:?string,
     *     account_enabled?:bool|null
     * } $officeUser
     * @return array{
     *     attributes:array<string, mixed>,
     *     match:array<string, mixed>,
     *     counters:array<string, int>
     * }
     */
    public function buildOfficeLicenseAttributes(array $officeUser, ?object $existingRow = null): array
    {
        $existing = $this->normalizeExisting($existingRow);
        $officeName = trim((string) ($officeUser['nome'] ?? ''));
        $officeEmail = trim((string) ($officeUser['email'] ?? ''));
        $graphDepartment = trim((string) ($officeUser['department'] ?? ''));
        $serviceDeskMatricula = $this->resolveServiceDeskMatricula($officeEmail, $officeName);

        $attributes = [
            'nome' => $officeName !== '' ? mb_substr($officeName, 0, 255) : ($existing['nome'] ?? $officeEmail),
            'email' => $officeEmail,
            // Preserve a value already saved in Office. When it is empty,
            // reuse the reliable matricula from the matching ServiceDesk email.
            'matricula' => $existing['matricula'] ?: $serviceDeskMatricula,
            'departamento_unidade' => $existing['departamento_unidade'] ?? ($graphDepartment !== '' ? mb_substr($graphDepartment, 0, 255) : 'N/D'),
            'unicoop_office' => $existing['unicoop_office'],
            'area_office' => $existing['area_office'],
            'unidade_id' => $existing['unidade_id'],
            'organization_unit_id' => $existing['organization_unit_id'],
            'unidade_nome' => $existing['unidade_nome'],
            'area_nome' => $existing['area_nome'],
            'departamento_id' => $existing['departamento_id'],
            'organization_department_id' => $existing['organization_department_id'],
            'organization_cost_center_id' => $existing['organization_cost_center_id'],
            'organization_position_id' => $existing['organization_position_id'],
            'organization_assignment_source' => $existing['organization_assignment_source'],
            'organization_assignment_locked_at' => $existing['organization_assignment_locked_at'],
            'organization_assignment_locked_by' => $existing['organization_assignment_locked_by'],
            'organization_unit_source' => $existing['organization_unit_source'],
            'organization_department_source' => $existing['organization_department_source'],
            'organization_cost_center_source' => $existing['organization_cost_center_source'],
            'organization_position_source' => $existing['organization_position_source'],
            'departamento_nome' => $existing['departamento_nome'],
            'setor_nome' => $existing['setor_nome'],
            'cargo_nome' => $existing['cargo_nome'],
            'empresa_nome' => $existing['empresa_nome'],
            'empresa_id_cocari' => $existing['empresa_id_cocari'],
            'colaborador_source_view' => $existing['colaborador_source_view'],
            'centro_custo' => $existing['centro_custo'],
            'centro_custo_nome' => $existing['centro_custo_nome'],
            'colaborador_nome' => $existing['colaborador_nome'],
            'colaborador_match_score' => null,
            'colaborador_match_type' => null,
            'colaborador_match_status' => null,
            'colaborador_match_message' => null,
            'colaborador_matched_at' => $existing['colaborador_matched_at'],
        ];

        $match = $this->matcher->match($officeName, $officeEmail);
        $counters = [
            'matched' => 0,
            'not_found' => 0,
            'ambiguous' => 0,
            'matched_primary_view' => 0,
            'matched_fallback_view' => 0,
            'not_found_after_fallback' => 0,
            'ambiguous_primary_view' => 0,
            'ambiguous_fallback_view' => 0,
            'resolved_active_priority' => 0,
            'resolved_exact_name' => 0,
            'ambiguous_active_tie' => 0,
            'ambiguous_equivalent_score' => 0,
            'centro_custo_valid' => 0,
            'centro_custo_invalid' => 0,
            'matricula_found' => 0,
            'matricula_missing' => 0,
            'matricula_from_service_desk' => 0,
            'unit_found' => 0,
            'unit_missing' => 0,
            'department_found' => 0,
            'department_missing' => 0,
            'errors' => 0,
        ];

        if (($existing['matricula'] ?? null) === null && $serviceDeskMatricula !== null) {
            $counters['matricula_from_service_desk']++;
        }

        $attributes['colaborador_match_score'] = $match['score'];
        $attributes['colaborador_match_type'] = $match['type'];
        $attributes['colaborador_match_message'] = $this->buildMatchMessage($match);
        $decisionCode = (string) ($match['decision_code'] ?? '');
        $sourceView = $this->nullableString($match['source_view'] ?? null);
        $evaluatedSources = is_array($match['evaluated_sources'] ?? null) ? $match['evaluated_sources'] : [];
        $manualOrganizationLock = ($existing['organization_assignment_source'] ?? null) === 'manual';

        if (in_array($decisionCode, ['active_priority', 'exact_active'], true)) {
            $counters['resolved_active_priority']++;
        }
        if (in_array($decisionCode, ['exact_name', 'exact_active', 'exact'], true)) {
            $counters['resolved_exact_name']++;
        }
        if ($decisionCode === 'ambiguous_active_tie') {
            $counters['ambiguous_active_tie']++;
        }
        if ($decisionCode === 'ambiguous_equivalent_score') {
            $counters['ambiguous_equivalent_score']++;
        }

        if (($evaluatedSources['hr_collaborators']['status'] ?? null) === 'ambiguous'
            || ($evaluatedSources['vWcadColaborador']['status'] ?? null) === 'ambiguous') {
            $counters['ambiguous_primary_view']++;
        }
        $fallbackEvaluated = collect($evaluatedSources)
            ->filter(fn ($value, $key): bool => $key !== 'vWcadColaborador' && ($value['status'] ?? null) === 'ambiguous')
            ->isNotEmpty();
        if ($fallbackEvaluated) {
            $counters['ambiguous_fallback_view']++;
        }

        if (($match['status'] ?? null) === 'matched' && is_array($match['collaborator'] ?? null)) {
            $collaborator = $match['collaborator'];
            $attributes['colaborador_nome'] = $collaborator['nome'] ?? $attributes['colaborador_nome'];
            $attributes['colaborador_matched_at'] = $existing['colaborador_matched_at'] ?? now();
            $attributes['colaborador_source_view'] = $sourceView;
            $attributes['organization_position_id'] = $collaborator['organization_position_id'] ?? $attributes['organization_position_id'];

            $matricula = trim((string) (($collaborator['source_id'] ?? null) ?: ($collaborator['id_pessoa'] ?? null) ?: ($collaborator['matricula'] ?? '')));
            if ($matricula !== '') {
                $attributes['matricula'] = $matricula;
                $counters['matricula_found']++;
            } else {
                $counters['matricula_missing']++;
            }

            $organization = $this->organizationResolver->resolve($collaborator);
            $centroCustoOriginal = trim((string) (($organization['cost_center_code'] ?? null) ?: ($collaborator['centro_custo'] ?? '')));
            $centroCustoNome = trim((string) (($collaborator['centro_custo_nome'] ?? null) ?: ($collaborator['nome_setor'] ?? null) ?: ($collaborator['nome_departamento'] ?? '')));
            $unicoop = $this->costCenterResolver->normalizeUnicoop((string) (($organization['unit_source_id'] ?? null) ?: ($collaborator['unit_source_id'] ?? null) ?: ($collaborator['id_unicoop'] ?? '')));
            $area = $this->nullableString(($organization['area_source_id'] ?? null) ?: ($collaborator['area_source_id'] ?? null) ?: ($collaborator['id_area'] ?? null));
            $nomeUnicoop = $this->nullableString($organization['unit_name'] ?? null) ?: $this->nullableString($collaborator['nome_unicoop'] ?? null);

            // Cristalina II deve usar o centro administrativo da unidade
            // quando a origem informar a combinação 59.040.
            if ($unicoop === '59' && $this->normalizeNumericComponent($area) === '40') {
                $area = '154';
                $centroCustoOriginal = '59.154';
            }

            $nomeArea = $this->nullableString($collaborator['nome_area'] ?? null);
            $nomeDepartamento = $this->nullableString($organization['department_name'] ?? null) ?: $this->nullableString($collaborator['nome_departamento'] ?? null);
            $nomeSetor = $this->nullableString($collaborator['nome_setor'] ?? null) ?: $nomeDepartamento ?: $centroCustoNome;
            $cargoNome = $this->nullableString($collaborator['cargo_nome'] ?? null);
            $empresaNome = $this->nullableString($collaborator['empresa_nome'] ?? null);
            $empresaIdCocari = $this->nullableString($collaborator['empresa_id_cocari'] ?? null);
            $strictDepartmentLookup = $area !== null;

            if ($centroCustoOriginal !== '') {
                $attributes['centro_custo'] = mb_substr($centroCustoOriginal, 0, 255);
            }
            if ($centroCustoNome !== '') {
                $attributes['centro_custo_nome'] = mb_substr($centroCustoNome, 0, 255);
            }
            if (($organization['cost_center_name'] ?? null) !== null) {
                $attributes['centro_custo_nome'] = mb_substr((string) $organization['cost_center_name'], 0, 255);
            }
            if ($unicoop !== null) {
                $attributes['unicoop_office'] = $unicoop;
            }
            if ($area !== null) {
                $attributes['area_office'] = $area;
            }
            if ($nomeUnicoop !== null) {
                $attributes['unidade_nome'] = mb_substr($nomeUnicoop, 0, 255);
                $attributes['departamento_unidade'] = mb_substr($nomeUnicoop, 0, 255);
            }
            if ($nomeArea !== null) {
                $attributes['area_nome'] = mb_substr($nomeArea, 0, 255);
            }
            if ($nomeDepartamento !== null) {
                $attributes['departamento_nome'] = mb_substr($nomeDepartamento, 0, 255);
            }
            if ($nomeSetor !== null) {
                $attributes['setor_nome'] = mb_substr($nomeSetor, 0, 255);
            }
            if ($cargoNome !== null) {
                $attributes['cargo_nome'] = mb_substr($cargoNome, 0, 255);
            }
            if ($empresaNome !== null) {
                $attributes['empresa_nome'] = mb_substr($empresaNome, 0, 255);
            }
            if ($empresaIdCocari !== null) {
                $attributes['empresa_id_cocari'] = mb_substr($empresaIdCocari, 0, 255);
            }

            $parsedCentroCusto = $this->centroCustoParser->parseCentroCusto($centroCustoOriginal);
            if ($parsedCentroCusto['valid']) {
                $counters['centro_custo_valid']++;
            } else {
                $counters['centro_custo_invalid']++;
                if (! empty($parsedCentroCusto['error'])) {
                    $attributes['colaborador_match_message'] = trim($attributes['colaborador_match_message'] . ' ' . $parsedCentroCusto['error']);
                }
            }

            $resolver = $this->costCenterResolver->resolve(
                $unicoop,
                $area,
                $nomeUnicoop,
                $nomeArea,
                $nomeDepartamento,
                $nomeSetor,
                $strictDepartmentLookup
            );

            if (! $manualOrganizationLock) {
                $attributes['organization_unit_id'] = $organization['organization_unit_id'] ?? $resolver['organization_unit_id'] ?? $attributes['organization_unit_id'];
                if (($organization['organization_department_id'] ?? null) !== null || $strictDepartmentLookup) {
                    $attributes['organization_department_id'] = $organization['organization_department_id'] ?? $resolver['organization_department_id'] ?? $attributes['organization_department_id'];
                }
                $attributes['organization_cost_center_id'] = $organization['organization_cost_center_id'] ?? $attributes['organization_cost_center_id'];
                $attributes['organization_assignment_source'] = 'sync';
            } elseif (! str_contains((string) $attributes['colaborador_match_message'], 'Override organizacional manual preservado.')) {
                $attributes['colaborador_match_message'] = trim($attributes['colaborador_match_message'] . ' Override organizacional manual preservado.');
            }
            if (! empty($resolver['unit_name']) || ! empty($organization['unit_name'])) {
                $unitName = (string) (($organization['unit_name'] ?? null) ?: $resolver['unit_name']);
                $attributes['unidade_nome'] = mb_substr($unitName, 0, 255);
                $attributes['departamento_unidade'] = mb_substr($unitName, 0, 255);
            }
            if (! empty($resolver['department_name']) || ! empty($organization['department_name'])) {
                $attributes['departamento_nome'] = mb_substr((string) (($organization['department_name'] ?? null) ?: $resolver['department_name']), 0, 255);
            }
            if (! empty($resolver['area_name'])) {
                $attributes['area_nome'] = mb_substr((string) $resolver['area_name'], 0, 255);
            }
            if (! empty($resolver['setor_name'])) {
                $attributes['setor_nome'] = mb_substr((string) $resolver['setor_name'], 0, 255);
            }

            $counters[($organization['unit_found'] ?? false) || $resolver['unit_found'] ? 'unit_found' : 'unit_missing']++;
            if ($strictDepartmentLookup) {
                $counters[($organization['department_found'] ?? false) || $resolver['department_found'] ? 'department_found' : 'department_missing']++;
            }
            $warnings = collect($organization['warnings'] ?? [])->filter()->implode(' ');
            if (! empty($resolver['message']) || $warnings !== '') {
                $attributes['colaborador_match_message'] = trim($attributes['colaborador_match_message'] . ' ' . $warnings . ' ' . ($resolver['message'] ?? ''));
            }

            $attributes['colaborador_match_status'] = $matricula !== '' ? 'matched' : 'missing_matricula';
            if ($matricula === '') {
                $attributes['colaborador_match_message'] = trim($attributes['colaborador_match_message'] . ' IdPessoa/CodColaborador vazio na fonte de colaborador.');
            }

            if (in_array($sourceView, ['hr_collaborators', 'vWcadColaborador'], true)) {
                $counters['matched_primary_view']++;
            } elseif ($sourceView === 'vWcadColaboradorEmpresa') {
                $counters['matched_fallback_view']++;
            }

            $counters['matched']++;
        } elseif (($match['status'] ?? null) === 'ambiguous') {
            $attributes['colaborador_match_status'] = 'ambiguous';
            $attributes['colaborador_source_view'] = $sourceView;
            $counters['ambiguous']++;
        } else {
            $attributes['colaborador_match_status'] = 'not_found';
            $attributes['colaborador_source_view'] = $sourceView;
            $counters['not_found']++;
            $counters['not_found_after_fallback']++;
        }

        if (($attributes['organization_assignment_source'] ?? null) !== 'manual'
            && (empty($attributes['organization_unit_id']) || empty($attributes['organization_cost_center_id']))
            && ($attributes['unicoop_office'] !== null || $attributes['area_office'] !== null || $attributes['centro_custo'] !== null)) {
            $fallbackOrganization = $this->organizationResolver->resolveFromSourceIds(
                $attributes['unicoop_office'],
                $attributes['area_office'],
                null,
                $this->costCenterSourceIdFromCode($attributes['centro_custo'])
            );

            $attributes['organization_unit_id'] = $attributes['organization_unit_id'] ?: ($fallbackOrganization['organization_unit_id'] ?? null);
            $attributes['organization_department_id'] = $attributes['organization_department_id'] ?: ($fallbackOrganization['organization_department_id'] ?? null);
            $attributes['organization_cost_center_id'] = $attributes['organization_cost_center_id'] ?: ($fallbackOrganization['organization_cost_center_id'] ?? null);
            $attributes['unidade_nome'] = $attributes['unidade_nome'] ?: ($fallbackOrganization['unit_name'] ?? null);
            $attributes['departamento_nome'] = $attributes['departamento_nome'] ?: ($fallbackOrganization['department_name'] ?? null);
            $attributes['centro_custo_nome'] = $attributes['centro_custo_nome'] ?: ($fallbackOrganization['cost_center_name'] ?? null);
        }

        // O AD e a fonte operacional da unidade/departamento. Ajustes manuais
        // antigos nao devem impedir a sincronizacao, exceto Cristalina II,
        // que possui rateio individual e e preservada dentro da politica.
        $this->applyActiveDirectoryLocationPolicy($attributes, $officeEmail);

        // Cargo somente pode ser enriquecido por matricula. O match por nome
        // continua valido para as demais dimensoes, mas nao e suficiente para
        // atribuir cargo automaticamente.
        $resolvedMatricula = $this->nullableString($attributes['matricula'] ?? null);
        $adCostCenter = $this->adUserCostCenterResolver->resolve($resolvedMatricula);
        $isCristalinaIi = str_contains(mb_strtolower((string) ($attributes['unidade_nome'] ?? $attributes['departamento_unidade'] ?? '')), 'cristalina ii');
        if (($adCostCenter['found'] ?? false) && ! $isCristalinaIi && ($existing['organization_assignment_source'] ?? 'sync') !== 'manual') {
            $attributes['centro_custo'] = $adCostCenter['code'];
            $attributes['centro_custo_nome'] = $adCostCenter['name'] ?: $attributes['centro_custo_nome'];
            $attributes['organization_cost_center_source'] = 'ad_users';
            $attributes['organization_assignment_source'] = 'sync';
            if (! empty($adCostCenter['message'])) {
                $attributes['colaborador_match_message'] = trim($attributes['colaborador_match_message'] . ' ' . $adCostCenter['message']);
            }
        } elseif (($adCostCenter['ambiguous'] ?? false) === true) {
            $attributes['colaborador_match_message'] = trim($attributes['colaborador_match_message'] . ' ' . $adCostCenter['message']);
        }
        if ($resolvedMatricula !== null && ($existing['organization_position_source'] ?? 'sync') !== 'manual') {
            $position = $this->resolvePositionByEmployeeNumber($resolvedMatricula);
            if ($position !== null) {
                $attributes['organization_position_id'] = $position['id'];
                $attributes['cargo_nome'] = $position['name'];
                $attributes['organization_position_source'] = 'sync';
            }
        }

        $this->logMatch($officeUser, $match, $attributes);

        return [
            'attributes' => $attributes,
            'match' => $match,
            'counters' => $counters,
        ];
    }

    /**
     * Resolve o cargo pelo numero de matricula nos snapshots locais.
     *
     * @return array{id:int,name:string}|null
     */
    public function resolvePositionByEmployeeNumber(string $matricula): ?array
    {
        $matricula = trim($matricula);
        if ($matricula === '') {
            return null;
        }

        $collaborators = HrCollaborator::query()
            ->where('source_system', 'intranet')
            ->where(function ($query) use ($matricula): void {
                $query->where('employee_number', $matricula)
                    ->orWhere('source_id', $matricula);
            })
            ->whereNotNull('position_source_id')
            ->get(['position_source_id']);

        $positionSourceIds = $collaborators
            ->map(fn (HrCollaborator $collaborator): string => trim((string) $collaborator->position_source_id))
            ->filter()
            ->unique()
            ->values();

        if ($positionSourceIds->count() !== 1) {
            return null;
        }

        $position = OrganizationPosition::query()
            ->where('source_system', 'intranet')
            ->where('source_id', $positionSourceIds->first())
            ->first(['id', 'name']);

        $name = trim((string) ($position?->name ?? ''));
        if (! $position || $name === '') {
            return null;
        }

        return ['id' => (int) $position->id, 'name' => mb_substr($name, 0, 255)];
    }

    private function resolveServiceDeskMatricula(string $email, string $name): ?string
    {
        if ($this->serviceDeskMatriculaByIdentity === null) {
            $this->serviceDeskMatriculaByIdentity = [];

            if (! DB::getSchemaBuilder()->hasTable('service_desk_emails')) {
                return null;
            }

            $rows = DB::table('service_desk_emails')
                ->whereNotNull('matricula')
                ->whereRaw("TRIM(matricula) <> ''")
                ->get(['email', 'colaborador_nome', 'matricula']);
            $byName = [];

            foreach ($rows as $row) {
                $matricula = trim((string) ($row->matricula ?? ''));
                if ($matricula === '') {
                    continue;
                }

                $rowEmail = $this->normalizeIdentity((string) ($row->email ?? ''));
                if ($rowEmail !== '') {
                    if (array_key_exists($rowEmail, $this->serviceDeskMatriculaByIdentity)
                        && $this->serviceDeskMatriculaByIdentity[$rowEmail] !== $matricula) {
                        // Conflicting values are ambiguous and must not be copied.
                        $this->serviceDeskMatriculaByIdentity[$rowEmail] = null;
                    } else {
                        $this->serviceDeskMatriculaByIdentity[$rowEmail] = $matricula;
                    }
                }

                $rowName = $this->normalizeIdentity((string) ($row->colaborador_nome ?? ''));
                if ($rowName !== '') {
                    $byName[$rowName][] = $matricula;
                }
            }

            foreach ($byName as $identity => $matriculas) {
                $unique = array_values(array_unique(array_filter($matriculas)));
                if (count($unique) === 1) {
                    $this->serviceDeskMatriculaByIdentity['name:' . $identity] = $unique[0];
                }
            }
        }

        $normalizedEmail = $this->normalizeIdentity($email);
        if ($normalizedEmail !== '' && array_key_exists($normalizedEmail, $this->serviceDeskMatriculaByIdentity)) {
            $matricula = $this->serviceDeskMatriculaByIdentity[$normalizedEmail];
            return $matricula !== null ? trim($matricula) : null;
        }

        $normalizedName = $this->normalizeIdentity($name);
        if ($normalizedName === '') {
            return null;
        }

        $matricula = $this->serviceDeskMatriculaByIdentity['name:' . $normalizedName] ?? null;
        return $matricula !== null ? trim($matricula) : null;
    }

    private function normalizeIdentity(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value), 'UTF-8'));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return trim($value);
    }

    /**
     * @param array<string, mixed> $officeUser
     * @param array<string, mixed> $match
     * @param array<string, mixed> $attributes
     */
    private function logMatch(array $officeUser, array $match, array $attributes): void
    {
        $status = (string) ($attributes['colaborador_match_status'] ?? '');
        if (app()->environment('production') && $status === 'matched') {
            return;
        }

        $context = [
            'office_nome' => trim((string) ($officeUser['nome'] ?? '')),
            'office_email' => trim((string) ($officeUser['email'] ?? '')),
            'office_upn' => trim((string) ($officeUser['upn'] ?? '')),
            'office_external_id' => trim((string) ($officeUser['external_id'] ?? '')),
            'match_status' => $attributes['colaborador_match_status'] ?? null,
            'match_type' => $attributes['colaborador_match_type'] ?? null,
            'match_decision_code' => $match['decision_code'] ?? null,
            'match_score' => $attributes['colaborador_match_score'] ?? null,
            'match_message' => $attributes['colaborador_match_message'] ?? null,
            'collaborator_source' => $attributes['colaborador_source_view'] ?? ($match['source_view'] ?? null),
            'collaborator_name_field' => $match['source_name_field'] ?? null,
            'normalized_office_name' => $match['normalized_office_name'] ?? null,
            'simplified_office_name' => $match['simplified_office_name'] ?? null,
            'evaluated_sources' => $match['evaluated_sources'] ?? [],
            'like_candidates_count' => $match['like_candidates_count'] ?? null,
            'best_candidate' => $match['best_candidate'] ?? null,
            'second_candidate' => $match['second_candidate'] ?? null,
            'collaborator' => $match['collaborator'] ?? null,
            'candidates' => $match['candidates'] ?? [],
            'centro_custo' => $attributes['centro_custo'] ?? null,
            'centro_custo_nome' => $attributes['centro_custo_nome'] ?? null,
            'matricula' => $attributes['matricula'] ?? null,
            'unicoop_office' => $attributes['unicoop_office'] ?? null,
            'area_office' => $attributes['area_office'] ?? null,
            'unidade_nome' => $attributes['unidade_nome'] ?? null,
            'area_nome' => $attributes['area_nome'] ?? null,
            'departamento_nome' => $attributes['departamento_nome'] ?? null,
            'setor_nome' => $attributes['setor_nome'] ?? null,
            'cargo_nome' => $attributes['cargo_nome'] ?? null,
            'empresa_nome' => $attributes['empresa_nome'] ?? null,
            'empresa_id_cocari' => $attributes['empresa_id_cocari'] ?? null,
        ];

        if (in_array($status, ['not_found', 'ambiguous'], true)) {
            Log::warning('office:sync-microsoft collaborator match requires attention', $context);
            return;
        }

        Log::debug('office:sync-microsoft collaborator match', $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeExisting(?object $existingRow): array
    {
        return [
            'nome' => $this->nullableString($existingRow->nome ?? null),
            'matricula' => $this->nullableString($existingRow->matricula ?? null),
            'departamento_unidade' => $this->nullableString($existingRow->departamento_unidade ?? null),
            'unicoop_office' => $this->nullableString($existingRow->unicoop_office ?? null),
            'area_office' => $this->nullableString($existingRow->area_office ?? null),
            'unidade_id' => filled($existingRow->unidade_id ?? null) ? (int) $existingRow->unidade_id : null,
            'organization_unit_id' => filled($existingRow->organization_unit_id ?? null) ? (int) $existingRow->organization_unit_id : null,
            'unidade_nome' => $this->nullableString($existingRow->unidade_nome ?? null),
            'area_nome' => $this->nullableString($existingRow->area_nome ?? null),
            'departamento_id' => filled($existingRow->departamento_id ?? null) ? (int) $existingRow->departamento_id : null,
            'organization_department_id' => filled($existingRow->organization_department_id ?? null) ? (int) $existingRow->organization_department_id : null,
            'organization_cost_center_id' => filled($existingRow->organization_cost_center_id ?? null) ? (int) $existingRow->organization_cost_center_id : null,
            'organization_position_id' => filled($existingRow->organization_position_id ?? null) ? (int) $existingRow->organization_position_id : null,
            'organization_assignment_source' => $this->nullableString($existingRow->organization_assignment_source ?? null),
            'organization_assignment_locked_at' => $existingRow->organization_assignment_locked_at ?? null,
            'organization_assignment_locked_by' => filled($existingRow->organization_assignment_locked_by ?? null) ? (int) $existingRow->organization_assignment_locked_by : null,
            'organization_unit_source' => $this->nullableString($existingRow->organization_unit_source ?? null) ?: 'sync',
            'organization_department_source' => $this->nullableString($existingRow->organization_department_source ?? null) ?: 'sync',
            'organization_cost_center_source' => $this->nullableString($existingRow->organization_cost_center_source ?? null) ?: 'sync',
            'organization_position_source' => $this->nullableString($existingRow->organization_position_source ?? null) ?: 'sync',
            'departamento_nome' => $this->nullableString($existingRow->departamento_nome ?? null),
            'setor_nome' => $this->nullableString($existingRow->setor_nome ?? null),
            'cargo_nome' => $this->nullableString($existingRow->cargo_nome ?? null),
            'empresa_nome' => $this->nullableString($existingRow->empresa_nome ?? null),
            'empresa_id_cocari' => $this->nullableString($existingRow->empresa_id_cocari ?? null),
            'colaborador_source_view' => $this->nullableString($existingRow->colaborador_source_view ?? null),
            'centro_custo' => $this->nullableString($existingRow->centro_custo ?? null),
            'centro_custo_nome' => $this->nullableString($existingRow->centro_custo_nome ?? null),
            'colaborador_nome' => $this->nullableString($existingRow->colaborador_nome ?? null),
            'colaborador_matched_at' => $existingRow->colaborador_matched_at ?? null,
        ];
    }

    /** @param array<string, mixed> $match */
    private function buildMatchMessage(array $match): string
    {
        $message = trim((string) ($match['message'] ?? ''));
        $sourceView = trim((string) ($match['source_view'] ?? ''));
        if ($sourceView !== '') {
            $message = trim($message . ' Fonte: ' . $sourceView . '.');
        }

        $candidates = $match['candidates'] ?? [];
        if (($match['status'] ?? null) === 'ambiguous' && is_array($candidates) && $candidates !== []) {
            $names = collect($candidates)
                ->take(3)
                ->map(fn (array $candidate): string => sprintf('%s (%.2f%%, ativo=%s, desligado=%s)', $candidate['nome'] ?? '-', (float) ($candidate['score'] ?? 0), (int) ($candidate['ativo'] ?? 0), (int) ($candidate['desligado'] ?? 0)))
                ->implode('; ');

            return trim($message . ' Candidatos: ' . $names);
        }

        return $message;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && $value !== '\\N' ? $value : null;
    }

    private function normalizeNumericComponent(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?: '';
        $digits = ltrim($digits, '0');

        return $digits !== '' ? $digits : null;
    }

    /**
     * Aplica a localização do AD como origem organizacional para o Office.
     * Cristalina II é deliberadamente excluída: seus dados dependem da
     * combinação individual de IdUnicoop/IdArea da cadColaborador.
     *
     * @param array<string, mixed> $attributes
     */
    private function applyActiveDirectoryLocationPolicy(array &$attributes, string $officeEmail): void
    {
        $cacheKey = mb_strtolower(trim($officeEmail));
        if (! $this->adLocationsLoaded) {
            DB::table('ad_users')
                ->orderByDesc('e_ativo')
                ->get(['email', 'nome_usuario', 'unidade_setor'])
                ->each(function (object $row): void {
                    $location = trim((string) ($row->unidade_setor ?? '')) ?: null;
                    foreach ([$row->email ?? null, $row->nome_usuario ?? null] as $identity) {
                        $identity = mb_strtolower(trim((string) $identity));
                        if ($identity !== '' && ! array_key_exists($identity, $this->adLocationCache)) {
                            $this->adLocationCache[$identity] = $location;
                        }
                    }
                });
            $this->adLocationsLoaded = true;
        }

        $location = (string) ($this->adLocationCache[$cacheKey] ?? '');
        if ($location === '') {
            $username = mb_strtolower(trim((string) \Illuminate\Support\Str::before($cacheKey, '@')));
            $location = (string) ($this->adLocationCache[$username] ?? '');
        }
        $normalized = $this->normalizeLocation($location);
        if ($normalized === '' || $normalized === 'cristalina ii') {
            return;
        }

        if (in_array($normalized, ['cristalina', 'cristalina i'], true)) {
            $option = app(OrganizationLookupService::class)->findByUnicoopArea('16', '154');
            $this->applyLocationOption($attributes, 'Cristalina', '16', '154', $option?->organization_unit_id, $option?->organization_department_id, $option?->organization_cost_center_id);
            return;
        }

        if ($normalized === 'ibc') {
            $option = app(OrganizationLookupService::class)->findByUnicoopArea('48', '154');
            $this->applyLocationOption($attributes, 'IBC', '48', '154', $option?->organization_unit_id, $option?->organization_department_id, $option?->organization_cost_center_id);
            return;
        }

        $mapped = $this->mapActiveDirectoryLocation($normalized);
        $lookup = app(OrganizationLookupService::class);
        $option = $lookup->findCostCenterByName($mapped);
        if ($option === null) {
            return;
        }

        $this->applyLocationOption(
            $attributes,
            (string) $option->name,
            (string) $option->unicoop,
            (string) $option->area,
            (int) ($option->organization_unit_id ?? 0) ?: null,
            (int) ($option->organization_department_id ?? 0) ?: null,
            (int) ($option->organization_cost_center_id ?? 0) ?: null
        );
    }

    /** @param array<string, mixed> $attributes */
    private function applyLocationOption(array &$attributes, string $name, string $unicoop, string $area, ?int $unitId = null, ?int $departmentId = null, ?int $costCenterId = null): void
    {
        $lookup = app(OrganizationLookupService::class);
        $unit = $lookup->findUnitByUnicoop($unicoop);
        $department = $lookup->findDepartmentByUnicoopArea($unicoop, $area);
        if ($department === null) {
            $department = $this->findDepartmentByAdLocation($name);
        }
        $costCenter = $lookup->findByUnicoopArea($unicoop, $area);
        $attributes['departamento_unidade'] = $name;
        $attributes['unidade_nome'] = $name;
        $attributes['unicoop_office'] = $unicoop;
        $attributes['area_office'] = $area;
        $attributes['centro_custo'] = str_pad((string) ((int) $unicoop), 2, '0', STR_PAD_LEFT) . '.' . str_pad((string) ((int) $area), 3, '0', STR_PAD_LEFT);
        $attributes['centro_custo_nome'] = $name;
        $attributes['organization_unit_id'] = $unitId ?: $unit?->organization_unit_id ?: $attributes['organization_unit_id'];
        $attributes['organization_department_id'] = $departmentId ?: $department?->organization_department_id ?: $attributes['organization_department_id'];
        $attributes['organization_cost_center_id'] = $costCenterId ?: $costCenter?->organization_cost_center_id ?: $attributes['organization_cost_center_id'];
        if ($department?->name) {
            $attributes['departamento_nome'] = mb_substr((string) $department->name, 0, 255);
            $attributes['setor_nome'] = mb_substr((string) $department->name, 0, 255);
        }
        $attributes['organization_assignment_source'] = 'ad';
        $attributes['organization_unit_source'] = 'ad';
        $attributes['organization_department_source'] = 'ad';
        $attributes['organization_cost_center_source'] = 'ad';
    }

    private function findDepartmentByAdLocation(string $name): ?object
    {
        $normalized = $this->normalizeLocation($name);
        if ($normalized === '') {
            return null;
        }

        $departments = DB::table('organization_departments')
            ->where('source_system', 'intranet')
            ->where('active', true)
            ->get(['id', 'name']);

        $matches = $departments->filter(function (object $department) use ($normalized): bool {
            $candidate = $this->normalizeLocation((string) $department->name);

            return $candidate === $normalized
                || str_starts_with($candidate, $normalized . ' ');
        });

        if ($normalized === 'desenvolvimento humano') {
            $matches = $matches->sortByDesc(function (object $department): int {
                return str_contains($this->normalizeLocation((string) $department->name), 'sede') ? 1 : 0;
            });
        }

        $department = $matches->first();
        if ($department === null) {
            return null;
        }

        return (object) [
            'organization_department_id' => (int) $department->id,
            'name' => (string) $department->name,
        ];
    }

    private function normalizeLocation(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = \Illuminate\Support\Str::of($value)->ascii()->value();
        $value = preg_replace('/[\\-_]+/', ' ', $value) ?: $value;
        $value = preg_replace('/\\s+/', ' ', $value) ?: $value;

        return trim($value);
    }

    private function mapActiveDirectoryLocation(string $normalized): string
    {
        return [
            'aac' => 'Associação Atlética COCARI',
            'abatedouro' => 'UBP - Unidade de Beneficiamento de Pescado',
            'aquidaban 2' => 'Aquidaban II',
            'caixa sao pedro' => 'Caixa de São Pedro',
            'campo alegre' => 'Campo Alegre de Goiás',
            'campo alegre ii' => 'Campo Alegre de Goiás',
            'controller' => 'Controladoria',
            'corretora' => 'Corretora de Seguros',
            'ctc' => 'CTC - Centro Tecnológico',
            'fiacao' => 'Fiação Cocari',
            'godoy moreira 2' => 'Godoy Moreira',
            'loja' => 'Loja Agropecuária',
            'manutencao' => 'Fiação Cocari',
            'marialva 2' => 'Marialva',
            'ortigueira' => 'Ortigueira II',
            'piscicultura' => 'UBP - Unidade de Beneficiamento de Pescado',
            'racao' => 'Fábrica de Ração',
            'sao joao da alianca' => "São João D'Aliança",
            'centro de distribuicao' => 'CD - Centro de Distribuição de Insumos',
            'ambiental' => 'Meio Ambiente',
            'auditoria' => 'Auditoria Interna',
            'balanca' => 'Mandaguari II',
            'cafe' => 'UBC - Unidade de Beneficiamento de Café',
            'comercializacao' => 'Comercialização',
            'contabilidade' => 'Contabilidade',
            'dh' => 'Desenvolvimento Humano',
            'diretores e superintendentes' => 'Diretoria',
            'desenvolvimento' => 'Tecnologia da Informação',
            'e commerce' => 'E-Commerce',
            'engenharia de processos' => 'Métodos e Processos',
            'financeiro' => 'Financeiro',
            'gestao de risco' => 'Gestão de Risco',
            'juridico' => 'Jurídico',
            'laboratorio' => 'Laboratório de Sementes',
            'logistica' => 'Logística Integrada',
            'logistica integrada' => 'Logística Integrada',
            'marketing' => 'Marketing',
            'medicina' => 'Ambulatório/Medicina',
            'processos de negocios' => 'Tecnologia da Informação',
            'rh' => 'Recursos Humanos',
            'seguranca patrimonial' => 'Segurança Patrimonial',
            'seguranca trabalho' => 'Segurança do Trabalho',
            'suporte tecnico' => 'Tecnologia da Informação',
            'suprimentos' => 'Suprimentos',
            'sao jose' => 'Jandaia do Sul II',
            'sede mandaguari' => 'Sede - Mandaguari',
        ][$normalized] ?? trim($normalized);
    }

    private function costCenterSourceIdFromCode(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-' || $value === 'N/D') {
            return null;
        }

        if (str_contains($value, '.')) {
            [$unit, $area] = array_pad(explode('.', $value, 2), 2, null);
            $unit = preg_replace('/\D+/', '', (string) $unit) ?: '';
            $area = preg_replace('/\D+/', '', (string) $area) ?: '';
            if ($unit !== '' && $area !== '') {
                return ((string) ((int) $unit)) . str_pad((string) ((int) $area), 3, '0', STR_PAD_LEFT);
            }
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits !== '' ? (string) ((int) $digits) : null;
    }
}
