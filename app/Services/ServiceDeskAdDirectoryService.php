<?php

namespace App\Services;

use App\Models\AdUser;
use App\Models\CoretiCostCenter;
use App\Services\Ad\AdCadastroIncompletoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ServiceDeskAdDirectoryService
{
    public const CATEGORIES = [
        'sede' => 'Sede',
        'pr' => 'Unidades PR',
        'go' => 'Unidades GO',
        'outras' => 'Outras unidades',
        'sem_unidade' => 'Sem unidade definida',
    ];

    public const INDUSTRIES = [
        'ubp' => 'UBP',
        'ubs' => 'UBS',
        'fiacao' => 'Fiação',
        'racao' => 'Ração',
        'ubs_cerrado' => 'UBS Cerrado',
        'ubc' => 'UBC',
    ];

    public function users(): Collection
    {
        $units = $this->organizationUnits();
        $departments = $this->organizationDepartments();
        $costCenters = CoretiCostCenter::query()
            ->active()
            ->get(['code', 'name'])
            ->keyBy(fn (CoretiCostCenter $center): string => $this->normalizeCostCenterCode($center->code));

        /*
         | Só contas que ainda existem no domínio.
         |
         | A linha de quem foi excluído do AD é preservada de propósito, para
         | guardar o histórico de quem teve acesso. Mas ela não representa uma
         | conta viva: sem este filtro o painel contava 1.869 contas apagadas
         | junto com as 1.784 reais, e todo número saía quase dobrado.
         */
        return AdUser::query()
            ->vigentes()
            ->orderBy('nome_completo')
            ->get()
            ->map(function (AdUser $user) use ($units, $departments, $costCenters): AdUser {
                $user->ad_category = $this->categoryFor((string) $user->unidade_setor, $units, $departments, (string) $user->distinguished_name);
                $user->ad_category_label = self::CATEGORIES[$user->ad_category];
                $user->ad_industry = $this->industryFor((string) $user->unidade_setor, (string) $user->distinguished_name);
                $user->ad_industry_label = self::INDUSTRIES[$user->ad_industry] ?? null;
                $user->ad_groups_list = $this->groupsFor($user->ad_groups);
                $user->ad_groups_preview = $this->groupsPreview($user->ad_groups_list);
                $user->canonical_cost_center_code = trim((string) $user->centro_custo) ?: null;
                $user->canonical_cost_center_name = $costCenters->get($this->normalizeCostCenterCode($user->centro_custo))?->name;

                return $user;
            })
            ->sortBy(fn (AdUser $user): string => Str::ascii(mb_strtolower((string) ($user->nome_completo ?: $user->nome_exibicao))))
            ->values();
    }

    public function summary(Collection $users): array
    {
        return [
            'total' => $users->count(),
            'ativos' => $users->where('e_ativo', true)->count(),
            'inativos' => $users->where('e_ativo', false)->count(),
            'sede' => $users->where('ad_category', 'sede')->count(),
            'pr' => $users->where('ad_category', 'pr')->count(),
            'go' => $users->where('ad_category', 'go')->count(),
            'outras' => $users->where('ad_category', 'outras')->count(),
            'sem_unidade' => $users->where('ad_category', 'sem_unidade')->count(),
            'industrias' => $users->filter(fn (AdUser $user): bool => filled($user->ad_industry))->count(),
        ];
    }

    public function breakdown(Collection $users): array
    {
        return [
            'sede' => $this->breakdownRows($users->where('ad_category', 'sede')),
            'pr' => $this->breakdownRows($users->where('ad_category', 'pr')),
            'go' => $this->breakdownRows($users->where('ad_category', 'go')),
            'outras' => $this->breakdownRows($users->where('ad_category', 'outras')),
        ];
    }

    public function industryBreakdown(Collection $users): array
    {
        return $users
            ->filter(fn (AdUser $user): bool => filled($user->ad_industry_label))
            ->groupBy('ad_industry_label')
            ->map(fn (Collection $items, string $label): array => [
                'label' => $label,
                'count' => $items->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    public function groupsBreakdown(Collection $users, int $limit = 12): array
    {
        return $users
            ->flatMap(fn (AdUser $user): array => $user->ad_groups_list ?? [])
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take($limit)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * Pendências de cadastro, contando só quem deveria ter esses campos.
     *
     * O recorte vem inteiro de AdCadastroIncompletoService: prefixo de conta
     * que não é pessoa, lista de "não é colaborador" mantida na tela, e as
     * estruturas que seguem outro modelo de cadastro. Perguntar ao serviço em
     * vez de repetir as regras aqui é o que garante que este painel e o
     * relatório detalhado respondam o mesmo número — eles já divergiram
     * porque cada um tinha sua própria lista.
     *
     * Essas contas continuam no restante do painel: elas existem no domínio e
     * ocupam lugar na base. O que muda é só a contagem de pendência, que
     * senão vira um número que nunca baixa.
     */
    public function dataQuality(Collection $users): array
    {
        $relatorio = app(AdCadastroIncompletoService::class);
        $noEscopo = array_flip($relatorio->loginsNoEscopo());

        $pessoas = $users->filter(
            fn (AdUser $user): bool => isset($noEscopo[mb_strtolower(trim((string) $user->nome_usuario))])
        );

        /*
         | Campo em branco não é sempre pendência: faltaCampo() desconta as
         | dispensas por estrutura (a Rodocoop não tem matrícula nossa). Sem
         | isso o painel cobrava 14 contas que o relatório não cobra.
         |
         | "Sem unidade" olha unidade_principal, o mesmo campo do relatório —
         | antes olhava unidade_setor, que está sempre preenchido e por isso
         | o indicador vivia zerado.
         */
        $faltando = fn (string $campo): int => $pessoas
            ->filter(fn (AdUser $user): bool => $relatorio->faltaCampo($user, $campo))
            ->count();

        return [
            'sem_email' => $pessoas->filter(fn (AdUser $user): bool => blank($user->email))->count(),
            'sem_matricula' => $faltando('matricula'),
            'sem_unidade' => $faltando('unidade_principal'),
            'sem_cargo' => $faltando('Cargo'),
            'sem_centro_custo' => $faltando('centro_custo'),
            // Quantas contas ficaram de fora da conta, para a tela poder dizer.
            'fora_da_contagem' => $users->count() - $pessoas->count(),
        ];
    }

    private function breakdownRows(Collection $users): array
    {
        $counts = $users
            ->groupBy(fn (AdUser $user): string => trim((string) ($user->canonical_cost_center_name ?? '')) ?: (trim((string) $user->unidade_setor) ?: 'Não definida'))
            ->map(fn (Collection $items): int => $items->count())
            ->sortKeys();
        $max = max((int) ($counts->first() ?? 0), 1);

        return $counts->map(fn (int $count, string $label): array => [
            'label' => $label,
            'count' => $count,
            'percent' => (int) round(($count / $max) * 100),
        ])->values()->all();
    }

    public function industryFor(string $unitName, string $distinguishedName = ''): ?string
    {
        $value = $this->normalize($unitName . ' ' . $distinguishedName);

        if (str_contains($value, 'abatedouro') || str_contains($value, 'piscicultura') || str_contains($value, 'ubp')) {
            return 'ubp';
        }
        if (str_contains($value, 'ubs') && str_contains($value, 'cerrado')) {
            return 'ubs_cerrado';
        }
        if (str_contains($value, 'fiacao') || str_contains($value, 'manutencao')) {
            return 'fiacao';
        }
        if (str_contains($value, 'racao')) {
            return 'racao';
        }
        if (str_contains($value, 'ubs')) {
            return 'ubs';
        }
        if (str_contains($value, 'ubc')) {
            return 'ubc';
        }

        return null;
    }

    public function categoryFor(string $unitName, Collection $units, Collection $departments, string $distinguishedName = ''): string
    {
        $normalized = $this->normalize($unitName);
        if ($normalized === '' || in_array($normalized, ['n d', 'nd', 'nao definido', 'sem unidade'], true)) {
            return 'sem_unidade';
        }

        if ($normalized === 'rh') {
            return 'sede';
        }

        if ($normalized === 'aac' || str_contains($normalized, 'associacao atletica cocari')) {
            return 'outras';
        }

        // The AD OU is authoritative for administrative departments and regions.
        if ($this->hasOu($distinguishedName, 'sede')) {
            return 'sede';
        }
        if ($this->hasOu($distinguishedName, 'unidades go')) {
            return 'go';
        }
        if ($this->hasOu($distinguishedName, 'unidades pr')) {
            return 'pr';
        }

        $unit = $this->matchOrganizationName($normalized, $units);
        if ($unit) {
            $municipality = (string) ($unit->municipality_id ?? '');
            if (str_starts_with($municipality, '52')) {
                return 'go';
            }
            if (str_starts_with($municipality, '41')) {
                return ((string) $unit->source_id === '1' || str_contains($this->normalize((string) $unit->name), 'sede'))
                    ? 'sede'
                    : 'pr';
            }

            return 'outras';
        }

        // Departments without a physical unit are administrative/Sede records.
        if ($this->matchOrganizationName($normalized, $departments)) {
            return 'sede';
        }

        return 'outras';
    }

    private function hasOu(string $distinguishedName, string $expected): bool
    {
        foreach (explode(',', $distinguishedName) as $part) {
            [$attribute, $value] = array_pad(explode('=', $part, 2), 2, '');
            if ($this->normalize($attribute) === 'ou' && $this->normalize($value) === $this->normalize($expected)) {
                return true;
            }
        }

        return false;
    }

    public function groupsFor(mixed $groups): array
    {
        if (is_array($groups)) {
            return array_values(array_filter(array_map('strval', $groups)));
        }

        $decoded = json_decode((string) $groups, true);
        return is_array($decoded)
            ? array_values(array_filter(array_map('strval', $decoded)))
            : (filled($groups) ? [(string) $groups] : []);
    }

    private function groupsPreview(array $groups): string
    {
        if ($groups === []) {
            return '-';
        }

        $preview = implode(', ', array_slice($groups, 0, 2));
        $remaining = count($groups) - 2;

        return $remaining > 0 ? $preview . ' +' . $remaining : $preview;
    }

    private function organizationUnits(): Collection
    {
        return \DB::table('organization_units')
            ->where('active', true)
            ->get(['source_id', 'name', 'municipality_id']);
    }

    private function organizationDepartments(): Collection
    {
        return \DB::table('organization_departments')
            ->where('active', true)
            ->get(['source_id', 'name']);
    }

    private function matchOrganizationName(string $value, Collection $records): ?object
    {
        $exact = $records->first(fn (object $record): bool => $this->normalize((string) $record->name) === $value);
        if ($exact) {
            return $exact;
        }

        return $records
            ->sortByDesc(fn (object $record): int => mb_strlen($this->normalize((string) $record->name)))
            ->first(function (object $record) use ($value): bool {
                $name = $this->normalize((string) $record->name);
                return $name !== '' && (str_contains($value, $name) || str_contains($name, $value));
            });
    }

    private function normalize(string $value): string
    {
        return trim((string) Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' '));
    }

    private function normalizeCostCenterCode(?string $code): string
    {
        if (! preg_match('/^\s*(\d{1,2})\s*[.\/-]\s*(\d{1,3})\s*$/', (string) $code, $matches)) {
            return '';
        }

        return str_pad((string) ((int) $matches[1]), 2, '0', STR_PAD_LEFT)
            . '.'
            . str_pad((string) ((int) $matches[2]), 3, '0', STR_PAD_LEFT);
    }
}
