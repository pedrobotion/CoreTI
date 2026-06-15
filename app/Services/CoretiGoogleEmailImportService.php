<?php

namespace App\Services;

use App\Models\CoretiGoogleEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use App\Services\CoretiRateioLocalService;

class CoretiGoogleEmailImportService
{
    public function __construct(
        private readonly CoretiRateioLocalService $rateioLocalService,
    ) {
    }

    public function importFromFile(string $path): array
    {
        $rows = $this->readSpreadsheetRows($this->resolvePath($path));
        if ($rows === []) {
            throw new \RuntimeException('A planilha não possui linhas válidas.');
        }

        $columns = $this->detectColumns(array_keys($rows[0]));
        if (! isset($columns['email'])) {
            throw new \RuntimeException('Não foi possível identificar a coluna de e-mail na planilha.');
        }

        $columns = array_change_key_case($columns, CASE_LOWER);
        $adUsers = $this->loadAdUsers();
        $summary = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'found_ad' => 0,
            'not_found_ad' => 0,
            'mapped' => 0,
            'pending' => 0,
            'skipped' => 0,
            'reasons' => [],
        ];
        $reportRows = [];

        foreach ($rows as $row) {
            $summary['total']++;
            $row = array_change_key_case($row, CASE_LOWER);
            $email = $this->normalizeEmail($row[$columns['email']] ?? '');

            if ($email === '') {
                $summary['skipped']++;
                $this->incrementReason($summary, 'e-mail ausente');
                continue;
            }

            $data = [
                'email' => $email,
                'nome' => trim((string) ($row[$columns['name']] ?? '')) ?: null,
                'status_google' => trim((string) ($row[$columns['status']] ?? '')) ?: null,
                'nome_usuario' => $this->extractUsername($email),
                'importado_em' => now(),
            ];

            $existing = CoretiGoogleEmail::query()->where('email', $email)->first();
            $mapped = $this->resolveRowMapping($data, $adUsers);
            $payload = array_merge($data, $mapped['payload']);

            if ($existing) {
                $existing->update($payload);
                $summary['updated']++;
            } else {
                CoretiGoogleEmail::query()->create($payload + ['created_at' => now(), 'updated_at' => now()]);
                $summary['created']++;
            }

            if ($mapped['found_ad']) {
                $summary['found_ad']++;
            } else {
                $summary['not_found_ad']++;
            }

            if ($mapped['status'] === 'mapeado') {
                $summary['mapped']++;
            } else {
                $summary['pending']++;
            }

            if ($mapped['reason'] !== null) {
                $this->incrementReason($summary, $mapped['reason']);
            }

            $reportRows[] = $this->buildReportRow($payload);
        }

        $reportPath = $this->generateReport(
            'google_admin_emails_import_' . now()->format('Ymd_His') . '.csv',
            $summary,
            $reportRows
        );

