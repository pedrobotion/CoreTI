<?php

namespace App\Services;

use App\Models\CoretiGoogleEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfficeLicenseImportService
{
    public function resolveIdentity(
        string $email,
        ?string $displayName = null,
        ?string $employeeId = null,
        ?string $existingMatricula = null,
        ?string $existingDepartment = null,
        ?string $graphDepartment = null
    ): array {
        $email = mb_strtolower(trim($email));
        $username = (string) Str::of($email)->before('@');
        $displayName = trim((string) $displayName);

        $adUser = $this->findAdUserByIdentity($email, $username, $displayName);
        $googleEmail = $this->findGoogleEmailByIdentity($email, $username);

        if (! $adUser && $googleEmail && filled($googleEmail->ad_user_id)) {
            $adUser = DB::table('ad_users')
                ->where('id', $googleEmail->ad_user_id)
                ->first(['id', 'nome_completo', 'nome_usuario', 'email', 'unidade_setor', 'e_ativo']);
        }

        $matricula = $this->firstFilled([
            $googleEmail?->matricula,
            $employeeId,
            $existingMatricula,
        ]);

        $department = $this->firstFilled([
            $adUser->unidade_setor ?? null,
            $googleEmail?->ad_unidade_setor_original,
            $graphDepartment,
            $existingDepartment,
        ]);
        $normalizedDepartment = $this->normalizeDepartmentLabel($department);
        if ($normalizedDepartment !== null) {
            $department = $normalizedDepartment;
        }

        return [
            'ad_user' => $adUser,
            'google_email' => $googleEmail,
            'matricula' => $matricula !== '' ? $matricula : null,
            'department' => $department !== '' ? $department : null,
            'department_original' => $this->firstFilled([
                $adUser->unidade_setor ?? null,
                $googleEmail?->ad_unidade_setor_original,
                $graphDepartment,
                $existingDepartment,
            ]) ?: null,
        ];
    }

    public function resolveGoogleEmailByIdentity(string $email): ?CoretiGoogleEmail
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $username = (string) Str::of($email)->before('@');

        return $this->findGoogleEmailByIdentity($email, $username);
    }

    /**
     * Resolve a unidade/departamento oficial e seus metadados (unicoop/area)
     * a partir do valor informado pelo AD.
     *
     * @return array{department:?string,unicoop:?string,area:?string}
     */
    public function resolveOfficeOrganizationFromDepartment(?string $department, ?string $preferredUnicoop = null, ?string $preferredArea = null): array
    {
        $department = trim((string) $department);
        $preferredUnicoop = trim((string) $preferredUnicoop);
        $preferredArea = trim((string) $preferredArea);

        if ($department === '') {
            return [
                'department' => null,
                'unicoop' => $preferredUnicoop !== '' ? $preferredUnicoop : null,
                'area' => $preferredArea !== '' ? $preferredArea : null,
            ];
        }

        $officialDepartment = $this->normalizeDepartmentLabel($department) ?? $department;
        $lookup = app(OrganizationLookupService::class);
        $organization = $lookup->findCostCenterByName($officialDepartment, $preferredArea !== '' ? $preferredArea : null);

        if ($organization && ($organization->source ?? null) === 'unidade') {
            return [
                'department' => $officialDepartment,
                'unicoop' => $this->normalizeNumericMeta($organization->unicoop ?? null) ?? ($preferredUnicoop !== '' ? $preferredUnicoop : null),
                'area' => $this->normalizeNumericMeta($organization->area ?? null) ?? ($preferredArea !== '' ? $preferredArea : null),
            ];
        }

        if ($organization && ($organization->source ?? null) === 'departamento') {
            $resolvedUnicoop = $this->normalizeNumericMeta($organization->unicoop ?? null);
            $resolvedArea = $this->normalizeNumericMeta($organization->area ?? null);

            if ($this->isCristalinaIi($officialDepartment)) {
                return [
                    'department' => $officialDepartment,
                    'unicoop' => $preferredUnicoop !== '' ? $preferredUnicoop : $resolvedUnicoop,
                    'area' => $preferredArea !== '' ? $preferredArea : $resolvedArea,
                ];
            }

            return [
                'department' => $officialDepartment,
                'unicoop' => $resolvedUnicoop ?? ($preferredUnicoop !== '' ? $preferredUnicoop : null),
                'area' => $resolvedArea ?? ($preferredArea !== '' ? $preferredArea : null),
            ];
        }

        if ($this->isCristalinaIi($officialDepartment)) {
            return [
                'department' => $officialDepartment,
                'unicoop' => $preferredUnicoop !== '' ? $preferredUnicoop : null,
                'area' => $preferredArea !== '' ? $preferredArea : null,
            ];
        }

        return [
            'department' => $officialDepartment,
            'unicoop' => $preferredUnicoop !== '' ? $preferredUnicoop : null,
            'area' => $preferredArea !== '' ? $preferredArea : null,
        ];
    }

    public function resolveManualDetecDepartmentOverride(?string $name): ?string
    {
        $normalized = $this->normalizeEmployeeName($name);
        if ($normalized === '') {
            return null;
        }

        $manualNames = [
            'franciele tonetto ribeiro viana',
            'maria cristina de oliveira barbosa',
            'fabio barbosa ribeiro',
            'vinicius gabriel alves bernardino',
            'rodrigo valentini rombaldi',
            'andre de paula barbosa',
            'ana isabelly tavares daghetti',
            'bruna thais de morais pereira',
            'edson aparecido bonilha',
            'rafael herrig furlaneto',
            'mateus cinquini benedetti',
            'roberson de souza lima',
        ];

        return in_array($normalized, $manualNames, true) ? 'Detec' : null;
    }

    private function findAdUserByIdentity(string $email, string $username, string $displayName): ?object
    {
        $query = DB::table('ad_users')
            ->whereRaw('LOWER(email) = ?', [$email]);

        if ($username !== '') {
            $query->orWhereRaw('LOWER(nome_usuario) = ?', [$username])
                ->orWhereRaw('LOWER(nome_usuario) = ?', [$email]);
        }

        if ($displayName !== '') {
            $query->orWhereRaw('LOWER(nome_completo) = ?', [mb_strtolower($displayName)])
                ->orWhereRaw('LOWER(nome_completo) LIKE ?', ['%' . mb_strtolower($displayName) . '%']);
        }

        return $query
            ->orderByDesc('e_ativo')
            ->orderByDesc('id')
            ->first(['id', 'nome_completo', 'nome_usuario', 'email', 'unidade_setor', 'e_ativo']);
    }

    private function findGoogleEmailByIdentity(string $email, string $username): ?CoretiGoogleEmail
    {
        $query = CoretiGoogleEmail::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email]);

        if ($username !== '') {
            $query->orWhereRaw('LOWER(TRIM(nome_usuario)) = ?', [$username]);
        }

        return $query->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first([
                'id',
                'email',
                'nome',
                'nome_usuario',
                'matricula',
                'ad_user_id',
                'ad_unidade_setor_original',
            ]);
    }

    private function normalizeDepartmentLabel(?string $department): ?string
    {
        $department = trim((string) $department);
        if ($department === '') {
            return null;
        }

        $normalized = mb_strtolower($department);
        $normalized = Str::of($normalized)->ascii()->value();
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim((string) $normalized);

        $map = [
            'aac' => 'Associação Atlética Cocari',
            'associacao atletica cocari' => 'Associação Atlética Cocari',
            'associação atlética cocari' => 'Associação Atlética Cocari',
            'abatedouro' => 'UBP - Unidade de Beneficiamento de Pescado',
            'aquidaban 2' => 'Aquidaban II',
            'aquidaban ii' => 'Aquidaban II',
            'caixa sao pedro' => 'Caixa de São Pedro',
            'caixa são pedro' => 'Caixa de São Pedro',
            'campo alegre' => 'Campo Alegre de Goiás',
            'campo alegre ii' => 'Campo Alegre de Goiás',
            'campo alegre de goias' => 'Campo Alegre de Goiás',
            'campo alegre de goiás' => 'Campo Alegre de Goiás',
            'controller' => 'Controladoria',
            'corretora' => 'Corretora de Seguros',
            'ctc' => 'CTC - Centro Tecnológico',
            'ctc centro tecnologico' => 'CTC - Centro Tecnológico',
            'ctc centro tecnológico' => 'CTC - Centro Tecnológico',
            'fiação' => 'Fiação Cocari',
            'fiacao' => 'Fiação Cocari',
            'godoy moreira 2' => 'Godoy Moreira',
            'loja' => 'Loja Agropecuária',
            'manutencao' => 'Fiação Cocari',
            'manutenção' => 'Fiação Cocari',
            'marialva 2' => 'Marialva',
            'ortigueira' => 'Ortigueira II',
            'piscicultura' => 'UBP - Unidade de Beneficiamento de Pescado',
            'raçao' => 'Fábrica de Ração',
            'racao' => 'Fábrica de Ração',
            'sao joao da alianca' => "São João D'Aliança",
            'sao joao d alianca' => "São João D'Aliança",
            'são joão da aliança' => "São João D'Aliança",
            'centro de distribuicao' => 'CD - Centro de Distribuição de Insumos',
            'centro de distribuição' => 'CD - Centro de Distribuição de Insumos',
            'almoxarifado' => 'Almoxarifado',
            'ambiental' => 'Meio Ambiente',
            'auditoria' => 'Auditoria Interna',
            'balanca' => 'Mandaguari II',
            'balança' => 'Mandaguari II',
            'cafe' => 'UBC - Unidade de Beneficiamento de Café',
            'café' => 'UBC - Unidade de Beneficiamento de Café',
            'comercializacao' => 'Comercialização',
            'comercialização' => 'Comercialização',
            'contabilidade' => 'Contabilidade',
            'controladoria' => 'Controladoria',
            'cooperativismo' => 'Cooperativismo',
            'dh' => 'Desenvolvimento Humano',
            'diretores e superintendentes' => 'Diretoria',
            'e-commerce' => 'E-Commerce',
            'e commerce' => 'E-Commerce',
            'engenharia de processos' => 'Métodos e Processos',
            'financeiro' => 'Financeiro',
            'gestao de risco' => 'Gestão de Risco',
            'gestão de risco' => 'Gestão de Risco',
            'juridico' => 'Jurídico',
            'jurídico' => 'Jurídico',
            'laboratorio' => 'Laboratório de Sementes',
            'laboratório' => 'Laboratório de Sementes',
            'marketing' => 'Marketing',
            'medicina' => 'Ambulatório/Medicina',
            'mix vegetal' => 'Mandaguari II',
            'originacao e operacao de cereais' => 'Originação e Operação de Cereais',
            'originação e operação de cereais' => 'Originação e Operação de Cereais',
            'ouvidoria' => 'Ouvidoria',
            'recepcao' => 'Recepção',
            'recepção' => 'Recepção',
            'rh' => 'Recursos Humanos',
            'seguranca patrimonial' => 'Segurança Patrimonial',
            'segurança patrimonial' => 'Segurança Patrimonial',
            'seguranca_trabalho' => 'Segurança do Trabalho',
            'segurança_trabalho' => 'Segurança do Trabalho',
            'seguranca trabalho' => 'Segurança do Trabalho',
            'segurança trabalho' => 'Segurança do Trabalho',
            'seguranca do trabalho' => 'Segurança do Trabalho',
            'segurança do trabalho' => 'Segurança do Trabalho',
            'transcocari' => 'Transcocari',
            'inovacao agricola' => 'Inovação Agrícola',
            'inovação agrícola' => 'Inovação Agrícola',
            'logistica' => 'Logística Integrada',
            'logistica integrada' => 'Logística Integrada',
            'suprimentos' => 'Suprimentos',
            'desenvolvimento' => 'Tecnologia da Informação',
            'gerencia' => 'Tecnologia da Informação',
            'gerência' => 'Tecnologia da Informação',
            'processos de negocios' => 'Tecnologia da Informação',
            'processos de negócio' => 'Tecnologia da Informação',
            'suporte tecnico' => 'Tecnologia da Informação',
            'suporte técnico' => 'Tecnologia da Informação',
            'sao jose' => 'Jandaia do Sul II',
            'são josé' => 'Jandaia do Sul II',
            'cristalina i' => 'Cristalina',
            'cristalina ii' => 'Cristalina II',
            'loja rural agropecuaria' => 'Loja Rural Agropecuária',
            'loja rural agropecuária' => 'Loja Rural Agropecuária',
        ];

        $candidate = $map[$normalized] ?? null;
        if ($candidate === null) {
            if (str_contains($normalized, 'cristalina ii')) {
                return 'Cristalina II';
            }
            if (str_contains($normalized, 'cristalina i')) {
                return 'Cristalina';
            }
            if (str_contains($normalized, 'campo alegre ii')) {
                return 'Campo Alegre de Goiás';
            }
            if (str_contains($normalized, 'sao jose') || str_contains($normalized, 'são josé')) {
                return 'Jandaia do Sul II';
            }
            return $department;
        }

        if (app(OrganizationLookupService::class)->findCostCenterByName($candidate) !== null) {
            return $candidate;
        }

        return $department;
    }

    private function normalizeNumericMeta(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function isCristalinaIi(string $name): bool
    {
        $normalized = mb_strtolower($name);
        $normalized = Str::of($normalized)->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();

        return str_contains($normalized, 'cristalina ii');
    }

    /**
     * @param array<int, string|null> $values
     */
    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeEmployeeName(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value);
        $value = Str::of($value)->ascii()->value();
        $value = str_replace(['_', '-', '.'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return trim($value);
    }
}
