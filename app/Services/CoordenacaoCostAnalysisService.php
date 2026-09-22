<?php

namespace App\Services;

use App\Models\CoordenacaoCostRule;
use App\Models\CoretiGoogleEmail;
use App\Models\OfficeLicense;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoordenacaoCostAnalysisService
{
    public function participants(): Collection
    {
        return $this->buildPeopleCollection();
    }

    public function buildReport(array $filters = []): array
    {
        $rules = CoordenacaoCostRule::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $people = $this->buildPeopleCollection();
        $people = $this->applyFilters($people, $filters);
        $people = $people->map(function (array $person) use ($rules): array {
            return $this->applyRulesToPerson($person, $rules);
        })->values();

        $units = $people
            ->groupBy('resolved_unit')
            ->map(function (Collection $group, string $unit): array {
                return [
                    'unit' => $unit !== '' ? $unit : 'Sem unidade',
                    'users' => $group->count(),
                    'monthly_cost' => (float) $group->sum('monthly_cost'),
                    'one_time_cost' => (float) $group->sum('one_time_cost'),
                    'office_cost' => (float) $group->sum('office_cost'),
                    'groups_cost' => (float) $group->sum('groups_cost'),
                ];
            })
            ->sortByDesc('monthly_cost')
            ->values();

        $groupFrequency = $this->buildGroupFrequency();
        $ruleSummary = $this->buildRuleSummary($people, $rules);
        $candidates = $this->buildCandidates($groupFrequency, $rules);
        $warnings = $this->buildWarnings();

        $stats = [
            'people_total' => $people->count(),
            'people_with_office' => $people->where('has_office_license', true)->count(),
            'office_outside_ad_total' => $people->filter(fn (array $person): bool => ! ($person['has_ad_user'] ?? false) && ($person['has_office_license'] ?? false))->count(),
            'office_outside_ad_allocated' => $people->filter(fn (array $person): bool => ! ($person['has_ad_user'] ?? false)
                && ($person['has_office_license'] ?? false)
                && trim((string) ($person['resolved_unit'] ?? '')) !== '')->count(),
            'people_with_group_costs' => $people->where('groups_cost', '>', 0)->count(),
            'rules_active' => $rules->where('is_active', true)->count(),
            'rules_total' => $rules->count(),
            'monthly_cost_total' => (float) $people->sum('monthly_cost'),
            'one_time_cost_total' => (float) $people->sum('one_time_cost'),
            'office_cost_total' => (float) $people->sum('office_cost'),
            'groups_cost_total' => (float) $people->sum('groups_cost'),
            'units_total' => $units->count(),
            'detected_groups_total' => $groupFrequency->count(),
            'warning_total' => $warnings->count(),
            'warning_email_total' => $warnings->where('source', 'email')->count(),
            'warning_office_total' => $warnings->where('source', 'office')->count(),
        ];

        return [
            'stats' => $stats,
            'people' => $people,
            'units' => $units,
            'rules' => $rules,
            'ruleSummary' => $ruleSummary,
            'groupFrequency' => $groupFrequency,
            'candidates' => $candidates,
            'warnings' => $warnings,
        ];
    }

    public function exportRows(array $filters = []): array
    {
        return $this->buildReport($filters);
    }

    public function updateRuleUsage(Collection $people, Collection $rules): Collection
    {
        return $rules->map(function (CoordenacaoCostRule $rule) use ($people): array {
            $matchedPeople = $people->filter(fn (array $person): bool => in_array($rule->name, $person['matched_rule_names'], true));

            return [
                'id' => $rule->id,
                'name' => $rule->name,
                'source_type' => $rule->source_type,
                'match_value' => $rule->match_value,
                'billing_cycle' => $rule->billing_cycle,
                'amount' => (float) $rule->amount,
                'is_active' => $rule->is_active,
                'sort_order' => $rule->sort_order,
                'notes' => $rule->notes,
                'matched_users' => $matchedPeople->count(),
                'monthly_total' => (float) $matchedPeople->sum('monthly_cost_for_rule_' . $rule->id),
                'one_time_total' => (float) $matchedPeople->sum('one_time_cost_for_rule_' . $rule->id),
            ];
        })->values();
    }

    private function buildPeopleCollection(): Collection
    {
        $adRows = DB::table('ad_users')
            ->orderBy('nome_completo')
            ->get([
                'id',
                'nome_completo',
                'email',
                'nome_usuario',
                'unidade_setor',
                'e_ativo',
                'ad_groups',
            ]);

        $people = collect();
        $lookup = [
            'email' => [],
            'username' => [],
            'matricula' => [],
        ];

        foreach ($adRows as $row) {
            $key = 'ad-' . $row->id;
            $email = $this->normalizeEmail((string) ($row->email ?? ''));
            $username = $this->normalizeIdentifier((string) ($row->nome_usuario ?? ''));
            $groups = $this->parseGroups((string) ($row->ad_groups ?? ''));

            $people->put($key, [
                'key' => $key,
                'source' => 'ad',
                'office_id' => null,
                'ad_user_id' => $row->id,
                'email' => $email,
                'name' => trim((string) ($row->nome_completo ?? '')),
                'unit_name' => trim((string) ($row->unidade_setor ?? '')),
                'ad_unit_name' => trim((string) ($row->unidade_setor ?? '')),
                'resolved_unit' => trim((string) ($row->unidade_setor ?? '')),
                'matricula' => null,
                'status' => ((bool) $row->e_ativo) ? 'Ativo' : 'Inativo',
                'ad_active' => (bool) $row->e_ativo,
                'office_active' => null,
                'office_flags' => [
                    'office_apps' => false,
                    'office_business' => false,
                    'powerbi_pro' => false,
                    'powerbi_premium' => false,
                    'visio_plan' => false,
                ],
                'ad_groups' => $groups,
                'ad_groups_display' => $groups,
                'matched_rule_names' => [],
                'matched_rules' => [],
                'office_cost' => 0.0,
                'groups_cost' => 0.0,
                'monthly_cost' => 0.0,
                'one_time_cost' => 0.0,
                'notes' => [],
                'has_ad_user' => true,
                'has_office_license' => false,
                'has_google_email' => false,
            ]);

            $this->rememberLookupKey($lookup, 'email', $email, $key);
            $this->rememberLookupKey($lookup, 'username', $username, $key);
        }

        $googleRows = CoretiGoogleEmail::query()
            ->orderBy('nome')
            ->get([
                'id',
                'email',
                'nome',
                'nome_usuario',
                'matricula',
                'status_google',
                'mapeamento_status',
                'mapeamento_motivo',
            ]);

        foreach ($googleRows as $row) {
            $this->mergeExternalRecord(
                $people,
                $lookup,
                'email',
                (string) ($row->email ?? ''),
                [
                    'google_id' => $row->id,
                    'google_email_status' => trim((string) ($row->status_google ?? '')),
                    'google_mapping_status' => trim((string) ($row->mapeamento_status ?? '')),
                    'google_mapping_reason' => trim((string) ($row->mapeamento_motivo ?? '')),
                ],
                'email'
            );

            $this->mergeExternalRecord(
                $people,
                $lookup,
                'username',
                (string) ($row->nome_usuario ?? ''),
                [
                    'google_id' => $row->id,
                    'google_email_status' => trim((string) ($row->status_google ?? '')),
                    'google_mapping_status' => trim((string) ($row->mapeamento_status ?? '')),
                    'google_mapping_reason' => trim((string) ($row->mapeamento_motivo ?? '')),
                ],
                'email'
            );

        }

        $officeRows = OfficeLicense::query()
            ->orderBy('nome')
            ->get([
                'id',
                'matricula',
                'nome',
                'email',
                'departamento_unidade',
                'unicoop_office',
                'area_office',
                'unidade_nome',
                'departamento_nome',
                'centro_custo',
                'centro_custo_nome',
                'office_apps',
                'office_business',
                'powerbi_pro',
                'powerbi_premium',
                'visio_plan',
                'ativo',
            ]);

        foreach ($officeRows as $row) {
            $this->mergeExternalRecord(
                $people,
                $lookup,
                'email',
                (string) ($row->email ?? ''),
                [
                    'office_id' => $row->id,
                    'office_active' => (bool) $row->ativo,
                    'office_allocation_name' => $this->officeAllocationName($row),
                    'office_unit_name' => trim((string) ($row->unidade_nome ?? '')),
                    'office_department_name' => trim((string) ($row->departamento_nome ?? '')),
                    'office_cost_center' => trim((string) ($row->centro_custo ?? '')),
                    'office_cost_center_name' => trim((string) ($row->centro_custo_nome ?? '')),
                    'office_flags' => [
                        'office_apps' => (bool) $row->office_apps,
                        'office_business' => (bool) $row->office_business,
                        'powerbi_pro' => (bool) $row->powerbi_pro,
                        'powerbi_premium' => (bool) $row->powerbi_premium,
                        'visio_plan' => (bool) $row->visio_plan,
                    ],
                ],
                'office'
            );

            $normalizedEmail = $this->normalizeEmail((string) ($row->email ?? ''));
            $matchedKey = $normalizedEmail !== '' ? ($lookup['email'][$normalizedEmail] ?? null) : null;

            if ($matchedKey === null || ! $people->has($matchedKey)) {
                $this->addOfficeOnlyPerson($people, $lookup, $row);
            }
        }

        return $people->values();
    }

    private function applyFilters(Collection $people, array $filters): Collection
    {
        $search = trim((string) Arr::get($filters, 'q', ''));
        $unit = trim((string) Arr::get($filters, 'unit', ''));
        $status = trim((string) Arr::get($filters, 'status', ''));

        return $people->filter(function (array $person) use ($search, $unit, $status): bool {
            if ($search !== '') {
                $haystack = $this->normalizeText(implode(' ', [
                    $person['name'] ?? '',
                    $person['email'] ?? '',
                    $person['unit_name'] ?? '',
                    $person['ad_unit_name'] ?? '',
                ]));

                if (! str_contains($haystack, $this->normalizeText($search))) {
                    return false;
                }
            }

            if ($unit !== '' && $this->normalizeText($person['resolved_unit'] ?? '') !== $this->normalizeText($unit)) {
                return false;
            }

            if ($status === 'active' && ! (($person['office_active'] ?? false) || ($person['ad_active'] ?? false))) {
                return false;
            }

            if ($status === 'inactive' && (($person['office_active'] ?? false) || ($person['ad_active'] ?? false))) {
                return false;
            }

            return true;
        })->values();
    }

    private function applyRulesToPerson(array $person, Collection $rules): array
    {
        $matchedRules = [];
        $ruleCostsMonthly = [];
        $ruleCostsOneTime = [];
        $officeCost = 0.0;
        $groupCost = 0.0;
        $oneTimeCost = 0.0;

        foreach ($rules as $rule) {
            if (! $this->ruleMatchesPerson($person, $rule)) {
                continue;
            }

            $matchedRules[] = $rule->name;

            $monthlyEquivalent = $this->monthlyEquivalent((float) $rule->amount, (string) $rule->billing_cycle);
            $oneTimeValue = $this->oneTimeEquivalent((float) $rule->amount, (string) $rule->billing_cycle);

            $ruleCostsMonthly['monthly_cost_for_rule_' . $rule->id] = $monthlyEquivalent;
            $ruleCostsOneTime['one_time_cost_for_rule_' . $rule->id] = $oneTimeValue;

            if ($rule->source_type === 'office_flag') {
                $officeCost += $monthlyEquivalent;
            } else {
                $groupCost += $monthlyEquivalent;
            }

            $oneTimeCost += $oneTimeValue;
        }

        $person['matched_rule_names'] = $matchedRules;
        $person['matched_rules'] = $matchedRules;
        $person['office_cost'] = $officeCost;
        $person['groups_cost'] = $groupCost;
        $person['monthly_cost'] = $officeCost + $groupCost;
        $person['one_time_cost'] = $oneTimeCost;
        $person['notes'] = array_values(array_filter(array_unique(array_merge($person['notes'] ?? [], []))));

        return array_merge($person, $ruleCostsMonthly, $ruleCostsOneTime);
    }

    private function ruleMatchesPerson(array $person, CoordenacaoCostRule $rule): bool
    {
        if (! $rule->is_active) {
            return false;
        }

        $matchValue = $this->normalizeText((string) ($rule->match_value ?? ''));

        if ($rule->source_type === 'office_flag') {
            return (bool) ($person['office_flags'][$rule->match_value] ?? false);
        }

        if ($matchValue === '') {
            return false;
        }

        $groups = array_map(fn (string $group): string => $this->normalizeText($group), $person['ad_groups'] ?? []);

        if ($rule->source_type === 'ad_group_exact') {
            return in_array($matchValue, $groups, true);
        }

        if ($rule->source_type === 'ad_group_contains') {
            foreach ($groups as $group) {
                if (str_contains($group, $matchValue) || str_contains($matchValue, $group)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function buildRuleSummary(Collection $people, Collection $rules): Collection
    {
        return $rules->map(function (CoordenacaoCostRule $rule) use ($people): array {
            $matchedPeople = $people->filter(fn (array $person): bool => in_array($rule->name, $person['matched_rule_names'], true));

            return [
                'id' => $rule->id,
                'name' => $rule->name,
                'source_type' => $rule->source_type,
                'match_value' => $rule->match_value,
                'billing_cycle' => $rule->billing_cycle,
                'amount' => (float) $rule->amount,
                'monthly_equivalent' => $this->monthlyEquivalent((float) $rule->amount, (string) $rule->billing_cycle),
                'one_time_equivalent' => $this->oneTimeEquivalent((float) $rule->amount, (string) $rule->billing_cycle),
                'is_active' => $rule->is_active,
                'sort_order' => $rule->sort_order,
                'notes' => $rule->notes,
                'matched_users' => $matchedPeople->count(),
                'monthly_total' => (float) $matchedPeople->sum('monthly_cost_for_rule_' . $rule->id),
                'one_time_total' => (float) $matchedPeople->sum('one_time_cost_for_rule_' . $rule->id),
            ];
        })->values();
    }

    private function buildGroupFrequency(): Collection
    {
        $groups = [];
        $adRows = DB::table('ad_users')
            ->orderBy('nome_completo')
            ->get(['id', 'nome_completo', 'email', 'ad_groups']);

        foreach ($adRows as $row) {
            foreach ($this->parseGroups((string) ($row->ad_groups ?? '')) as $group) {
                $normalized = $this->normalizeText($group);
                if ($normalized === '') {
                    continue;
                }

                if (! array_key_exists($normalized, $groups)) {
                    $groups[$normalized] = [
                        'group' => $group,
                        'normalized' => $normalized,
                        'users' => 0,
                    ];
                }

                $groups[$normalized]['users'] = ($groups[$normalized]['users'] ?? 0) + 1;
            }
        }

        return collect($groups)
            ->sortByDesc('users')
            ->map(fn (array $item): object => (object) $item)
            ->values();
    }

    private function buildCandidates(Collection $groupFrequency, Collection $rules): Collection
    {
        $activePatterns = $rules
            ->where('is_active', true)
            ->filter(fn (CoordenacaoCostRule $rule): bool => in_array($rule->source_type, ['ad_group_contains', 'ad_group_exact'], true))
            ->map(fn (CoordenacaoCostRule $rule): string => $this->normalizeText((string) $rule->match_value))
            ->filter()
            ->values();

        return $groupFrequency->map(function (object $group) use ($activePatterns): object {
            $group->potential_cost = ! $activePatterns->contains(fn (string $pattern): bool => str_contains($group->normalized, $pattern) || str_contains($pattern, $group->normalized));

            return $group;
        })->filter(fn (object $group): bool => $group->potential_cost)->values();
    }

    private function buildWarnings(): Collection
    {
        $warnings = collect();
        $adUsers = DB::table('ad_users')
            ->get(['id', 'nome_completo', 'nome_usuario', 'email']);

        $adByEmail = $this->buildAdLookup($adUsers, 'email');
        $adByUsername = $this->buildAdLookup($adUsers, 'username');
        $googleRows = CoretiGoogleEmail::query()->get(['id', 'email', 'nome', 'nome_usuario', 'matricula', 'status_google']);
        foreach ($googleRows as $row) {
            if ($this->findAdMatchKey($adByEmail, $adByUsername, (string) $row->email, (string) $row->nome_usuario) !== null) {
                continue;
            }

            $warnings->push([
                'source' => 'email',
                'label' => 'E-mail',
                'name' => trim((string) ($row->nome ?? '')),
                'email' => trim((string) ($row->email ?? '')),
                'identifier' => trim((string) ($row->nome_usuario ?? '')),
                'matricula' => trim((string) ($row->matricula ?? '')),
                'status' => trim((string) ($row->status_google ?? '')),
                'reason' => 'Existe na base de e-mail, mas não foi encontrado no AD e não entra no custo.',
            ]);
        }

        $officeRows = OfficeLicense::query()->get([
            'id', 'nome', 'email', 'matricula', 'ativo', 'departamento_unidade',
            'unidade_nome', 'departamento_nome', 'centro_custo', 'centro_custo_nome',
        ]);
        foreach ($officeRows as $row) {
            if ($this->findAdMatchKey($adByEmail, $adByUsername, (string) $row->email, (string) $row->nome) !== null) {
                continue;
            }

            $allocationName = $this->officeAllocationName($row);
            $hasAllocation = $allocationName !== ''
                || trim((string) ($row->centro_custo ?? '')) !== ''
                || trim((string) ($row->centro_custo_nome ?? '')) !== '';

            $warnings->push([
                'source' => 'office',
                'label' => 'Office',
                'name' => trim((string) ($row->nome ?? '')),
                'email' => trim((string) ($row->email ?? '')),
                'identifier' => trim((string) ($row->nome ?? '')),
                'matricula' => trim((string) ($row->matricula ?? '')),
                'status' => ((bool) $row->ativo) ? 'Ativo' : 'Inativo',
                'reason' => $hasAllocation
                    ? 'Fora do AD, mas contabilizado pela alocação organizacional cadastrada no Office.'
                    : 'Fora do AD e sem unidade/departamento ou centro de custo cadastrado; precisa de ajuste.',
            ]);
        }

        return $warnings
            ->unique(fn (array $row): string => $row['source'] . '|' . $row['email'] . '|' . $row['matricula'] . '|' . $row['name'])
            ->values();
    }

    private function buildAdLookup(Collection $rows, string $field): array
    {
        $lookup = [];

        foreach ($rows as $row) {
            $key = match ($field) {
                'username' => $this->normalizeIdentifier((string) ($row->nome_usuario ?? '')),
                default => $this->normalizeEmail((string) ($row->email ?? '')),
            };

            if ($key === '') {
                continue;
            }

            $lookup[$key] = (int) $row->id;
        }

        return $lookup;
    }

    private function findAdMatchKey(array $adByEmail, array $adByUsername, string $email, string $username): ?int
    {
        $emailKey = $this->normalizeEmail($email);
        if ($emailKey !== '' && array_key_exists($emailKey, $adByEmail)) {
            return $adByEmail[$emailKey];
        }

        $usernameKey = $this->normalizeIdentifier($username);
        if ($usernameKey !== '' && array_key_exists($usernameKey, $adByUsername)) {
            return $adByUsername[$usernameKey];
        }

        return null;
    }

    private function rememberLookupKey(array &$lookup, string $field, string $value, string $key): void
    {
        $normalized = match ($field) {
            'username' => $this->normalizeIdentifier($value),
            default => $this->normalizeEmail($value),
        };

        if ($normalized === '') {
            return;
        }

        $lookup[$field][$normalized] = $key;
    }

    private function mergeExternalRecord(Collection $people, array $lookup, string $field, string $value, array $payload, string $sourceType): void
    {
        $normalized = match ($field) {
            'username', 'matricula' => $this->normalizeIdentifier($value),
            default => $this->normalizeEmail($value),
        };

        if ($normalized === '') {
            return;
        }

        $key = $lookup[$field][$normalized] ?? null;
        if ($key === null || ! $people->has($key)) {
            return;
        }

        $existing = $people->get($key);
        if (! is_array($existing)) {
            return;
        }

        if ($sourceType === 'office') {
            $existing['has_office_license'] = true;
            $existing['office_id'] = $payload['office_id'] ?? $existing['office_id'];
            $existing['office_active'] = $payload['office_active'] ?? $existing['office_active'];
            $existing['office_flags'] = array_merge($existing['office_flags'] ?? [], $payload['office_flags'] ?? []);

            if (trim((string) ($existing['resolved_unit'] ?? '')) === '' && trim((string) ($payload['office_allocation_name'] ?? '')) !== '') {
                $existing['resolved_unit'] = trim((string) $payload['office_allocation_name']);
                $existing['unit_name'] = trim((string) $payload['office_allocation_name']);
            }
        }

        if ($sourceType === 'email') {
            $existing['has_google_email'] = true;
            $existing['google_id'] = $payload['google_id'] ?? ($existing['google_id'] ?? null);
            $existing['google_email_status'] = $payload['google_email_status'] ?? ($existing['google_email_status'] ?? null);
            $existing['google_mapping_status'] = $payload['google_mapping_status'] ?? ($existing['google_mapping_status'] ?? null);
            $existing['google_mapping_reason'] = $payload['google_mapping_reason'] ?? ($existing['google_mapping_reason'] ?? null);
        }

        $people->put($key, $existing);
    }

    private function addOfficeOnlyPerson(Collection $people, array &$lookup, object $row): void
    {
        $key = 'office-' . $row->id;
        $email = $this->normalizeEmail((string) ($row->email ?? ''));
        $allocationName = $this->officeAllocationName($row);
        $officeFlags = [
            'office_apps' => (bool) $row->office_apps,
            'office_business' => (bool) $row->office_business,
            'powerbi_pro' => (bool) $row->powerbi_pro,
            'powerbi_premium' => (bool) $row->powerbi_premium,
            'visio_plan' => (bool) $row->visio_plan,
        ];

        $people->put($key, [
            'key' => $key,
            'source' => 'office',
            'office_id' => $row->id,
            'ad_user_id' => null,
            'email' => $email,
            'name' => trim((string) ($row->nome ?? '')),
            'unit_name' => $allocationName,
            'ad_unit_name' => '',
            'resolved_unit' => $allocationName,
            'matricula' => trim((string) ($row->matricula ?? '')),
            'status' => ((bool) $row->ativo) ? 'Ativo' : 'Inativo',
            'ad_active' => false,
            'office_active' => (bool) $row->ativo,
            'office_flags' => $officeFlags,
            'ad_groups' => [],
            'ad_groups_display' => [],
            'matched_rule_names' => [],
            'matched_rules' => [],
            'office_cost' => 0.0,
            'groups_cost' => 0.0,
            'monthly_cost' => 0.0,
            'one_time_cost' => 0.0,
            'notes' => ['Fora do AD; custo alocado pelos dados cadastrados no Office.'],
            'has_ad_user' => false,
            'has_office_license' => true,
            'has_google_email' => false,
            'office_allocation_name' => $allocationName,
            'office_unit_name' => trim((string) ($row->unidade_nome ?? '')),
            'office_department_name' => trim((string) ($row->departamento_nome ?? '')),
            'office_cost_center' => trim((string) ($row->centro_custo ?? '')),
            'office_cost_center_name' => trim((string) ($row->centro_custo_nome ?? '')),
        ]);

        $this->rememberLookupKey($lookup, 'email', $email, $key);
    }

    private function officeAllocationName(object $row): string
    {
        foreach (['departamento_unidade', 'unidade_nome', 'departamento_nome', 'centro_custo_nome'] as $field) {
            $value = trim((string) ($row->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function monthlyEquivalent(float $amount, string $billingCycle): float
    {
        return match ($billingCycle) {
            'annual' => round($amount / 12, 2),
            'one_time' => 0.0,
            default => round($amount, 2),
        };
    }

    private function oneTimeEquivalent(float $amount, string $billingCycle): float
    {
        return $billingCycle === 'one_time' ? round($amount, 2) : 0.0;
    }

    private function parseGroups(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $decoded)));
        }

        $value = trim($value, "[]");
        $parts = preg_split('/\s*,\s*/', $value) ?: [];

        return array_values(array_filter(array_map(static fn ($item): string => trim(trim($item), "\"'"), $parts)));
    }

    private function personKey(string $email, string $fallback): string
    {
        $email = $this->normalizeEmail($email);

        return $email !== '' ? $email : $fallback;
    }

    private function normalizeEmail(string $value): string
    {
        return Str::of($value)->trim()->lower()->replace(["\t", "\n", "\r"], '')->toString();
    }

    private function normalizeIdentifier(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->lower()
            ->replaceMatches('/\s+/', '')
            ->replace(["\t", "\n", "\r"], '')
            ->toString();
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }
}