        return array_merge($summary, ['report_path' => $reportPath]);
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    public function previewFromGoogleUsers(array $users): array
    {
        return $this->processGoogleUsers($users, false);
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    public function importFromGoogleUsers(array $users): array
    {
        return $this->processGoogleUsers($users, true);
    }

    public function remapRateio(bool $dryRun = true): array
    {
        $adUsers = $this->loadAdUsers();
        $rows = DB::table('coreti_google_emails')->get();
        $summary = [
            'total' => $rows->count(),
            'updated' => 0,
            'found_ad' => 0,
            'not_found_ad' => 0,
            'mapped' => 0,
            'pending' => 0,
            'reasons' => [],
        ];
        $reportRows = [];

        foreach ($rows as $row) {
            $data = [
                'email' => trim((string) ($row->email ?? '')),
                'nome' => trim((string) ($row->nome ?? '')) ?: null,
                'status_google' => trim((string) ($row->status_google ?? '')) ?: null,
                'nome_usuario' => trim((string) ($row->nome_usuario ?? '')) ?: null,
                'ad_user_id' => $row->ad_user_id,
                'ad_unidade_setor_original' => trim((string) ($row->ad_unidade_setor_original ?? '')) ?: null,
                'tipo_local' => trim((string) ($row->tipo_local ?? '')) ?: null,
                'nome_local' => trim((string) ($row->nome_local ?? '')) ?: null,
                'nome_local_normalizado' => trim((string) ($row->nome_local_normalizado ?? '')) ?: null,
                'unicoop' => trim((string) ($row->unicoop ?? '')) ?: null,
                'area' => trim((string) ($row->area ?? '')) ?: null,
                'centro_custo' => trim((string) ($row->centro_custo ?? '')) ?: null,
                'centro_custo_nome' => trim((string) ($row->centro_custo_nome ?? '')) ?: null,
            ];

            $mapped = $this->resolveRowMapping($data, $adUsers);
            $payload = array_merge($data, $mapped['payload']);
            unset($payload['email']);

            if ($mapped['found_ad']) {
                $summary['found_ad']++;
            } else {
                $summary['not_found_ad']++;
            }

            if ($mapped['status'] === 'mapeado') {
                $summary['mapped']++;
            } else {
                $summary['pending']++;
            }

            if ($mapped['reason'] !== null) {
                $this->incrementReason($summary, $mapped['reason']);
            }

            if (! $dryRun) {
                $dirty = [];
                foreach ($payload as $key => $value) {
                    if ($row->$key != $value) {
                        $dirty[$key] = $value;
                    }
                }

                if ($dirty !== []) {
                    DB::table('coreti_google_emails')->where('id', $row->id)->update($dirty + ['updated_at' => now()]);
                    $summary['updated']++;
                }
            }

            $reportRows[] = $this->buildReportRow($payload + ['email' => $data['email']]);
        }

        $fileName = 'google_admin_emails_mapear_rateio_' . now()->format('Ymd_His') . '.csv';
        $reportPath = $this->generateReport($fileName, $summary, $reportRows);

        return array_merge($summary, ['report_path' => $reportPath]);
    }

    public function syncFromServiceDeskEmails(bool $dryRun = true): array
    {
        $legacyRows = DB::table('service_desk_emails')
            ->select([
                'id',
                'email',
                'matricula',
                'colaborador_nome',
                'id_pessoa',
                'centro_custo',
                'unicoop_sede',
                'area_sede',
                'centro_custo_manual',
                'centro_custo_manual_at',
                'scope',
                'ativo',
                'updated_at',
            ])
            ->orderBy('email')
            ->get();

        $googleRows = CoretiGoogleEmail::query()->get()->keyBy(fn (CoretiGoogleEmail $row) => mb_strtolower(trim((string) $row->email)));

        $summary = [
            'total' => $legacyRows->count(),
            'matched' => 0,
            'updated' => 0,
            'already_in_sync' => 0,
            'missing_in_coreti' => 0,
            'manual_overrides' => 0,
            'reasons' => [],
        ];
        $reportRows = [];

        foreach ($legacyRows as $legacy) {
            $email = $this->normalizeEmail((string) ($legacy->email ?? ''));
            if ($email === '') {
                $this->incrementReason($summary, 'e-mail ausente');
                continue;
            }

            $googleRow = $googleRows->get($email);
            if (! $googleRow) {
                $summary['missing_in_coreti']++;
                $this->incrementReason($summary, 'e-mail não encontrado em coreti_google_emails');
                continue;
            }

            $summary['matched']++;

            $unicoop = $this->padCenterComponent((string) ($legacy->unicoop_sede ?? ''), 2);
            $area = $this->padCenterComponent((string) ($legacy->area_sede ?? ''), 3);
            $legacyName = trim((string) ($legacy->centro_custo ?? '')) ?: null;
            $legacyCenter = ($unicoop !== null && $area !== null) ? $unicoop . '.' . $area : null;

            $payload = [
                'centro_custo_nome' => $legacyName,
                'unicoop' => $unicoop,
                'area' => $area,
                'centro_custo' => $legacyCenter,
                'mapeamento_status' => $legacyCenter !== null ? 'mapeado' : ($googleRow->mapeamento_status ?? 'pendente'),
                'mapeamento_motivo' => $legacyCenter !== null ? null : ($googleRow->mapeamento_motivo ?? null),
                'atualizado_rateio_em' => $legacyCenter !== null ? now() : ($googleRow->atualizado_rateio_em ?? null),
            ];

            if ($legacyName !== null) {
                $payload['nome_local'] = $legacyName;
                $payload['nome_local_normalizado'] = $this->rateioLocalService->normalizeLocalName($legacyName);
            }

            if (trim((string) ($googleRow->centro_custo ?? '')) !== trim((string) ($payload['centro_custo'] ?? ''))
                || trim((string) ($googleRow->unicoop ?? '')) !== trim((string) ($payload['unicoop'] ?? ''))
                || trim((string) ($googleRow->area ?? '')) !== trim((string) ($payload['area'] ?? ''))
                || trim((string) ($googleRow->centro_custo_nome ?? '')) !== trim((string) ($payload['centro_custo_nome'] ?? ''))
                || trim((string) ($googleRow->mapeamento_status ?? '')) !== 'mapeado'
            ) {
                $summary['updated']++;
                $summary['manual_overrides']++;

                if (! $dryRun) {
                    CoretiGoogleEmail::query()
                        ->where('id', $googleRow->id)
                        ->update($payload + ['updated_at' => now()]);
                }

                $reportRows[] = [
                    'email' => $email,
                    'nome' => $googleRow->nome ?? $legacy->colaborador_nome ?? null,
                    'resultado' => 'atualizado',
                    'mapeamento_status' => $payload['mapeamento_status'],
                    'mapeamento_motivo' => $payload['mapeamento_motivo'],
                    'centro_custo_anterior' => $googleRow->centro_custo,
                    'centro_custo_novo' => $payload['centro_custo'],
                    'nome_local_anterior' => $googleRow->nome_local,
                    'nome_local_novo' => $payload['nome_local'] ?? null,
                    'observacao' => $legacyCenter !== null ? 'rateio herdado da tabela service_desk_emails' : 'sem rateio legado disponível',
                ];
            } else {
                $summary['already_in_sync']++;
            }
        }

        $reportPath = $this->generateGoogleSyncReport(
            'google_emails_service_desk_sync_' . now()->format('Ymd_His') . '.csv',
            $summary,
            $reportRows
        );

        return array_merge($summary, ['report_path' => $reportPath]);
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    private function processGoogleUsers(array $users, bool $persist): array
    {
        $adUsers = $this->loadAdUsers();
        $existingRows = CoretiGoogleEmail::query()
            ->get()
            ->keyBy(fn (CoretiGoogleEmail $row) => mb_strtolower(trim((string) $row->email)));

        $summary = [
            'google_total' => 0,
            'new_emails' => 0,
            'existing_emails' => 0,
            'updated_emails' => 0,
            'mapped' => 0,
            'pending' => 0,
            'sem_ad' => 0,
            'sem_centro_custo' => 0,
            'center_cost_changes' => 0,
            'ja_correto' => 0,
            'reasons' => [],
        ];
        $details = [
            'missing_in_coreti' => [],
            'missing_in_google' => [],
            'present_but_suspended' => [],
            'reactivate_in_coreti' => [],
            'matricula_updates' => [],
            'center_cost_changes' => [],
        ];
        $reportRows = [];
        $googleEmails = collect();

        foreach ($users as $user) {
            $summary['google_total']++;
            $user = is_array($user) ? $user : (array) $user;

            $email = $this->normalizeEmail((string) ($user['email'] ?? $user['primary_email'] ?? $user['primaryEmail'] ?? ''));
            if ($email === '') {
                $summary['pending']++;
                $this->incrementReason($summary, 'e-mail ausente');
                continue;
            }

            $googleEmails->push($email);

            $statusGoogle = trim((string) ($user['status_google'] ?? ''));
            if ($statusGoogle === '') {
                $statusGoogle = ! array_key_exists('active', $user) || (bool) ($user['active'] ?? true)
                    ? 'ativo'
                    : 'suspenso';
            }

            $row = [
                'email' => $email,
                'nome' => trim((string) ($user['name'] ?? $user['nome'] ?? $user['display_name'] ?? '')) ?: null,
                'status_google' => $statusGoogle,
                'nome_usuario' => $this->extractUsername($email),
                'importado_em' => now(),
            ];

            $existing = $existingRows->get($email);
            $mapped = $this->resolveRowMapping($row, $adUsers);
            $payload = array_merge($row, $mapped['payload']);
            $legacyOverride = $this->resolveLegacyServiceDeskRateio($email);
            if ($legacyOverride !== null) {
                $payload = array_merge($payload, $legacyOverride);
                $mapped['status'] = 'mapeado';
                $mapped['reason'] = null;
            }
            $comparisonPayload = $this->payloadForComparison($payload);
            $existingHasCompleteRateio = $existing ? $this->hasCompleteRateio($existing) : false;

            if ($existing === null) {
                $summary['new_emails']++;
                $details['missing_in_coreti'][] = [
                    'email' => $email,
                    'matricula' => $payload['nome_usuario'] ?? null,
                    'active' => $statusGoogle !== 'suspenso',
                ];
            } else {
                $summary['existing_emails']++;
            }

            if ($mapped['found_ad']) {
                // nothing else
            } else {
                $summary['sem_ad']++;
            }

            if (($payload['mapeamento_status'] ?? null) === 'mapeado') {
                $summary['mapped']++;
            } else {
                $summary['pending']++;
                if (($payload['centro_custo'] ?? null) === null || trim((string) ($payload['centro_custo'] ?? '')) === '') {
                    $summary['sem_centro_custo']++;
                }
            }

            if ($existing !== null) {
                $dirty = [];
                $centerCostChanged = false;
                foreach ($comparisonPayload as $key => $value) {
                    $current = $existing->{$key} ?? null;
                    if ($existingHasCompleteRateio && in_array($key, ['mapeamento_status', 'mapeamento_motivo'], true)) {
                        $current = $key === 'mapeamento_status' ? 'mapeado' : null;
                    }
                    if ($this->normalizeComparableValue($current) !== $this->normalizeComparableValue($value)) {
                        $dirty[$key] = $value;
                        if (in_array($key, ['tipo_local', 'nome_local', 'nome_local_normalizado', 'unicoop', 'area', 'centro_custo', 'centro_custo_nome'], true)) {
                            $centerCostChanged = true;
                        }
                    }
                }

                if ($existingHasCompleteRateio && ($existing->mapeamento_status ?? null) !== 'mapeado') {
                    $dirty['mapeamento_status'] = 'mapeado';
                    $dirty['mapeamento_motivo'] = null;
                    $dirty['atualizado_rateio_em'] = now();
                }

                if ($dirty === []) {
                    $summary['ja_correto']++;
                    if ($persist) {
                        $existing->forceFill(['importado_em' => now()])->save();
                    }
                } else {
                    $summary['updated_emails']++;
                    if ($centerCostChanged) {
                        $summary['center_cost_changes']++;
                        $details['center_cost_changes'][] = [
                            'email' => $email,
                            'nome' => $payload['nome'] ?? null,
                            'ad_unidade_setor_original' => $payload['ad_unidade_setor_original'] ?? null,
                            'centro_custo_anterior' => $existing->centro_custo,
                            'centro_custo_novo' => $payload['centro_custo'] ?? null,
                            'nome_local_anterior' => $existing->nome_local,
                            'nome_local_novo' => $payload['nome_local'] ?? null,
                            'resultado' => 'centro_custo_alterado',
                            'motivo' => 'alteração identificada no AD',
                        ];
                    }
                    if ($persist) {
                        $existing->update($dirty + ['importado_em' => now()]);
                        $existingRows->put($email, $existing->refresh());
                    }
                }
            } else {
                if ($persist) {
                    $createdRow = CoretiGoogleEmail::query()->create($payload + ['created_at' => now(), 'updated_at' => now()]);
                    $existingRows->put($email, $createdRow);
                }
            }

            if (($mapped['reason'] ?? null) !== null) {
                $this->incrementReason($summary, (string) $mapped['reason']);
            }

            $reportRows[] = array_merge(
                $this->buildReportRow($payload + ['email' => $email]),
                [
                    'resultado' => $existing === null
                        ? 'inserido'
                        : (($dirty ?? []) === [] ? 'ja_correto' : (($centerCostChanged ?? false) ? 'centro_custo_alterado' : 'atualizado')),
                    'centro_custo_anterior' => $existing?->centro_custo,
                    'centro_custo_novo' => $payload['centro_custo'] ?? null,
                    'nome_local_anterior' => $existing?->nome_local,
                    'nome_local_novo' => $payload['nome_local'] ?? null,
                    'observacao' => $payload['mapeamento_motivo'] ?? null,
                ]
            );
        }

        $googleEmailSet = $googleEmails->filter()->unique()->flip();
        foreach ($existingRows as $row) {
            $email = mb_strtolower(trim((string) $row->email));
            if (! $googleEmailSet->has($email)) {
                $details['missing_in_google'][] = $email;
            }

            if ((string) $row->ativo === '1' || (bool) $row->ativo) {
                if (in_array(mb_strtolower(trim((string) $row->status_google)), ['suspenso', 'suspended', 'inativo'], true)) {
                    $details['present_but_suspended'][] = $email;
                }
            }

            if (! (bool) $row->ativo && in_array(mb_strtolower(trim((string) $row->status_google)), ['ativo', 'active'], true)) {
                $details['reactivate_in_coreti'][] = $email;
            }
        }

        $reportPath = $this->generateGoogleSyncReport(
            'google_emails_sync_' . now()->format('Ymd_His') . '.csv',
            $summary,
            $reportRows
        );

        $summary['missing_in_coreti'] = count($details['missing_in_coreti']);
        $summary['missing_in_google'] = count($details['missing_in_google']);
        $summary['present_but_suspended'] = count($details['present_but_suspended']);
        $summary['reactivate_in_coreti'] = count($details['reactivate_in_coreti']);
        $summary['matricula_updates'] = 0;
        $summary['report_path'] = $reportPath;

        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
            'details' => $details,
            'samples' => [
                'missing_in_coreti' => array_slice($details['missing_in_coreti'], 0, 20),
                'missing_in_google' => array_slice($details['missing_in_google'], 0, 20),
                'present_but_suspended' => array_slice($details['present_but_suspended'], 0, 20),
                'reactivate_in_coreti' => array_slice($details['reactivate_in_coreti'], 0, 20),
                'matricula_updates' => [],
                'center_cost_changes' => array_slice($details['center_cost_changes'], 0, 20),
            ],
            'report_path' => $reportPath,
        ];
    }

    private function payloadForComparison(array $payload): array
    {
        return [
            'nome' => $payload['nome'] ?? null,
            'status_google' => $payload['status_google'] ?? null,
            'nome_usuario' => $payload['nome_usuario'] ?? null,
            'ad_user_id' => $payload['ad_user_id'] ?? null,
            'ad_unidade_setor_original' => $payload['ad_unidade_setor_original'] ?? null,
            'tipo_local' => $payload['tipo_local'] ?? null,
            'nome_local' => $payload['nome_local'] ?? null,
            'nome_local_normalizado' => $payload['nome_local_normalizado'] ?? null,
            'unicoop' => $payload['unicoop'] ?? null,
            'area' => $payload['area'] ?? null,
            'centro_custo' => $payload['centro_custo'] ?? null,
            'centro_custo_nome' => $payload['centro_custo_nome'] ?? null,
            'mapeamento_status' => $payload['mapeamento_status'] ?? null,
            'mapeamento_motivo' => $payload['mapeamento_motivo'] ?? null,
            'atualizado_rateio_em' => $payload['atualizado_rateio_em'] ?? null,
        ];
    }

    private function normalizeComparableValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }

