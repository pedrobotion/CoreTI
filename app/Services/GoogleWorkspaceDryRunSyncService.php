<?php

namespace App\Services;

use App\Models\ServiceDeskEmail;
use App\Models\ServiceDeskEmailCostCenter;
use App\Services\CoretiGoogleEmailImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use Google\Service\Directory;
use Illuminate\Support\Collection;
use RuntimeException;

class GoogleWorkspaceDryRunSyncService
{
    public const PREVIEW_CACHE_KEY = 'workspace.sync.preview';

    public function __construct(
        private readonly CoretiGoogleEmailImportService $importService,
    ) {
    }

    public function run(): array
    {
        return $this->buildAndCachePreview();
    }

    public function buildAndCachePreview(): array
    {
        $directory = $this->buildDirectoryClient();
        $googleUsers = $this->fetchGoogleUsers($directory)->values()->all();
        $preview = $this->importService->previewFromGoogleUsers($googleUsers);

        cache()->put(self::PREVIEW_CACHE_KEY, [
            'preview' => $preview,
            'users' => $googleUsers,
            'generated_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        return $preview;
    }

    public function cachedPreview(): ?array
    {
        $payload = cache()->get(self::PREVIEW_CACHE_KEY);
        if (! is_array($payload)) {
            return null;
        }

        $preview = $payload['preview'] ?? null;
        return is_array($preview) ? $preview : null;
    }

    public function applyFromCachedPreview(): array
    {
        $payload = cache()->get(self::PREVIEW_CACHE_KEY);
        if (! is_array($payload) || ! is_array($payload['users'] ?? null)) {
            throw new RuntimeException('Nenhuma prévia disponível. Gere uma nova prévia antes de aplicar.');
        }

        $users = $payload['users'];
        $result = $this->importService->importFromGoogleUsers($users);
        cache()->forget(self::PREVIEW_CACHE_KEY);

        Log::info('Google Workspace sync aplicado', $result['summary'] ?? $result);

        return $result;
    }

    /**
     * @return array{updated:int,center_cost_changes:int}
     */
    public function refreshIntranetDataForExistingEmails(): array
    {
        $updated = 0;
        $centerCostChanges = 0;

        ServiceDeskEmail::query()
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(200, function ($emails) use (&$updated, &$centerCostChanges): void {
                foreach ($emails as $email) {
                    $matricula = trim((string) ($email->matricula ?? ''));
                    $domainPayload = $this->resolveDataByEmailDomain((string) $email->email);
                    if ($domainPayload !== null) {
                        $targetScope = $this->resolveEmailScopeForSync($matricula, $domainPayload);
                        $domainPayload['scope'] = $targetScope;
                        $costCenterId = $this->syncEmailCostCenter(
                            $targetScope,
                            $domainPayload['centro_custo'] ?? null,
                            $domainPayload['unicoop_sede'] ?? null,
                            $domainPayload['area_sede'] ?? null
                        );
                        $domainPayload['service_desk_email_cost_center_id'] = $costCenterId;

                        $changes = [];
                        $centerCostChanged = false;
                        foreach ($domainPayload as $key => $value) {
                            if ((string) ($email->{$key} ?? '') !== (string) ($value ?? '')) {
                                $changes[$key] = $value;
                                if (in_array($key, ['centro_custo', 'unicoop_sede', 'area_sede'], true)) {
                                    $centerCostChanged = true;
                                }
                            }
                        }

                        if ($changes !== []) {
                            $email->update($changes);
                            $updated++;
                            if ($centerCostChanged) {
                                $centerCostChanges++;
                            }
                        }

                        continue;
                    }

                    $identity = null;

                    if ($matricula === '') {
                        $identity = $this->resolveIntranetIdentityByEmail((string) $email->email);
                        $matricula = trim((string) ($identity['matricula'] ?? ''));
                    }

                    if ($matricula === '') {
                        if ((string) $email->scope !== 'genericos') {
                            $email->update(['scope' => 'genericos']);
                            $updated++;
                        }
                        continue;
                    }

                    if ((bool) $email->centro_custo_manual) {
                        continue;
                    }

                    $enrichment = $this->resolveIntranetDataByMatricula($matricula);
                    if ($enrichment === null) {
                        continue;
                    }

                    $scope = $this->resolveScopeByUnicoop($enrichment['unicoop_sede'] ?? null);
                    $payload = $enrichment;
                    $payload['scope'] = 'genericos';
                    if ($scope !== null) {
                        $payload['scope'] = $scope;
                    }
                    if ($identity !== null) {
                        $payload['matricula'] = mb_substr($matricula, 0, 20);
                        if (! blank($identity['id_pessoa'] ?? null)) {
                            $payload['id_pessoa'] = $identity['id_pessoa'];
                        }
                        if (! blank($identity['nome'] ?? null)) {
                            $payload['colaborador_nome'] = $identity['nome'];
                        }
                    }

                    $targetScope = (string) ($payload['scope'] ?? $email->scope);
                    $costCenterId = $this->syncEmailCostCenter(
                        $targetScope,
                        $payload['centro_custo'] ?? null,
                        $payload['unicoop_sede'] ?? null,
                        $payload['area_sede'] ?? null
                    );
                    $payload['service_desk_email_cost_center_id'] = $costCenterId;

                    $changes = [];
                    $centerCostChanged = false;
                    foreach ($payload as $key => $value) {
                        if ((string) ($email->{$key} ?? '') !== (string) ($value ?? '')) {
                            $changes[$key] = $value;
                            if (in_array($key, ['centro_custo', 'unicoop_sede', 'area_sede'], true)) {
                                $centerCostChanged = true;
                            }
                        }
                    }

                    if ($changes !== []) {
                        $email->update($changes);
                        $updated++;
                        if ($centerCostChanged) {
                            $centerCostChanges++;
                        }
                    }
                }
            });

        return [
            'updated' => $updated,
            'center_cost_changes' => $centerCostChanges,
        ];
    }

    public function resolveServiceDeskIntranetDataByMatricula(string $matricula): ?array
    {
        return $this->resolveIntranetDataByMatricula($matricula);
    }

    private function consolidateByEmail(string $email): ?ServiceDeskEmail
    {
        $rows = ServiceDeskEmail::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->orderByDesc('ativo')
            ->orderByRaw('LENGTH(COALESCE(matricula, "")) DESC')
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $keep = $rows->first();
        $others = $rows->slice(1);
        if ($others->isEmpty()) {
            return $keep;
        }

        foreach ($others as $row) {
            if (blank($keep->matricula) && filled($row->matricula)) {
                $keep->matricula = $row->matricula;
            }
            if (blank($keep->centro_custo) && filled($row->centro_custo)) {
                $keep->centro_custo = $row->centro_custo;
            }
            if (blank($keep->unicoop_sede) && filled($row->unicoop_sede)) {
                $keep->unicoop_sede = $row->unicoop_sede;
            }
            if (blank($keep->area_sede) && filled($row->area_sede)) {
                $keep->area_sede = $row->area_sede;
            }
            if (blank($keep->service_desk_email_cost_center_id) && filled($row->service_desk_email_cost_center_id)) {
                $keep->service_desk_email_cost_center_id = $row->service_desk_email_cost_center_id;
            }
            if (blank($keep->legacy_source_table) && filled($row->legacy_source_table)) {
                $keep->legacy_source_table = $row->legacy_source_table;
            }
        }

        $keep->save();
        ServiceDeskEmail::query()->whereIn('id', $others->pluck('id')->all())->delete();

        return $keep;
    }

    private function resolveScopeByUnicoop(?string $unicoop): ?string
    {
        if ($unicoop === null) {
            return null;
        }

        $code = preg_replace('/\D+/', '', trim($unicoop));
        if ($code === '') {
            return null;
        }

        $code = str_pad($code, 2, '0', STR_PAD_LEFT);

        if ($code === '01') {
            return 'sede';
        }

        $cerradoUnicoops = [
            '16', '19', '53', '55', '56', '57', '58', '59',
            '60', '63', '70', '72', '77', '78', '79',
        ];

        return in_array($code, $cerradoUnicoops, true) ? 'cerrado' : 'unidades';
    }

    private function resolveIntranetIdentityByEmail(string $email): ?array
    {
        $normalized = mb_strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        try {
            $row = DB::connection('intranet_cocari')
                ->table('cadColaborador as c')
                ->select([
                    DB::raw('c.IdPessoa as matricula'),
                    DB::raw('c.Nome as nome'),
                    DB::raw('c.IdPessoa as id_pessoa'),
                ])
                ->whereRaw('LOWER(c.Email) = ?', [$normalized])
                ->orderByDesc('c.Ativo')
                ->first();

            if (! $row) {
                return null;
            }

            return [
                'matricula' => trim((string) ($row->matricula ?? '')),
                'nome' => trim((string) ($row->nome ?? '')),
                'id_pessoa' => $row->id_pessoa ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function syncEmailCostCenter(string $scope, ?string $name, ?string $unicoop, ?string $area): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $costCenter = ServiceDeskEmailCostCenter::query()->updateOrCreate(
            [
                'scope' => $scope,
                'name' => mb_substr($name, 0, 255),
            ],
            [
                'unicoop' => trim((string) $unicoop) !== '' ? trim((string) $unicoop) : null,
                'area' => trim((string) $area) !== '' ? trim((string) $area) : null,
                'source_table' => 'intranet_cocari',
                'source_id' => null,
            ]
        );

        return $costCenter->id;
    }

    private function resolveIntranetDataByMatricula(string $matricula): ?array
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $row = DB::connection('intranet_cocari')
                    ->table('cadColaborador as c')
                    ->select([
                        DB::raw('c.IdPessoa as matricula'),
                        DB::raw('c.IdPessoa as id_pessoa'),
                        DB::raw('c.Nome as nome'),
                        DB::raw('c.Email as email'),
                        DB::raw('c.CentroCusto as centro_custo_codigo'),
                        DB::raw('c.Ativo as ativo'),
                    ])
                    ->where('c.IdPessoa', $matricula)
                    ->orderByDesc('c.Ativo')
                    ->first();

                if (! $row) {
                    return null;
                }

                $parsedCostCenter = $this->parseIntranetCentroCusto($row->centro_custo_codigo ?? null);
                if ($parsedCostCenter === null) {
                    return [
                        'matricula' => (string) ($row->matricula ?? $matricula),
                        'id_pessoa' => $row->id_pessoa ?? null,
                        'colaborador_nome' => trim((string) ($row->nome ?? '')) ?: null,
                        'centro_custo' => null,
                        'unicoop_sede' => null,
                        'area_sede' => null,
                    ];
                }

                $parsedCostCenter = $this->normalizeServiceDeskRateioCostCenter($parsedCostCenter);
                $localName = $this->resolveLocalNameFromCoreti(
                    $parsedCostCenter['unicoop'],
                    $parsedCostCenter['area']
                );

                return [
                    'matricula' => (string) ($row->matricula ?? $matricula),
                    'id_pessoa' => $row->id_pessoa ?? null,
                    'colaborador_nome' => trim((string) ($row->nome ?? '')) ?: null,
                    'centro_custo' => $localName ?? $parsedCostCenter['centro_custo'],
                    'unicoop_sede' => $parsedCostCenter['unicoop'],
                    'area_sede' => $parsedCostCenter['area'],
                ];
            } catch (\Throwable) {
                try {
                    DB::connection('intranet_cocari')->disconnect();
                } catch (\Throwable) {
                }

                if ($attempt === 2) {
                    return null;
                }
            }
        }

        return null;
    }

    private function normalizeServiceDeskRateioCostCenter(array $costCenter): array
    {
        $unicoop = str_pad(preg_replace('/\D+/', '', (string) ($costCenter['unicoop'] ?? '')), 2, '0', STR_PAD_LEFT);
        $area = str_pad(preg_replace('/\D+/', '', (string) ($costCenter['area'] ?? '')), 3, '0', STR_PAD_LEFT);

        if ($unicoop === '86') {
            $area = '201';
        } elseif ($unicoop === '64') {
            $area = '200';
        } elseif (! in_array($unicoop, ['01', '59'], true)) {
            $area = '154';
        }

        return [
            'unicoop' => $unicoop,
            'area' => $area,
            'centro_custo' => "{$unicoop}.{$area}",
        ];
    }

    private function parseIntranetCentroCusto(mixed $value): ?array
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, '.')) {
            [$unicoop, $area] = array_pad(explode('.', $raw, 2), 2, '');
            $unicoop = preg_replace('/\D+/', '', $unicoop);
            $area = preg_replace('/\D+/', '', $area);
        } else {
            $digits = preg_replace('/\D+/', '', $raw);
            if ($digits === '' || mb_strlen($digits) <= 3) {
                return null;
            }

            $area = mb_substr($digits, -3);
            $unicoop = mb_substr($digits, 0, -3);
        }

        $unicoop = ltrim((string) $unicoop, '0');
        $area = ltrim((string) $area, '0');

        if ($unicoop === '' || $area === '') {
            return null;
        }

        $unicoop = str_pad($unicoop, 2, '0', STR_PAD_LEFT);
        $area = str_pad($area, 3, '0', STR_PAD_LEFT);

        return [
            'unicoop' => $unicoop,
            'area' => $area,
            'centro_custo' => "{$unicoop}.{$area}",
        ];
    }

    private function resolveLocalNameFromCoreti(string $unicoop, string $area): ?string
    {
        $unicoop = str_pad(preg_replace('/\D+/', '', $unicoop), 2, '0', STR_PAD_LEFT);
        $area = str_pad(preg_replace('/\D+/', '', $area), 3, '0', STR_PAD_LEFT);

        if ($unicoop === '01') {
            $department = DB::table('departamentos')
                ->where('ativo', true)
                ->where('unicoop', $unicoop)
                ->where('area', $area)
                ->value('nome');

            if (filled($department)) {
                return (string) $department;
            }

            $sedeDepartment = DB::table('sede_departamentos')
                ->where('ativo', true)
                ->where('unicoop', $unicoop)
                ->where('area', $area)
                ->value('nome_departamento');

            if (filled($sedeDepartment)) {
                return (string) $sedeDepartment;
            }
        }

        $unit = DB::table('unidades')
            ->where('unicoop', $unicoop)
            ->where('area', $area)
            ->value('unidade');

        if (filled($unit)) {
            return (string) $unit;
        }

        if ($unicoop !== '01') {
            $department = DB::table('departamentos')
                ->where('ativo', true)
                ->where('unicoop', $unicoop)
                ->where('area', $area)
                ->value('nome');

            if (filled($department)) {
                return (string) $department;
            }
        }

        return null;
    }

    private function buildDiff(): array
    {
        $directory = $this->buildDirectoryClient();
        $googleUsers = $this->fetchGoogleUsers($directory)->values()->all();

        return $this->importService->previewFromGoogleUsers($googleUsers);
    }

    private function buildDirectoryClient(): Directory
    {
        $jsonPath = (string) config('services.google_workspace.service_account_json');
        $subject = (string) config('services.google_workspace.admin_subject');

        if ($jsonPath === '' || ! is_file($jsonPath)) {
            throw new RuntimeException('Arquivo JSON da service account não encontrado. Configure GOOGLE_WORKSPACE_SERVICE_ACCOUNT_JSON.');
        }

        if ($subject === '') {
            throw new RuntimeException('Admin subject não configurado. Configure GOOGLE_WORKSPACE_ADMIN_SUBJECT.');
        }

        $client = new GoogleClient();
        $client->setAuthConfig($jsonPath);
        $client->setSubject($subject);
        $client->setScopes([
            'https://www.googleapis.com/auth/admin.directory.user.readonly',
            'https://www.googleapis.com/auth/admin.directory.group.readonly',
            'https://www.googleapis.com/auth/admin.directory.orgunit.readonly',
        ]);

        return new Directory($client);
    }

    private function fetchGoogleUsers(Directory $directory): Collection
    {
        $items = collect();
        $pageToken = null;

        do {
            $response = $directory->users->listUsers([
                'customer' => 'my_customer',
                'maxResults' => 500,
                'orderBy' => 'email',
                'projection' => 'full',
                'viewType' => 'admin_view',
                'pageToken' => $pageToken,
            ]);

            $users = $response->getUsers() ?: [];
            foreach ($users as $user) {
                $email = mb_strtolower((string) $user->getPrimaryEmail());
                if (! $this->isSyncableWorkspaceEmail($email)) {
                    continue;
                }

                $items->push([
                    'email' => $email,
                    'name' => trim((string) optional($user->getName())->getFullName()),
                    'status_google' => $user->getSuspended() ? 'suspenso' : 'ativo',
                    'active' => ! (bool) $user->getSuspended(),
                    'matricula' => $this->extractEmployeeId($user),
                ]);
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $items;
    }

    private function isSyncableWorkspaceEmail(?string $email): bool
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== ''
            && (str_ends_with($email, '@cocari.com.br') || str_ends_with($email, '@rodocoop.com.br'));
    }

    private function resolveDataByEmailDomain(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        if (! str_ends_with($email, '@rodocoop.com.br')) {
            return null;
        }

        return [
            'scope' => 'sede',
            'centro_custo' => $this->resolveLocalNameFromCoreti('01', '144') ?? 'Sede Geral',
            'unicoop_sede' => '01',
            'area_sede' => '144',
        ];
    }

    private function resolveEmailScopeForSync(?string $matricula, ?array $payload = null): string
    {
        if (trim((string) $matricula) === '') {
            return 'genericos';
        }

        $scope = trim((string) ($payload['scope'] ?? ''));

        return in_array($scope, ['sede', 'unidades', 'cerrado'], true) ? $scope : 'sede';
    }

    private function extractEmployeeId(object $user): ?string
    {
        $externalIds = method_exists($user, 'getExternalIds')
            ? (array) ($user->getExternalIds() ?? [])
            : [];

        foreach ($externalIds as $externalId) {
            $type = '';
            $value = '';
            if (is_array($externalId)) {
                $type = mb_strtolower(trim((string) ($externalId['type'] ?? '')));
                $value = trim((string) ($externalId['value'] ?? ''));
            } elseif (is_object($externalId) && method_exists($externalId, 'getValue')) {
                $type = method_exists($externalId, 'getType')
                    ? mb_strtolower(trim((string) $externalId->getType()))
                    : '';
                $value = trim((string) $externalId->getValue());
            }

            if ($value === '') {
                continue;
            }

            if (in_array($type, ['organization', 'work', 'custom'], true)) {
                return $value;
            }
        }

        $organizations = method_exists($user, 'getOrganizations')
            ? (array) ($user->getOrganizations() ?? [])
            : [];

        foreach ($organizations as $organization) {
            $employeeId = '';
            if (is_array($organization)) {
                $employeeId = trim((string) ($organization['employeeId'] ?? ''));
            } elseif (is_object($organization) && method_exists($organization, 'getEmployeeId')) {
                $employeeId = trim((string) $organization->getEmployeeId());
            }
            if ($employeeId !== '') {
                return $employeeId;
            }
        }

        return null;
    }
}