    private function hasCompleteRateio(mixed $row): bool
    {
        if (! $row) {
            return false;
        }

        $centro = trim((string) ($row->centro_custo ?? ''));
        $unicoop = trim((string) ($row->unicoop ?? ''));
        $area = trim((string) ($row->area ?? ''));

        return $centro !== '' && $unicoop !== '' && $area !== '';
    }

    private function resolveLegacyServiceDeskRateio(string $email): ?array
    {
        $legacy = DB::table('service_desk_emails')
            ->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($email))])
            ->first([
                'email',
                'colaborador_nome',
                'matricula',
                'id_pessoa',
                'centro_custo',
                'unicoop_sede',
                'area_sede',
                'centro_custo_manual',
                'centro_custo_manual_at',
                'scope',
                'ativo',
            ]);

        if (! $legacy) {
            return null;
        }

        $unicoop = $this->padCenterComponent((string) ($legacy->unicoop_sede ?? ''), 2);
        $area = $this->padCenterComponent((string) ($legacy->area_sede ?? ''), 3);
        if ($unicoop === null || $area === null) {
            return null;
        }

        $legacyName = trim((string) ($legacy->centro_custo ?? '')) ?: null;

        return [
            'nome_local' => $legacyName,
            'nome_local_normalizado' => $legacyName !== null ? $this->rateioLocalService->normalizeLocalName($legacyName) : null,
            'unicoop' => $unicoop,
            'area' => $area,
            'centro_custo' => $unicoop . '.' . $area,
            'centro_custo_nome' => $legacyName,
            'mapeamento_status' => 'mapeado',
            'mapeamento_motivo' => null,
            'atualizado_rateio_em' => now(),
        ];
    }

    private function readSpreadsheetRows(string $fullPath): array
    {
        $reader = $this->createSpreadsheetReader($fullPath);
        $spreadsheet = $reader->load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $headers = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $current = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $current[] = trim((string) $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue());
            }

            if ($row === 1) {
                $headers = $current;
                continue;
            }

            if (array_filter($current, static fn ($value) => trim((string) $value) !== '') === []) {
                continue;
            }

            $normalized = [];
            foreach ($current as $index => $value) {
                $header = $headers[$index] ?? 'col_' . $index;
                $normalized[$header] = $value;
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    private function detectColumns(array $headers): array
    {
        $columns = [];
        $emailCandidates = [
            'email',
            'e-mail',
            'mail',
            'email address',
            'userprincipalname',
            'primary email',
            'primaryemail',
            'user name',
            'username',
        ];
        $nameCandidates = [
            'nome',
            'nome completo',
            'displayname',
            'full name',
            'name',
            'nome exibicao',
            'nome_exibicao',
        ];
        $statusCandidates = [
            'status',
            'estado',
            'accountstatus',
            'account enabled',
            'active',
            'ativo',
            'isactive',
        ];

        foreach ($headers as $header) {
            $normalized = mb_strtolower(trim((string) $header));
            $normalized = str_replace(['_', '\\', '\u00A0'], [' ', ' ', ' '], $normalized);
            $normalized = preg_replace('/\s+/', ' ', $normalized);

            if (! isset($columns['email']) && $this->headerMatches($normalized, $emailCandidates)) {
                $columns['email'] = $header;
                continue;
            }

            if (! isset($columns['name']) && $this->headerMatches($normalized, $nameCandidates)) {
                $columns['name'] = $header;
                continue;
            }

            if (! isset($columns['status']) && $this->headerMatches($normalized, $statusCandidates)) {
                $columns['status'] = $header;
                continue;
            }
        }

        return $columns;
    }

    private function headerMatches(string $value, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($value === $candidate || str_contains($value, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function extractUsername(string $email): string
    {
        return trim((string) Str::of($email)->before('@'));
    }

    private function loadAdUsers(): array
    {
        $adUsers = DB::table('ad_users')
            ->select(['id', 'nome_usuario', 'email', 'unidade_setor'])
            ->get();

        $lookup = [
            'by_email' => [],
            'by_username' => [],
        ];

        foreach ($adUsers as $row) {
            $email = mb_strtolower(trim((string) ($row->email ?? '')));
            $username = mb_strtolower(trim((string) ($row->nome_usuario ?? '')));

            if ($email !== '') {
                $lookup['by_email'][$email] = $row;
            }

            if ($username !== '') {
                $lookup['by_username'][$username] = $row;
                if (! str_contains($username, '@')) {
                    $lookup['by_username'][$username . '@cocari.com.br'] = $row;
                }
            }
        }

        return $lookup;
    }

    private function resolveRowMapping(array $row, array $adUsers): array
    {
        $result = [
            'payload' => [
                'ad_user_id' => null,
                'ad_unidade_setor_original' => null,
                'tipo_local' => null,
                'nome_local' => null,
                'nome_local_normalizado' => null,
                'unicoop' => null,
                'area' => null,
                'centro_custo' => null,
                'centro_custo_nome' => null,
                'mapeamento_status' => 'pendente',
                'mapeamento_motivo' => null,
                'atualizado_rateio_em' => null,
            ],
            'found_ad' => false,
            'status' => 'pendente',
            'reason' => null,
        ];

        $email = $this->normalizeEmail($row['email'] ?? '');
        $nomeUsuario = trim((string) ($row['nome_usuario'] ?? '')) ?: $this->extractUsername($email);
        $isCocari = str_ends_with($email, '@cocari.com.br');

        $adUser = null;
        if ($email !== '') {
            $adUser = $adUsers['by_email'][$email] ?? null;
            if ($adUser === null && $isCocari && $nomeUsuario !== '') {
                $adUser = $adUsers['by_username'][mb_strtolower($nomeUsuario)] ?? null;
            }
        }

        if ($adUser !== null) {
            $result['found_ad'] = true;
            $result['payload']['ad_user_id'] = $adUser->id;
            $result['payload']['ad_unidade_setor_original'] = trim((string) ($adUser->unidade_setor ?? '')) ?: null;
        }

        if (! $result['found_ad']) {
            $reason = $email === '' ? 'e-mail ausente' : ($isCocari ? 'usuário não encontrado no AD' : 'domínio não suportado');
            $result['reason'] = $reason;
            $result['payload']['mapeamento_status'] = 'pendente';
            $result['payload']['mapeamento_motivo'] = $reason;
            return $result;
        }

        $originalSetor = $result['payload']['ad_unidade_setor_original'] ?? '';
        if ($originalSetor === '') {
            $result['reason'] = 'unidade_setor original ausente no AD';
            $result['payload']['mapeamento_status'] = 'pendente';
            $result['payload']['mapeamento_motivo'] = $result['reason'];
            return $result;
        }

        $officialName = $this->mapUnidadeSetorName($originalSetor);
        $result['payload']['nome_local'] = $officialName;
        $result['payload']['nome_local_normalizado'] = $this->rateioLocalService->normalizeLocalName($officialName);

        if ($this->isCristalinaIi($officialName)) {
            $result['reason'] = 'Cristalina II exige validação manual';
            $result['payload']['mapeamento_status'] = 'pendente';
            $result['payload']['mapeamento_motivo'] = $result['reason'];
            return $result;
        }

        if ($this->isCristalinaI($officialName)) {
            $result['payload']['tipo_local'] = 'unidade';
            $result['payload']['unicoop'] = '16';
            $result['payload']['area'] = '154';
            $result['payload']['centro_custo'] = '16.154';
            $result['payload']['centro_custo_nome'] = $officialName;
            $result['payload']['mapeamento_status'] = 'mapeado';
            $result['payload']['mapeamento_motivo'] = null;
            $result['payload']['atualizado_rateio_em'] = now();
            $result['status'] = 'mapeado';
            return $result;
        }

        if ($this->isLojaRuralAgropecuaria($officialName)) {
            $result['payload']['tipo_local'] = 'unidade';
            $result['payload']['unicoop'] = '64';
            $result['payload']['area'] = '122';
            $result['payload']['centro_custo'] = '64.122';
            $result['payload']['centro_custo_nome'] = $officialName;
            $result['payload']['mapeamento_status'] = 'mapeado';
            $result['payload']['mapeamento_motivo'] = null;
            $result['payload']['atualizado_rateio_em'] = now();
            $result['status'] = 'mapeado';
            return $result;
        }

        $localType = $this->detectLocalType($officialName);
        if ($localType === 'ambiguous') {
            $result['reason'] = 'nome encontrado em unidades e departamentos';
            $result['payload']['mapeamento_status'] = 'pendente';
            $result['payload']['mapeamento_motivo'] = $result['reason'];
            return $result;
        }

        if ($localType === null) {
            $result['reason'] = 'local oficial não encontrado em unidades/departamentos';
            $result['payload']['mapeamento_status'] = 'pendente';
            $result['payload']['mapeamento_motivo'] = $result['reason'];
            return $result;
        }

        $result['payload']['tipo_local'] = $localType;
        $rateio = $this->rateioLocalService->resolveCentroCusto($officialName, $localType);

        if ($rateio === null) {
            $result['reason'] = 'centro de custo não encontrado na base mestre';
            $result['payload']['mapeamento_status'] = 'pendente';
            $result['payload']['mapeamento_motivo'] = $result['reason'];
            return $result;
        }

        $result['payload']['tipo_local'] = $rateio['tipo_local'];
        $result['payload']['nome_local'] = $rateio['nome_local'];
        $result['payload']['nome_local_normalizado'] = $rateio['nome_normalizado'];
        $result['payload']['unicoop'] = $this->padCenterComponent($rateio['unicoop'], 2);
        $result['payload']['area'] = $this->padCenterComponent($rateio['area'], 3);
        $result['payload']['centro_custo'] = $rateio['centro_custo'];
        $result['payload']['centro_custo_nome'] = $rateio['centro_custo_nome'];
        $result['payload']['mapeamento_status'] = 'mapeado';
        $result['payload']['mapeamento_motivo'] = null;
        $result['payload']['atualizado_rateio_em'] = now();
        $result['status'] = 'mapeado';

        return $result;
    }

    private function createSpreadsheetReader(string $fullPath)
    {
        $extension = mb_strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            $reader = new Csv();
            $reader->setDelimiter(';');
            $reader->setEnclosure('"');
            $reader->setSheetIndex(0);
            $reader->setInputEncoding('UTF-8');
            return $reader;
        }

        return IOFactory::createReaderForFile($fullPath);
    }

    private function resolvePath(string $path): string
    {
        $fullPath = Str::startsWith($path, ['/', 'C:', 'c:']) ? $path : base_path($path);
        if (! is_file($fullPath)) {
            throw new \RuntimeException('Arquivo não encontrado: ' . $fullPath);
        }

        return $fullPath;
    }

    private function mapUnidadeSetorName(string $value): string
    {
        $normalized = $this->normalizeComparisonValue($value);
        $map = [
            'almoxarifado' => 'Almoxarifado',
            'ambiental' => 'Meio Ambiente',
            'auditoria' => 'Auditoria Interna',
            'balanca' => 'Mandaguari II',
            'cafe' => 'UBC - Unidade de Beneficiamento de Café',
            'comercializacao' => 'Comercialização',
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
            'juridico' => 'Jurídico',
            'laboratorio' => 'Laboratório de Sementes',
            'marketing' => 'Marketing',
            'medicina' => 'Ambulatório/Medicina',
            'mix vegetal' => 'Mandaguari II',
            'originacao e operacao de cereais' => 'Originação e Operação de Cereais',
            'originação e operação de cereais' => 'Originação e Operação de Cereais',
            'ouvidoria' => 'Ouvidoria',
            'recepcao' => 'Recepção',
            'rh' => 'Recursos Humanos',
            'seguranca patrimonial' => 'Segurança Patrimonial',
            'seguranca trabalho' => 'Segurança do Trabalho',
            'seguranca do trabalho' => 'Segurança do Trabalho',
            'transcocari' => 'Transcocari',
            'centro de distribuicao' => 'Centro de Distribuição',
            'inovacao agricola' => 'Inovação Agrícola',
            'logistica' => 'Logística Integrada',
            'logistica integrada' => 'Logística Integrada',
            'suprimentos' => 'Suprimentos',
            'desenvolvimento' => 'Tecnologia da Informação',
            'gerencia' => 'Tecnologia da Informação',
            'processos de negocios' => 'Tecnologia da Informação',
            'processos de negócio' => 'Tecnologia da Informação',
            'suporte tecnico' => 'Tecnologia da Informação',
            'cristalina i' => 'Cristalina I',
            'cristalina ii' => 'Cristalina II',
            'loja rural agropecuaria' => 'Loja Rural Agropecuária',
            'loja rural agropecuária' => 'Loja Rural Agropecuária',
        ];

        return $map[$normalized] ?? trim($value);
    }

    private function normalizeComparisonValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(['_', '-', '\\', '\u00A0'], [' ', ' ', ' ', ' '], $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = mb_strtolower($value);
        $value = Str::of($value)->ascii()->value();
        $value = preg_replace('/[^a-z0-9 ]+/', ' ', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return $value;
    }

    private function isCristalinaI(string $name): bool
    {
        $normalized = mb_strtolower($name);
        return str_contains($normalized, 'cristalina i') && ! str_contains($normalized, 'cristalina ii');
    }

    private function isCristalinaIi(string $name): bool
    {
        $normalized = mb_strtolower($name);
        return str_contains($normalized, 'cristalina ii');
    }

    private function isLojaRuralAgropecuaria(string $name): bool
    {
        return mb_stripos($name, 'Loja Rural Agropecu') !== false;
    }

    private function detectLocalType(string $name): ?string
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return null;
        }

        $unitExists = DB::table('unidades')
            ->whereRaw('LOWER(TRIM(unidade)) = ?', [$normalized])
            ->exists();

        $deptExists = DB::table('departamentos')
            ->whereRaw('LOWER(TRIM(nome)) = ?', [$normalized])
            ->exists();

        if ($unitExists && $deptExists) {
            return 'ambiguous';
        }

        if ($unitExists) {
            return 'unidade';
        }

        if ($deptExists) {
            return 'departamento';
        }

        return null;
    }

    private function padCenterComponent(?string $value, int $length): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === '') {
            return null;
        }

        return str_pad(ltrim($digits, '0') === '' ? '0' : ltrim($digits, '0'), $length, '0', STR_PAD_LEFT);
    }

    private function incrementReason(array &$summary, string $reason): void
    {
        if (! isset($summary['reasons'][$reason])) {
            $summary['reasons'][$reason] = 0;
        }

        $summary['reasons'][$reason]++;
    }

    private function buildReportRow(array $payload): array
    {
        return [
            'email' => $payload['email'] ?? null,
            'nome' => $payload['nome'] ?? null,
            'status_google' => $payload['status_google'] ?? null,
            'nome_usuario' => $payload['nome_usuario'] ?? null,
            'ad_user_id' => $payload['ad_user_id'] ?? null,
            'ad_unidade_setor_original' => $payload['ad_unidade_setor_original'] ?? null,
            'nome_local_convertido' => $payload['nome_local'] ?? null,
            'nome_local_normalizado' => $payload['nome_local_normalizado'] ?? null,
            'tipo_local' => $payload['tipo_local'] ?? null,
            'unicoop' => $payload['unicoop'] ?? null,
            'area' => $payload['area'] ?? null,
            'centro_custo' => $payload['centro_custo'] ?? null,
            'mapeamento_status' => $payload['mapeamento_status'] ?? null,
            'mapeamento_motivo' => $payload['mapeamento_motivo'] ?? null,
        ];
    }

    private function generateReport(string $fileName, array $summary, array $rows): string
    {
        $directory = storage_path('app/reports');
        File::ensureDirectoryExists($directory);

        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;
        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível criar o relatório.');
        }

        fputcsv($handle, ['Indicador', 'Valor'], ';');
        fputcsv($handle, ['Total importado', $summary['total']], ';');
        fputcsv($handle, ['Criados', $summary['created'] ?? 0], ';');
        fputcsv($handle, ['Atualizados', $summary['updated'] ?? 0], ';');
        fputcsv($handle, ['Encontrado no AD', $summary['found_ad']], ';');
        fputcsv($handle, ['Não encontrado no AD', $summary['not_found_ad']], ';');
        fputcsv($handle, ['Mapeado', $summary['mapped']], ';');
        fputcsv($handle, ['Pendente', $summary['pending']], ';');
        if (isset($summary['skipped'])) {
            fputcsv($handle, ['Ignorados', $summary['skipped']], ';');
        }
        foreach ($summary['reasons'] as $reason => $count) {
            fputcsv($handle, ['Pendência: ' . $reason, $count], ';');
        }

        fputcsv($handle, []);
        fputcsv($handle, array_keys($rows[0] ?? []), ';');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        fclose($handle);

        return $filePath;
    }

    private function generateGoogleSyncReport(string $fileName, array $summary, array $rows): string
    {
        $directory = storage_path('app/reports');
        File::ensureDirectoryExists($directory);

        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;
        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível criar o relatório.');
        }

        fputcsv($handle, ['Indicador', 'Valor'], ';');
        fputcsv($handle, ['Total Google', $summary['google_total'] ?? 0], ';');
        fputcsv($handle, ['Novos e-mails', $summary['new_emails'] ?? 0], ';');
        fputcsv($handle, ['E-mails existentes', $summary['existing_emails'] ?? 0], ';');
        fputcsv($handle, ['Atualizados', $summary['updated_emails'] ?? 0], ';');
        fputcsv($handle, ['Já corretos', $summary['ja_correto'] ?? 0], ';');
        fputcsv($handle, ['Mapeados', $summary['mapped'] ?? 0], ';');
        fputcsv($handle, ['Pendentes', $summary['pending'] ?? 0], ';');
        fputcsv($handle, ['Sem AD', $summary['sem_ad'] ?? 0], ';');
        fputcsv($handle, ['Sem centro de custo', $summary['sem_centro_custo'] ?? 0], ';');
        fputcsv($handle, ['Trocas de centro de custo', $summary['center_cost_changes'] ?? 0], ';');
        foreach (($summary['reasons'] ?? []) as $reason => $count) {
            fputcsv($handle, ['Pendência: ' . $reason, $count], ';');
        }

        fputcsv($handle, []);
        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]), ';');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }
        }

        fclose($handle);

        return $filePath;
    }
}
