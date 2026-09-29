<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\GoogleWorkspaceDryRunSyncService;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\CoretiRateioMaintenanceService;
use App\Services\CoretiGoogleEmailImportService;
use App\Services\OfficeLicenseImportService;
use App\Services\OfficeUserSyncService;
use App\Services\OfficeCollaboratorMatcherService;
use App\Models\CmdbExpiration;
use App\Services\CmdbAssetHistoryService;
use App\Services\CmdbServerSpreadsheetImportService;
use App\Services\CmdbServerIpSyncService;
use App\Services\CmdbVCenterAssetCreationService;
use App\Services\JiraIssueService;
use App\Services\IntranetOrganization\IntranetOrganizationSyncService;
use App\Services\IntranetOrganization\IntranetHrSnapshotSyncService;
use App\Models\OrganizationLegacyMapping;
use App\Models\OrganizationUnit;
use App\Models\OrganizationDepartment;
use App\Models\OfficeLicense;
use App\Models\CmdbConnector;
use App\Services\CmdbSyncService;
use App\Services\Cmdb\Discovery\Connectors\VCenterSyncLock;
use App\Support\OfficeLicenseReportPolicy;
use App\Services\Cmdb\Veeam\VeeamApiException;
use App\Services\Cmdb\Veeam\VeeamBackupSyncService;
use App\Services\CoreTiSyncNotificationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cmdb:vcenter-sync {--connector=} {--dry-run} {--object-type=} {--limit=}', function (CmdbSyncService $syncService, VCenterSyncLock $lock) {
    $query = CmdbConnector::query()->whereIn('connector_type', ['vmware', 'vcenter']);
    if ($this->option('connector')) {
        $query->whereKey((int) $this->option('connector'));
    }

    $connector = $query->first();
    if (! $connector) {
        $this->error('Conector VMware não encontrado.');
        return 1;
    }

    $settings = is_array($connector->settings) ? $connector->settings : [];
    $originalSettings = $settings;
    if ($this->option('object-type')) {
        $settings['object_types'] = [(string) $this->option('object-type')];
    }
    if ((int) $this->option('limit') > 0) {
        $settings['runtime_limit'] = (int) $this->option('limit');
    }
    $connector->setAttribute('settings', $settings);

    $mutex = $lock->acquire($connector->id);
    if (! $mutex->get()) {
        $this->warn('Já existe uma sincronização em execução para este conector.');
        return 0;
    }

    try {
        if ($this->option('dry-run')) {
            $result = app(\App\Services\Cmdb\Discovery\DiscoveryEngine::class)->discover($connector)->toArray();
            $this->info('Dry-run concluído. Nenhuma descoberta foi persistida.');
            $this->line('Objetos encontrados: ' . count($result['items'] ?? []));
            foreach (($result['warnings'] ?? []) as $warning) {
                $this->warn($warning);
            }
            return ($result['success'] ?? false) ? 0 : 1;
        }

        $result = $syncService->syncConnector($connector);
        $summary = $result['summary'] ?? [];
        $this->info($result['message'] ?? 'Sincronização concluída.');
        $this->table(['Indicador', 'Total'], [
            ['Objetos encontrados', $summary['assets_found'] ?? 0],
            ['Enviados para revisão', $summary['assets_unchanged'] ?? 0],
            ['Falhas', $summary['assets_failed'] ?? 0],
        ]);
        return ($result['status'] ?? 'failed') === 'failed' ? 1 : 0;
    } finally {
        $connector->setAttribute('settings', $originalSettings);
        $mutex->release();
    }
})->purpose('Descobre o inventário VMware vCenter no CMDB sem alterar ativos automaticamente.');

Artisan::command('cmdb:sync-due-connectors {--type=}', function (CmdbSyncService $syncService, VCenterSyncLock $lock) {
    $type = (string) ($this->option('type') ?: 'vcenter');
    $now = now();
    $connectors = CmdbConnector::query()
        ->whereIn('connector_type', $type === 'vcenter' ? ['vmware', 'vcenter'] : [$type])
        ->where('is_active', true)
        ->get();

    foreach ($connectors as $connector) {
        if ($connector->last_connection_test_status !== 'success') {
            continue;
        }
        $interval = max(1, (int) ($connector->sync_interval_minutes ?: 60));
        if ($connector->last_sync_at && $connector->last_sync_at->copy()->addMinutes($interval)->isFuture()) {
            continue;
        }
        $mutex = $lock->acquire($connector->id);
        if (! $mutex->get()) {
            continue;
        }
        try {
            $result = $syncService->syncConnector($connector);
            $this->line($connector->name . ': ' . ($result['message'] ?? 'concluído'));
        } finally {
            $mutex->release();
        }
    }

    return 0;
})->purpose('Executa sincronizações vencidas de conectores CMDB.');

Artisan::command('cmdb:veeam-sync {--connector=} {--asset=} {--full} {--dry-run}', function (VeeamBackupSyncService $syncService, CoreTiSyncNotificationService $notificationService) {
    $query = CmdbConnector::query()
        ->where('connector_type', 'veeam')
        ->where('is_active', true);

    if ($this->option('connector')) {
        $query->whereKey((int) $this->option('connector'));
    }

    $connectors = $query->get();
    if ($connectors->isEmpty()) {
        $this->warn('Nenhum conector Veeam ativo foi configurado.');
        return 0;
    }

    $exitCode = 0;
    foreach ($connectors as $connector) {
        try {
            $summary = $syncService->sync($connector, [
                'asset_id' => $this->option('asset') ? (int) $this->option('asset') : null,
                'full' => (bool) $this->option('full'),
                'dry_run' => (bool) $this->option('dry-run'),
            ]);
            $this->info($connector->name . ': sincronização concluída.');
            $this->table(['Encontrados', 'Vinculados', 'Ambíguos', 'Não encontrados', 'Atualizados'], [[
                $summary['found'] ?? 0,
                $summary['matched'] ?? 0,
                $summary['ambiguous'] ?? 0,
                $summary['not_found'] ?? 0,
                $summary['updated'] ?? 0,
            ]]);
            if (! $this->option('dry-run')) {
                $notificationService->notify(
                    'Veeam Backup',
                    $summary,
                    ((int) ($summary['ambiguous'] ?? 0) > 0 || (int) ($summary['not_found'] ?? 0) > 0) ? 'warning' : 'success',
                    null,
                    ['details' => ['conector' => $connector->name]]
                );
            }
        } catch (VeeamApiException $exception) {
            $exitCode = 1;
            $this->error($connector->name . ': ' . $exception->getMessage());
            $connector->update([
                'status' => 'error',
                'last_sync_at' => now(),
                'last_sync_status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);
            $notificationService->notify(
                'Veeam Backup',
                ['errors' => 1],
                'failed',
                $exception->getMessage(),
                ['details' => ['conector' => $connector->name]]
            );
        }
    }

    return $exitCode;
})->purpose('Sincroniza a proteção das VMs no Veeam por meio da API oficial configurada.');

Schedule::command('cmdb:sync-due-connectors --type=vcenter')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Artisan::command('cmdb:vcenter-create-servers {--connector=} {--limit=}', function (CmdbVCenterAssetCreationService $service) {
    $connector = $this->option('connector') !== null ? (int) $this->option('connector') : null;
    $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
    $summary = $service->createPendingServers($connector, $limit);

    $this->info('Criação de ativos do vCenter concluída.');
    $this->table(['Encontradas', 'Criadas', 'Falhas'], [[
        $summary['found'],
        $summary['created'],
        $summary['failed'],
    ]]);

    foreach ($summary['issues'] as $issue) {
        $this->error(sprintf('Descoberta #%s (%s): %s', $issue['discovery_id'], $issue['name'], $issue['message']));
    }

    return $summary['failed'] > 0 ? 1 : 0;
})->purpose('Converte descobertas de VMs e hosts vCenter em ativos CMDB, sem converter redes/VLANs.');

Artisan::command('cmdb:vcenter-sync-ips {--connector=}', function (CmdbVCenterAssetCreationService $service) {
    $connector = $this->option('connector') !== null ? (int) $this->option('connector') : null;
    $summary = $service->syncExistingServerIps($connector);
    $this->info('IPs das VMs e hosts vCenter sincronizados.');
    $this->table(['Descobertas', 'Com IP', 'Sem IP'], [[$summary['found'], $summary['updated'], $summary['without_ip']]]);
    return 0;
})->purpose('Atualiza os endereços IP das VMs e hosts já criados a partir dos dados do vCenter.');

Artisan::command('intranet:sync-organization {--units} {--departments} {--full} {--dry-run} {--force} {--chunk=500}', function (IntranetOrganizationSyncService $syncService) {
    $syncUnits = (bool) $this->option('units');
    $syncDepartments = (bool) $this->option('departments');
    $full = (bool) $this->option('full');

    if (! $syncUnits && ! $syncDepartments && ! $full) {
        $full = true;
    }

    if ($full) {
        $syncUnits = true;
        $syncDepartments = true;
    }

    $summary = $syncService->sync(
        syncUnits: $syncUnits,
        syncDepartments: $syncDepartments,
        dryRun: (bool) $this->option('dry-run'),
        force: (bool) $this->option('force'),
    );

    $this->components->info(($summary['dry_run'] ?? false) ? 'Prévia da sincronização organizacional concluída.' : 'Sincronização organizacional concluída.');
    $this->table(['Indicador', 'Total'], [
        ['Unidades encontradas', $summary['units_found'] ?? 0],
        ['Unidades criadas', $summary['units_created'] ?? 0],
        ['Unidades atualizadas', $summary['units_updated'] ?? 0],
        ['Unidades sem alteração', $summary['units_unchanged'] ?? 0],
        ['Unidades ausentes', $summary['units_missing'] ?? 0],
        ['Departamentos encontrados', $summary['departments_found'] ?? 0],
        ['Departamentos criados', $summary['departments_created'] ?? 0],
        ['Departamentos atualizados', $summary['departments_updated'] ?? 0],
        ['Departamentos sem alteração', $summary['departments_unchanged'] ?? 0],
        ['Departamentos ausentes', $summary['departments_missing'] ?? 0],
        ['Tempo total (s)', $summary['duration_seconds'] ?? 0],
    ]);

    foreach (($summary['warnings'] ?? []) as $warning) {
        $this->warn($warning);
    }

    if (! empty($summary['batch_id'])) {
        $this->line('Batch: #' . $summary['batch_id']);
    }

    return ($summary['status'] ?? null) === 'failed' ? 1 : 0;
})->purpose('Sincroniza unidades e departamentos da Intranet para o snapshot local do CoreTI.');

Artisan::command('intranet:sync-hr-snapshots {--collaborators} {--cost-centers} {--full} {--dry-run}', function (IntranetHrSnapshotSyncService $syncService) {
    $syncCollaborators = (bool) $this->option('collaborators');
    $syncCostCenters = (bool) $this->option('cost-centers');
    $full = (bool) $this->option('full');

    if (! $syncCollaborators && ! $syncCostCenters && ! $full) {
        $full = true;
    }

    if ($full) {
        $syncCollaborators = true;
        $syncCostCenters = true;
    }

    $summary = $syncService->sync(
        syncCollaborators: $syncCollaborators,
        syncCostCenters: $syncCostCenters,
        dryRun: (bool) $this->option('dry-run'),
    );

    $this->components->info(($summary['dry_run'] ?? false) ? 'Prévia da sincronização RH concluída.' : 'Sincronização RH concluída.');
    $this->table(['Indicador', 'Total'], [
        ['Colaboradores encontrados', $summary['collaborators_found'] ?? 0],
        ['Colaboradores criados', $summary['collaborators_created'] ?? 0],
        ['Colaboradores atualizados', $summary['collaborators_updated'] ?? 0],
        ['Colaboradores sem alteração', $summary['collaborators_unchanged'] ?? 0],
        ['Centros de custo encontrados', $summary['cost_centers_found'] ?? 0],
        ['Centros de custo criados', $summary['cost_centers_created'] ?? 0],
        ['Centros de custo atualizados', $summary['cost_centers_updated'] ?? 0],
        ['Centros de custo sem alteração', $summary['cost_centers_unchanged'] ?? 0],
        ['Cargos encontrados', $summary['positions_found'] ?? 0],
        ['Cargos criados', $summary['positions_created'] ?? 0],
        ['Cargos atualizados', $summary['positions_updated'] ?? 0],
        ['Cargos sem alteração', $summary['positions_unchanged'] ?? 0],
        ['Tempo total (s)', $summary['duration_seconds'] ?? 0],
    ]);

    return 0;
})->purpose('Sincroniza colaboradores e centros de custo da Intranet para snapshots locais do CoreTI.');

Artisan::command('organization:audit-legacy-dependencies {--strict}', function () {
    $strict = (bool) $this->option('strict');
    $schemaRows = DB::table('information_schema.KEY_COLUMN_USAGE')
        ->select('TABLE_NAME', 'COLUMN_NAME', 'CONSTRAINT_NAME', 'REFERENCED_TABLE_NAME')
        ->whereRaw('TABLE_SCHEMA = DATABASE()')
        ->whereIn('REFERENCED_TABLE_NAME', ['unidades', 'departamentos'])
        ->get();

    $mappingPending = Schema::hasTable('organization_legacy_mappings')
        ? OrganizationLegacyMapping::query()->whereIn('status', ['manual_review', 'unresolved'])->count()
        : 0;

    $units = Schema::hasTable('organization_units') ? OrganizationUnit::query()->count() : 0;
    $departments = Schema::hasTable('organization_departments') ? OrganizationDepartment::query()->count() : 0;

    $legacyColumns = collect([
        ['office_licenses', 'unidade_id', 'organization_unit_id'],
        ['office_licenses', 'departamento_id', 'organization_department_id'],
        ['cmdb_assets', 'unit_id', 'organization_unit_id'],
        ['cmdb_racks', 'unit_id', 'organization_unit_id'],
        ['network_servicos_conectividade', 'unidade_id', 'organization_unit_id'],
        ['network_servicos_conectividade', 'departamento_id', 'organization_department_id'],
        ['circuitos_unidades', 'id_unidades', 'organization_unit_id'],
    ])->map(function (array $row) {
        [$table, $legacyColumn, $newColumn] = $row;

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $legacyColumn)) {
            return [$table, $legacyColumn, 'tabela/coluna ausente', 0];
        }

        $query = DB::table($table)->whereNotNull($legacyColumn);
        if (Schema::hasColumn($table, $newColumn)) {
            $query->whereNull($newColumn);
        }

        return [$table, $legacyColumn, $newColumn, $query->count()];
    });

    $integrityRows = collect([
        ['office_licenses', 'unidade_id', 'organization_unit_id', 'unit', 'unidades'],
        ['office_licenses', 'departamento_id', 'organization_department_id', 'department', 'departamentos'],
        ['cmdb_assets', 'unit_id', 'organization_unit_id', 'unit', 'unidades'],
        ['cmdb_racks', 'unit_id', 'organization_unit_id', 'unit', 'unidades'],
        ['network_servicos_conectividade', 'unidade_id', 'organization_unit_id', 'unit', 'unidades'],
        ['network_servicos_conectividade', 'departamento_id', 'organization_department_id', 'department', 'departamentos'],
        ['circuitos_unidades', 'id_unidades', 'organization_unit_id', 'unit', 'unidades'],
    ])->map(function (array $row) {
        [$table, $legacyColumn, $newColumn, $entityType, $legacyTable] = $row;

        if (! Schema::hasTable($table)) {
            return [$table, 0, 0, 0, 0, 0, 0];
        }

        $total = DB::table($table)->count();
        $withLegacy = Schema::hasColumn($table, $legacyColumn)
            ? DB::table($table)->whereNotNull($legacyColumn)->count()
            : 0;
        $withOrganization = Schema::hasColumn($table, $newColumn)
            ? DB::table($table)->whereNotNull($newColumn)->count()
            : 0;
        $withoutOrganization = Schema::hasColumn($table, $newColumn)
            ? DB::table($table)->whereNull($newColumn)->count()
            : $total;
        $resolvedMappings = OrganizationLegacyMapping::query()
            ->matched()
            ->where('entity_type', $entityType)
            ->where('legacy_table', $legacyTable)
            ->count();
        $unresolvedMappings = OrganizationLegacyMapping::query()
            ->where('entity_type', $entityType)
            ->where('legacy_table', $legacyTable)
            ->whereIn('status', ['manual_review', 'unresolved'])
            ->count();

        $mixed = 0;
        if (Schema::hasColumn($table, $legacyColumn)) {
            $organizationTable = $entityType === 'unit' ? 'organization_units' : 'organization_departments';
            $legacyValuesThatLookLikeOrganizationIds = Schema::hasTable($organizationTable)
                ? DB::table($organizationTable)->pluck('id')->map(fn ($id) => (string) $id)->all()
                : [];

            if ($legacyValuesThatLookLikeOrganizationIds !== []) {
                $mappedLegacyIds = OrganizationLegacyMapping::query()
                    ->where('entity_type', $entityType)
                    ->where('legacy_table', $legacyTable)
                    ->pluck('legacy_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $mixed = DB::table($table)
                    ->whereIn($legacyColumn, $legacyValuesThatLookLikeOrganizationIds)
                    ->whereNotIn($legacyColumn, $mappedLegacyIds)
                    ->count();
            }
        }

        return [$table . '.' . $legacyColumn, $total, $withLegacy, $withOrganization, $withoutOrganization, $resolvedMappings, $unresolvedMappings, $mixed];
    });

    $this->line('Snapshot organizacional local');
    $this->table(['Item', 'Total'], [
        ['organization_units', $units],
        ['organization_departments', $departments],
        ['Mapeamentos pendentes', $mappingPending],
    ]);

    $this->line('Foreign keys legadas');
    $this->table(['Tabela', 'Coluna', 'Constraint', 'Referência'], $schemaRows->map(fn ($row) => [
        $row->TABLE_NAME,
        $row->COLUMN_NAME,
        $row->CONSTRAINT_NAME,
        $row->REFERENCED_TABLE_NAME,
    ])->all());

    $this->line('Registros com ID legado sem vínculo organization_*');
    $this->table(['Tabela', 'Coluna legada', 'Coluna nova', 'Pendentes'], $legacyColumns->all());

    $this->line('Integridade de vínculos organizacionais');
    $this->table(
        ['Campo', 'Total', 'Com legado', 'Com organization', 'Sem organization', 'Mappings resolvidos', 'Mappings pendentes', 'Possível mistura'],
        $integrityRows->all()
    );

    $brokenOrganizationReferences = collect([
        ['office_licenses', 'organization_unit_id', 'organization_units'],
        ['office_licenses', 'organization_department_id', 'organization_departments'],
        ['cmdb_assets', 'organization_unit_id', 'organization_units'],
        ['cmdb_assets', 'organization_department_id', 'organization_departments'],
        ['cmdb_racks', 'organization_unit_id', 'organization_units'],
        ['cmdb_racks', 'organization_department_id', 'organization_departments'],
        ['network_servicos_conectividade', 'organization_unit_id', 'organization_units'],
        ['network_servicos_conectividade', 'organization_department_id', 'organization_departments'],
        ['circuitos_unidades', 'organization_unit_id', 'organization_units'],
        ['circuitos_unidades', 'organization_department_id', 'organization_departments'],
    ])->map(function (array $row) {
        [$table, $column, $targetTable] = $row;

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($targetTable)) {
            return [$table . '.' . $column, $targetTable, 0];
        }

        $broken = DB::table($table)
            ->leftJoin($targetTable, "{$targetTable}.id", '=', "{$table}.{$column}")
            ->whereNotNull("{$table}.{$column}")
            ->whereNull("{$targetTable}.id")
            ->count();

        return [$table . '.' . $column, $targetTable, $broken];
    });

    $this->line('Referências canônicas quebradas');
    $this->table(['Campo', 'Tabela alvo', 'Quebradas'], $brokenOrganizationReferences->all());

    $fallbackEnabled = (bool) config('organization.legacy_fallback_enabled');
    $activeLegacyCommands = [
        'units:sync-intranet',
        'departamentos:sync',
        'departamentos:import-areas',
        'sede-departamentos:sync',
        'circuits:repair-unit-links',
    ];

    if ($strict) {
        $this->line('Auditoria estrita');
        $this->table(['Item', 'Status'], [
            ['Fallback legado', $fallbackEnabled ? 'ativado' : 'desativado'],
            ['Commands legados conhecidos', implode(', ', $activeLegacyCommands)],
        ]);
    }

    $hasPending = $schemaRows->isNotEmpty()
        || $mappingPending > 0
        || $legacyColumns->contains(fn ($row) => (int) ($row[3] ?? 0) > 0)
        || $integrityRows->contains(fn ($row) => (int) ($row[7] ?? 0) > 0)
        || ($strict && $fallbackEnabled)
        || ($strict && $brokenOrganizationReferences->contains(fn ($row) => (int) ($row[2] ?? 0) > 0));

    $hasPending
        ? $this->warn('Ainda existem dependências legadas ou pendências de cutover.')
        : $this->components->info('Nenhuma dependência operacional pendente encontrada pela auditoria básica.');

    return $hasPending ? 1 : 0;
})->purpose('Audita dependências operacionais remanescentes das tabelas legadas unidades/departamentos.');

Artisan::command('organization:mappings:list {--status=pending}', function () {
    $status = (string) $this->option('status');
    $statuses = $status === 'pending'
        ? ['unresolved', 'manual_review']
        : array_filter(array_map('trim', explode(',', $status)));

    $mappings = OrganizationLegacyMapping::query()
        ->whereIn('status', $statuses)
        ->orderBy('entity_type')
        ->orderBy('legacy_table')
        ->orderBy('legacy_id')
        ->get();

    $impactCount = function (OrganizationLegacyMapping $mapping): int {
        $pairs = [
            ['office_licenses', 'departamento_id', 'departamentos'],
            ['network_servicos_conectividade', 'departamento_id', 'departamentos'],
            ['office_licenses', 'unidade_id', 'unidades'],
            ['cmdb_assets', 'unit_id', 'unidades'],
            ['cmdb_racks', 'unit_id', 'unidades'],
            ['network_servicos_conectividade', 'unidade_id', 'unidades'],
            ['circuitos_unidades', 'id_unidades', 'unidades'],
        ];

        return collect($pairs)
            ->filter(fn (array $pair): bool => $pair[2] === $mapping->legacy_table && Schema::hasTable($pair[0]) && Schema::hasColumn($pair[0], $pair[1]))
            ->sum(fn (array $pair): int => DB::table($pair[0])->where($pair[1], $mapping->legacy_id)->count());
    };

    $rows = $mappings->map(fn (OrganizationLegacyMapping $mapping): array => [
        $mapping->id,
        $mapping->entity_type,
        $mapping->legacy_table,
        $mapping->legacy_id,
        $mapping->source_id ?? '-',
        $mapping->organization_id ?? '-',
        $mapping->match_type ?? '-',
        $mapping->confidence ?? '-',
        $mapping->status,
        $impactCount($mapping),
        Str::limit((string) $mapping->notes, 80),
    ]);

    $this->table(
        ['ID', 'Tipo', 'Tabela legada', 'ID legado', 'Source sugerido', 'Organization ID', 'Match', 'Conf.', 'Status', 'Impacto', 'Notas'],
        $rows->all()
    );

    $this->line('Total: ' . $mappings->count());

    return $mappings->isEmpty() ? 0 : 1;
})->purpose('Lista mappings organizacionais pendentes ou por status.');

Artisan::command('organization:mappings:resolve {mapping_id} {--organization-id=} {--ignore} {--notes=}', function () {
    $mapping = OrganizationLegacyMapping::query()->find($this->argument('mapping_id'));
    if (! $mapping) {
        $this->error('Mapping não encontrado.');

        return 1;
    }

    $notes = trim((string) $this->option('notes'));
    if ($notes === '') {
        $this->error('Informe uma justificativa em --notes.');

        return 1;
    }

    if ((bool) $this->option('ignore')) {
        $mapping->update([
            'status' => 'ignored',
            'match_type' => 'manual_ignore',
            'notes' => trim(((string) $mapping->notes) . "\n" . $notes),
        ]);

        $this->components->info('Mapping ignorado formalmente.');

        return 0;
    }

    $organizationId = trim((string) $this->option('organization-id'));
    if ($organizationId === '') {
        $this->error('Informe --organization-id ou use --ignore.');

        return 1;
    }

    $exists = $mapping->entity_type === 'unit'
        ? OrganizationUnit::query()->whereKey($organizationId)->exists()
        : OrganizationDepartment::query()->whereKey($organizationId)->exists();

    if (! $exists) {
        $this->error('Registro canônico não encontrado para o tipo do mapping.');

        return 1;
    }

    $mapping->update([
        'organization_id' => $organizationId,
        'match_type' => 'manual',
        'confidence' => 100,
        'status' => 'matched',
        'notes' => trim(((string) $mapping->notes) . "\n" . $notes),
    ]);

    $this->components->info('Mapping resolvido manualmente.');

    return 0;
})->purpose('Resolve ou ignora formalmente um mapping legado.');

Artisan::command('organization:map-legacy', function () {
    if (! Schema::hasTable('organization_legacy_mappings')) {
        $this->error('Tabela organization_legacy_mappings não encontrada. Execute as migrations.');

        return 1;
    }

    $normalize = static function (?string $value): string {
        $value = Str::ascii(mb_strtolower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', (string) $value);

        return trim(preg_replace('/\s+/', ' ', (string) $value));
    };

    $summary = [
        'units_matched' => 0,
        'units_unresolved' => 0,
        'departments_matched' => 0,
        'departments_unresolved' => 0,
    ];

    if (Schema::hasTable('unidades') && Schema::hasTable('organization_units')) {
        $unitsBySource = OrganizationUnit::query()->get()->keyBy(fn ($unit) => (string) $unit->source_id);
        $unitsByCnpj = OrganizationUnit::query()->whereNotNull('cnpj')->get()->keyBy(fn ($unit) => preg_replace('/\D+/', '', (string) $unit->cnpj));
        $unitsByName = OrganizationUnit::query()->get()->keyBy(fn ($unit) => $normalize($unit->name));

        DB::table('unidades')->orderBy('id_unidades')->get()->each(function ($legacy) use ($unitsBySource, $unitsByCnpj, $unitsByName, $normalize, &$summary): void {
            $match = $unitsBySource->get((string) $legacy->id_unidades);
            $matchType = $match ? 'source_id' : null;

            if (! $match && filled($legacy->cnpj ?? null)) {
                $match = $unitsByCnpj->get(preg_replace('/\D+/', '', (string) $legacy->cnpj));
                $matchType = $match ? 'cnpj' : null;
            }

            if (! $match) {
                $match = $unitsByName->get($normalize($legacy->unidade ?? null));
                $matchType = $match ? 'normalized_name_exact' : null;
            }

            OrganizationLegacyMapping::updateOrCreate(
                [
                    'entity_type' => 'unit',
                    'legacy_table' => 'unidades',
                    'legacy_id' => (string) $legacy->id_unidades,
                ],
                [
                    'organization_id' => $match?->id,
                    'source_id' => $match?->source_id,
                    'match_type' => $matchType ?: 'unresolved',
                    'confidence' => $match ? 100 : null,
                    'status' => $match ? 'matched' : 'unresolved',
                    'notes' => $match ? null : 'Sem correspondência exata por source_id, CNPJ ou nome normalizado.',
                ]
            );

            $summary[$match ? 'units_matched' : 'units_unresolved']++;
        });
    }

    if (Schema::hasTable('departamentos') && Schema::hasTable('organization_departments')) {
        $departmentsByArea = OrganizationDepartment::query()
            ->whereNotNull('source_area_id')
            ->get()
            ->keyBy(fn ($department) => ltrim(preg_replace('/\D+/', '', (string) $department->source_area_id), '0') ?: '0');
        $departmentsByName = OrganizationDepartment::query()->get()->keyBy(fn ($department) => $normalize($department->name));

        DB::table('departamentos')->orderBy('id')->get()->each(function ($legacy) use ($departmentsByArea, $departmentsByName, $normalize, &$summary): void {
            $area = ltrim(preg_replace('/\D+/', '', (string) ($legacy->area ?? '')), '0') ?: '0';
            $match = $departmentsByArea->get($area);
            $matchType = $match ? 'area' : null;

            if (! $match) {
                $match = $departmentsByName->get($normalize($legacy->nome ?? null));
                $matchType = $match ? 'normalized_name_exact' : null;
            }

            OrganizationLegacyMapping::updateOrCreate(
                [
                    'entity_type' => 'department',
                    'legacy_table' => 'departamentos',
                    'legacy_id' => (string) $legacy->id,
                ],
                [
                    'organization_id' => $match?->id,
                    'source_id' => $match?->source_id,
                    'match_type' => $matchType ?: 'unresolved',
                    'confidence' => $match ? 100 : null,
                    'status' => $match ? 'matched' : 'unresolved',
                    'notes' => $match ? null : 'Sem correspondência exata por área ou nome normalizado.',
                ]
            );

            $summary[$match ? 'departments_matched' : 'departments_unresolved']++;
        });
    }

    $this->components->info('Mapeamento legado concluído.');
    $this->table(['Indicador', 'Total'], [
        ['Unidades mapeadas', $summary['units_matched']],
        ['Unidades não resolvidas', $summary['units_unresolved']],
        ['Departamentos mapeados', $summary['departments_matched']],
        ['Departamentos não resolvidos', $summary['departments_unresolved']],
    ]);

    return ($summary['units_unresolved'] + $summary['departments_unresolved']) > 0 ? 1 : 0;
})->purpose('Cria mapeamentos auditáveis das tabelas legadas para organization_units/organization_departments.');

Artisan::command('organization:backfill-links', function () {
    if (! Schema::hasTable('organization_legacy_mappings')) {
        $this->error('Tabela organization_legacy_mappings não encontrada.');

        return 1;
    }

    $summary = [];

    $backfill = function (string $table, string $legacyColumn, string $newColumn, string $entityType, string $legacyTable) use (&$summary): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $legacyColumn) || ! Schema::hasColumn($table, $newColumn)) {
            $summary[] = [$table, $legacyColumn, $newColumn, 'não aplicável', 0];
            return;
        }

        $updated = 0;
        DB::table($table)
            ->whereNotNull($legacyColumn)
            ->whereNull($newColumn)
            ->orderBy($table === 'circuitos_unidades' ? 'id_circuitos' : 'id')
            ->chunkById(500, function ($rows) use ($table, $legacyColumn, $newColumn, $entityType, $legacyTable, &$updated): void {
                foreach ($rows as $row) {
                    $idColumn = $table === 'circuitos_unidades' ? 'id_circuitos' : 'id';
                    $mapping = OrganizationLegacyMapping::query()
                        ->matched()
                        ->where('entity_type', $entityType)
                        ->where('legacy_table', $legacyTable)
                        ->where('legacy_id', (string) $row->{$legacyColumn})
                        ->first();

                    if (! $mapping?->organization_id) {
                        continue;
                    }

                    DB::table($table)
                        ->where($idColumn, $row->{$idColumn})
                        ->whereNull($newColumn)
                        ->update([$newColumn => $mapping->organization_id]);
                    $updated++;
                }
            }, $table === 'circuitos_unidades' ? 'id_circuitos' : 'id');

        $summary[] = [$table, $legacyColumn, $newColumn, 'atualizados', $updated];
    };

    $backfill('office_licenses', 'unidade_id', 'organization_unit_id', 'unit', 'unidades');
    $backfill('office_licenses', 'departamento_id', 'organization_department_id', 'department', 'departamentos');
    $backfill('cmdb_assets', 'unit_id', 'organization_unit_id', 'unit', 'unidades');
    $backfill('cmdb_racks', 'unit_id', 'organization_unit_id', 'unit', 'unidades');
    $backfill('network_servicos_conectividade', 'unidade_id', 'organization_unit_id', 'unit', 'unidades');
    $backfill('network_servicos_conectividade', 'departamento_id', 'organization_department_id', 'department', 'departamentos');
    $backfill('circuitos_unidades', 'id_unidades', 'organization_unit_id', 'unit', 'unidades');

    $this->components->info('Backfill organizacional concluído.');
    $this->table(['Tabela', 'Campo legado', 'Campo novo', 'Status', 'Total'], $summary);

    return 0;
})->purpose('Preenche organization_unit_id/organization_department_id a partir dos mapeamentos legados resolvidos.');

Artisan::command('applications:import {--programs-path=/var/www/html/plataforma_chamado/templates/programas} {--images-path=/var/www/html/plataforma_chamado/templates/img} {--truncate}', function () {
    $programsPath = rtrim((string) $this->option('programs-path'), '/');
    $imagesPath = rtrim((string) $this->option('images-path'), '/');

    if (! File::isDirectory($programsPath)) {
        $this->error("Diretório de programas não encontrado: {$programsPath}");

        return 1;
    }

    if (! File::isDirectory($imagesPath)) {
        $this->error("Diretório de imagens não encontrado: {$imagesPath}");

        return 1;
    }

    if ($this->option('truncate')) {
        DB::table('applications')->truncate();
        Storage::disk('local')->deleteDirectory('applications/files');
        File::deleteDirectory(public_path('applications/images'));
        $this->info('Aplicativos anteriores removidos.');
    }

    Storage::disk('local')->makeDirectory('applications/files');
    File::ensureDirectoryExists(public_path('applications/images'));

    $images = collect(File::files($imagesPath))
        ->mapWithKeys(fn ($file) => [Str::of($file->getFilenameWithoutExtension())->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value() => $file->getPathname()]);

    $imageAliases = [
        'acro' => 'acrobat',
        'reader' => 'acrobat',
        'advancedipscanner' => 'ipscanner',
        'ipscanner' => 'ipscanner',
        'bematech' => 'bematech',
        'bizagi' => 'bizagi',
        'citrix' => 'citrix',
        'earth' => 'earth',
        'googleearth' => 'earth',
        'hercules' => 'hercules',
        'jre' => 'java',
        'java' => 'java',
        'loopback' => 'loopback',
        'office2016' => 'office2016',
        'office2019' => 'office2019',
        'office365' => 'office365',
        'officesetup2016' => 'office2016',
        'officesetup2019' => 'office2019',
        'officesetup365' => 'office365',
        'produkey' => 'produkey',
        'serial' => 'serial',
        'smartclient' => 'smartclient',
        'totvs' => 'totvs',
        'ultravnc' => 'ultravnc',
        'vlc' => 'vlc',
        'vpn' => 'vpn',
        'zabbix' => 'zabbix',
        'zebra' => 'zebra',
        'qztray' => 'zebra',
        'zd105' => 'zebra',
        'apex' => 'apex',
    ];

    $nameAliases = [
        'acro' => 'Adobe Acrobat Reader',
        'advancedipscanner' => 'Advanced IP Scanner',
        'apex' => 'Apex',
        'bematech' => 'Bematech Spooler Drivers',
        'bizagi' => 'Bizagi',
        'citrix' => 'Citrix Workspace',
        'earth' => 'Google Earth Pro',
        'googleearth' => 'Google Earth Pro',
        'hercules' => 'Hercules',
        'jre' => 'Java Runtime 8',
        'java' => 'Java Runtime 8',
        'loopback' => 'Loopback',
        'office2016' => 'Office 2016',
        'office2019' => 'Office 2019',
        'office365' => 'Office 365',
        'produkey' => 'ProduKey',
        'pl2303windowsdrivermanual' => 'Manual Driver Serial PL2303',
        'pl23xxdriverinstaller' => 'Notas Driver Serial PL23XX',
        'pl23xxmlogodriver' => 'Driver Serial PL23XX',
        'smartclient' => 'SmartClient RH',
        'totvs' => 'TOTVS Launcher DI',
        'ultravnc' => 'UltraVNC',
        'vlc' => 'VLC Media Player',
        'gvcsetup' => 'VPN SonicWall',
        'certificado' => 'Certificado VPN',
        'zabbix' => 'Zabbix Agent',
        'qztray' => 'QZ Tray',
        'zd105' => 'Driver Zebra',
        'zebra' => 'Driver Zebra',
        'broadcastagro' => 'Broadcast Agro',
        'officesetup' => 'Office',
    ];

    $files = collect(File::allFiles($programsPath))
        ->filter(fn ($file) => $file->isFile())
        ->sortBy(fn ($file) => $file->getRelativePathname())
        ->values();

    $this->info("Arquivos encontrados: {$files->count()}");

    foreach ($files as $file) {
        $relativePath = str_replace('\\', '/', $file->getRelativePathname());
        $extension = strtolower($file->getExtension());
        $category = match ($file->getRelativePath()) {
            'vpn' => 'VPN',
            default => $file->getRelativePath() !== '' ? Str::headline($file->getRelativePath()) : 'Geral',
        };
        $fileName = $file->getFilename();
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $name = Str::of($baseName)
            ->replace(['_', '-'], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->headline()
            ->value();
        $slug = Str::slug($relativePath);
        $storedPath = "applications/files/{$relativePath}";
        $normalizedName = Str::of($relativePath)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();

        foreach ($nameAliases as $needle => $displayName) {
            if (str_contains($normalizedName, $needle)) {
                $name = $displayName;
                break;
            }
        }

        File::ensureDirectoryExists(dirname(Storage::disk('local')->path($storedPath)));
        File::copy($file->getPathname(), Storage::disk('local')->path($storedPath));

        $imageSource = null;

        foreach ($imageAliases as $needle => $imageKey) {
            if (str_contains($normalizedName, $needle) && $images->has($imageKey)) {
                $imageSource = $images->get($imageKey);
                break;
            }
        }

        $imagePath = null;
        if ($imageSource) {
            $imageExtension = pathinfo($imageSource, PATHINFO_EXTENSION);
            $imageFileName = "{$slug}.{$imageExtension}";
            File::copy($imageSource, public_path("applications/images/{$imageFileName}"));
            $imagePath = "applications/images/{$imageFileName}";
        } elseif ($images->has('profileplaceholder')) {
            $imageSource = $images->get('profileplaceholder');
            $imageExtension = pathinfo($imageSource, PATHINFO_EXTENSION);
            $imageFileName = "{$slug}.{$imageExtension}";
            File::copy($imageSource, public_path("applications/images/{$imageFileName}"));
            $imagePath = "applications/images/{$imageFileName}";
        }

        DB::table('applications')->updateOrInsert(
            ['slug' => $slug],
            [
                'name' => $name,
                'category' => $category,
                'file_name' => $fileName,
                'file_extension' => $extension,
                'file_size' => $file->getSize(),
                'file_path' => $storedPath,
                'image_path' => $imagePath,
                'is_bundle' => false,
                'bundle_files' => null,
                'source_path' => $file->getPathname(),
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    $bundles = [
        [
            'name' => 'VPN Kit',
            'slug' => 'vpn-kit',
            'category' => 'Kits',
            'image_key' => 'vpn',
            'files' => [
                'vpn/184-011500-00_REV_A_GVCSetup64.exe',
                'vpn/Certificado.rcf',
            ],
        ],
        [
            'name' => 'Zebra Kit',
            'slug' => 'zebra-kit',
            'category' => 'Kits',
            'image_key' => 'zebra',
            'files' => [
                'zebra/qz-tray-2.1.5.exe',
                'zebra/zd105127605-certified.exe',
            ],
        ],
        [
            'name' => 'Serial Driver Kit',
            'slug' => 'serial-driver-kit',
            'category' => 'Kits',
            'image_key' => 'serial',
            'files' => [
                'serial/PL23XX_DriverInstallerv2.0.5_ReleaseNote.txt',
                'serial/PL23XX-M_LogoDriver_Setup_v205_20210129.exe',
                'serial/PL2303 Windows Driver Manual v1.23.0.pdf',
            ],
        ],
    ];

    foreach ($bundles as $bundle) {
        $bundleFiles = [];
        $bundleSize = 0;

        foreach ($bundle['files'] as $relativePath) {
            $storedPath = "applications/files/{$relativePath}";

            if (! Storage::disk('local')->exists($storedPath)) {
                continue;
            }

            $bundleFiles[] = [
                'path' => $storedPath,
                'name' => basename($relativePath),
            ];
            $bundleSize += Storage::disk('local')->size($storedPath);
        }

        if ($bundleFiles === []) {
            continue;
        }

        $imagePath = null;
        if ($images->has($bundle['image_key'])) {
            $imageSource = $images->get($bundle['image_key']);
            $imageExtension = pathinfo($imageSource, PATHINFO_EXTENSION);
            $imageFileName = "{$bundle['slug']}.{$imageExtension}";
            File::copy($imageSource, public_path("applications/images/{$imageFileName}"));
            $imagePath = "applications/images/{$imageFileName}";
        }

        DB::table('applications')->updateOrInsert(
            ['slug' => $bundle['slug']],
            [
                'name' => $bundle['name'],
                'category' => $bundle['category'],
                'file_name' => "{$bundle['slug']}.zip",
                'file_extension' => 'zip',
                'file_size' => $bundleSize,
                'file_path' => '',
                'image_path' => $imagePath,
                'is_bundle' => true,
                'bundle_files' => json_encode($bundleFiles),
                'source_path' => null,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    $this->info('Importação de aplicativos concluída.');

    return 0;
})->purpose('Importa aplicativos e imagens do plataforma_chamado para o CoreTI');

Artisan::command('workspace:sync-dry-run {--cache-preview}', function (GoogleWorkspaceDryRunSyncService $syncService) {
    $this->components->info('Iniciando validação dry-run do Google Workspace (somente leitura)...');

    try {
        $result = $this->option('cache-preview')
            ? $syncService->buildAndCachePreview()
            : $syncService->run();
    } catch (\Throwable $e) {
        $this->components->error('Falha no dry-run: ' . $e->getMessage());
        return 1;
    }

    $summary = $result['summary'];
    $this->newLine();
    $this->table(
        ['Métrica', 'Valor'],
        [
            ['Google total', $summary['google_total']],
            ['Novos e-mails', $summary['new_emails'] ?? 0],
            ['E-mails existentes', $summary['existing_emails'] ?? 0],
            ['Atualizados', $summary['updated_emails'] ?? 0],
            ['Mapeados', $summary['mapped'] ?? 0],
            ['Pendentes', $summary['pending'] ?? 0],
            ['Sem AD', $summary['sem_ad'] ?? 0],
            ['Sem centro de custo real', $summary['sem_centro_custo'] ?? 0],
            ['Trocas de centro de custo', $summary['center_cost_changes'] ?? 0],
            ['Já corretos', $summary['ja_correto'] ?? 0],
        ]
    );

    $samples = $result['samples'];

    $this->newLine();
    $this->components->warn('Amostra: novos e-mails (até 20)');
    foreach ($samples['missing_in_coreti'] as $entry) {
        $email = is_array($entry) ? (string) ($entry['email'] ?? '') : (string) $entry;
        $matricula = is_array($entry) ? (string) ($entry['matricula'] ?? '') : '';
        $suffix = $matricula !== '' ? " (matrícula: {$matricula})" : '';
        $this->line("- {$email}{$suffix}");
    }

    $this->newLine();
    $this->components->warn('Amostra: trocas de centro de custo (até 20)');
    foreach ($samples['center_cost_changes'] as $entry) {
        $email = is_array($entry) ? (string) ($entry['email'] ?? '') : (string) $entry;
        $old = is_array($entry) ? (string) ($entry['centro_custo_anterior'] ?? '') : '';
        $new = is_array($entry) ? (string) ($entry['centro_custo_novo'] ?? '') : '';
        $this->line("- {$email} ({$old} -> {$new})");
    }

    $this->newLine();
    $this->components->warn('Amostra: faltando no Google (até 20)');
    foreach ($samples['missing_in_google'] as $email) {
        $this->line("- {$email}");
    }

    $this->newLine();
    $this->components->warn('Amostra: no CoreTI, mas suspensos no Google (até 20)');
    foreach ($samples['present_but_suspended'] as $email) {
        $this->line("- {$email}");
    }

    $this->newLine();
    $this->components->info('Dry-run concluído. Nenhum dado foi alterado.');
    return 0;
})->purpose('Compara Google Workspace x CoreTI em modo somente leitura (dry-run).');

Artisan::command('workspace:sync-auto', function (GoogleWorkspaceDryRunSyncService $syncService, CoreTiSyncNotificationService $notificationService) {
    $this->components->info('Executando sincronização automática do Google Workspace...');

    try {
        $preview = $syncService->buildAndCachePreview();
        $summary = is_array($preview['summary'] ?? null) ? $preview['summary'] : [];
        $hasChanges =
            (int) ($summary['new_emails'] ?? 0) > 0
            || (int) ($summary['updated_emails'] ?? 0) > 0
            || (int) ($summary['center_cost_changes'] ?? 0) > 0;

        if (! $hasChanges) {
            $this->components->info('Nenhuma alteração pendente. Sync automática ignorada.');
            return 0;
        }

        $result = $syncService->applyFromCachedPreview();
    } catch (\Throwable $e) {
        $this->components->error('Falha na sync automática: ' . $e->getMessage());
        $notificationService->notify('Google Workspace', ['errors' => 1], 'failed', $e->getMessage(), [
            'url' => url('/service-desk'),
        ]);
        return 1;
    }

    $summary = $result['summary'] ?? [];
    $this->newLine();
    $this->table(
        ['Métrica', 'Valor'],
        [
            ['Google total', $summary['google_total'] ?? 0],
            ['Novos e-mails', $summary['new_emails'] ?? 0],
            ['E-mails existentes', $summary['existing_emails'] ?? 0],
            ['Atualizados', $summary['updated_emails'] ?? 0],
            ['Mapeados', $summary['mapped'] ?? 0],
            ['Pendentes', $summary['pending'] ?? 0],
            ['Sem AD', $summary['sem_ad'] ?? 0],
            ['Sem centro de custo real', $summary['sem_centro_custo'] ?? 0],
            ['Trocas de centro de custo', $summary['center_cost_changes'] ?? 0],
            ['Já corretos', $summary['ja_correto'] ?? 0],
        ]
    );

    $this->newLine();
    $this->components->info('Sync automática concluída.');
    $workspaceStatus = ((int) ($summary['pending'] ?? 0) > 0 || (int) ($summary['sem_centro_custo'] ?? 0) > 0) ? 'warning' : 'success';
    $notificationService->notify('Google Workspace', $summary, $workspaceStatus, null, [
        'url' => url('/service-desk'),
    ]);

    return 0;
})->purpose('Executa a sincronização automática do Google Workspace quando houver mudanças.');

Artisan::command('cmdb:check-expirations', function (JiraIssueService $jiraIssueService, CmdbAssetHistoryService $historyService) {
    if (! Schema::hasTable('cmdb_expirations')) {
        $this->components->warn('Tabela cmdb_expirations não encontrada.');
        return 0;
    }

    $items = CmdbExpiration::query()
        ->with('asset')
        ->where('status', 'pending')
        ->whereDate('expiration_date', '<=', now()->addDays(90)->toDateString())
        ->orderBy('expiration_date')
        ->get();

    $analyzed = 0;
    $eligible = 0;
    $ignoredWithJira = 0;
    $created = 0;
    $simulated = 0;
    $errors = 0;

    foreach ($items as $item) {
        $analyzed++;

        if ($item->jira_issue_key) {
            $ignoredWithJira++;
            continue;
        }

        $daysBefore = (int) ($item->alert_days_before ?? 30);
        if ($item->expiration_date && now()->addDays($daysBefore)->startOfDay()->lt($item->expiration_date->startOfDay())) {
            continue;
        }

        $eligible++;
        $result = $jiraIssueService->createIssueForExpiration($item);

        if (($result['status'] ?? null) === 'created') {
            $created++;
        } elseif (($result['status'] ?? null) === 'simulated') {
            $simulated++;
        } else {
            $errors++;
            continue;
        }

        $item->update([
            'jira_issue_key' => $result['issue_key'] ?? $item->jira_issue_key,
            'jira_issue_url' => $result['issue_url'] ?? $item->jira_issue_url,
            'status' => 'notified',
            'notified_at' => now(),
        ]);

        if (($result['status'] ?? null) === 'created' && $item->asset) {
            $historyService->record(
                $item->asset,
                null,
                'jira_issue_created',
                'Chamado Jira criado',
                $item->title,
                [],
                $item->fresh()->toArray()
            );
        }
    }

    $this->components->table(
        ['Métrica', 'Valor'],
        [
            ['Analisados', $analyzed],
            ['Elegíveis para ação', $eligible],
            ['Ignorados por já possuírem Jira', $ignoredWithJira],
            ['Chamados criados', $created],
            ['Chamados simulados', $simulated],
            ['Erros', $errors],
        ]
    );

    $this->components->info("Vencimentos processados. Jira criado: {$created} | Simulados: {$simulated} | Ignorados com Jira: {$ignoredWithJira} | Erros: {$errors}");
    return 0;
})->purpose('Verifica vencimentos do CMDB e abre chamados no Jira quando necessário');

Schedule::command('workspace:sync-dry-run --cache-preview')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('workspace:sync-auto')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('cmdb:check-expirations')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

Artisan::command('units:sync-intranet', function () {
    $this->components->error('Comando desativado. Use intranet:sync-organization --units ou --full.');

    return 1;

    $this->components->info('Sincronizando unidades da intranet_cocari.cadUnicoop...');

    try {
        $rows = DB::connection('intranet_cocari')
            ->table('cadUnicoop')
            ->select(['IdUnicoop', 'Nome', 'CNPJ', 'Endereco', 'Complemento'])
            ->where('Ativo', 1)
            ->whereNotNull('IdUnicoop')
            ->whereNotNull('Nome')
            ->orderBy('Nome')
            ->get();
    } catch (\Throwable $e) {
        $this->components->error('Falha ao consultar intranet_cocari: ' . $e->getMessage());
        return 1;
    }

    if ($rows->isEmpty()) {
        $this->components->warn('Nenhuma unidade ativa encontrada na origem.');
        return 0;
    }

    $excludedNormNames = [
        Str::of('Escritório Comercial em Catalão')->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value(),
    ];

    $payload = $rows
        ->map(function ($row): array {
            $nome = trim((string) $row->Nome);
            $cnpj = trim((string) ($row->CNPJ ?? ''));
            $endereco = trim(implode(', ', array_filter([
                trim((string) ($row->Endereco ?? '')),
                trim((string) ($row->Complemento ?? '')),
            ])));

            return [
                'id_unidades' => (int) $row->IdUnicoop,
                'unidade' => $nome,
                'cnpj' => $cnpj !== '' ? mb_substr($cnpj, 0, 20) : null,
                'endereco' => $endereco !== '' ? mb_substr($endereco, 0, 255) : null,
            ];
        })
        ->filter(function (array $item) use ($excludedNormNames): bool {
            if ($item['id_unidades'] <= 0 || $item['unidade'] === '') {
                return false;
            }

            if ((int) $item['id_unidades'] === 999999) {
                return false;
            }

            $normalized = Str::of($item['unidade'])->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();

            return ! in_array($normalized, $excludedNormNames, true);
        })
        ->values()
        ->all();

    if (empty($payload)) {
        $this->components->warn('Nenhuma linha válida para sincronização.');
        return 0;
    }

    $sourceByNormName = [];
    foreach ($payload as $item) {
        $normName = Str::of($item['unidade'])->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();
        $sourceByNormName[$normName] = $item;
    }

    $existingUnits = DB::table('unidades')->get(['id_unidades', 'unidade']);
    foreach ($existingUnits as $existing) {
        $existingNorm = Str::of((string) $existing->unidade)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();
        $source = $sourceByNormName[$existingNorm] ?? null;

        if (! $source) {
            continue;
        }

        $sourceId = (int) $source['id_unidades'];
        $existingId = (int) $existing->id_unidades;

        if ($existingId === $sourceId) {
            continue;
        }

        DB::table('circuitos_unidades')
            ->where('id_unidades', $existingId)
            ->update(['id_unidades' => $sourceId]);

        DB::table('unidades')
            ->where('id_unidades', $existingId)
            ->delete();
    }

    $unitColumns = Schema::getColumnListing('unidades');
    $canSyncCnpj = in_array('cnpj', $unitColumns, true);
    $canSyncEndereco = in_array('endereco', $unitColumns, true);

    if (! $canSyncCnpj || ! $canSyncEndereco) {
        $payload = array_map(function (array $item): array {
            return [
                'id_unidades' => $item['id_unidades'],
                'unidade' => $item['unidade'],
            ];
        }, $payload);
    }

    $updateColumns = ['unidade'];
    if ($canSyncCnpj) {
        $updateColumns[] = 'cnpj';
    }
    if ($canSyncEndereco) {
        $updateColumns[] = 'endereco';
    }

    DB::table('unidades')->upsert(
        $payload,
        ['id_unidades'],
        $updateColumns
    );

    // Mantém apenas unidades ativas da origem na base local.
    $activeIds = array_values(array_unique(array_map(
        fn (array $item): int => (int) $item['id_unidades'],
        $payload
    )));

    DB::table('circuitos_unidades')
        ->whereNotNull('id_unidades')
        ->whereNotIn('id_unidades', $activeIds)
        ->update(['id_unidades' => null]);

    DB::table('unidades')
        ->whereNotIn('id_unidades', $activeIds)
        ->delete();

    foreach ($excludedNormNames as $excludedNormName) {
        $toDeleteIds = DB::table('unidades')
            ->get(['id_unidades', 'unidade'])
            ->filter(function ($row) use ($excludedNormName) {
                $norm = Str::of((string) $row->unidade)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();
                return $norm === $excludedNormName;
            })
            ->pluck('id_unidades')
            ->all();

        if ($toDeleteIds !== []) {
            DB::table('circuitos_unidades')
                ->whereIn('id_unidades', $toDeleteIds)
                ->update(['id_unidades' => null]);

            DB::table('unidades')
                ->whereIn('id_unidades', $toDeleteIds)
                ->delete();
        }
    }

    DB::table('unidades')->where('id_unidades', 999999)->delete();
    DB::table('unidades')->where('unidade', 'like', '%Escritório Comercial em Catalão%')->delete();

    $this->components->info('Sincronização concluída.');
    $this->line('Registros processados: ' . count($payload));

    return 0;
})->purpose('Sincroniza unidades locais com cadUnicoop (nome, CNPJ e endereço).');

Artisan::command('departamentos:sync', function () {
    $this->components->error('Comando desativado. Use intranet:sync-organization --departments ou --full.');

    return 1;

    $this->components->info('Sincronizando tabela departamentos...');

    $normalize = static fn (?string $value): string => trim((string) $value);
    $rows = [];

    // Base local: somente escopo de departamentos (Sede).
    $local = DB::table('service_desk_email_cost_centers')
        ->select(['name', 'unicoop', 'area', 'scope'])
        ->where('scope', 'sede')
        ->get();

    foreach ($local as $item) {
        $nome = $normalize($item->name);
        if ($nome === '') {
            continue;
        }

        $rows[] = [
            'nome' => mb_substr($nome, 0, 255),
            'unicoop' => ($u = $normalize($item->unicoop)) !== '' ? mb_substr($u, 0, 10) : null,
            'area' => ($a = $normalize($item->area)) !== '' ? mb_substr($a, 0, 50) : null,
            'ativo' => true,
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }

    // Complemento da intranet para centros de custo RH (departamentos).
    try {
        $rh = DB::connection('intranet_cocari')
            ->table('cadCentroCustoRh')
            ->select(['Nome', 'IdUnicoop', 'IdCentroCusto'])
            ->get();

        foreach ($rh as $item) {
            $nome = $normalize($item->Nome);
            if ($nome === '') {
                continue;
            }

            $unicoop = $normalize((string) $item->IdUnicoop);
            if ($unicoop !== '' && ctype_digit($unicoop)) {
                $unicoop = str_pad($unicoop, 2, '0', STR_PAD_LEFT);
            }

            $area = $normalize((string) $item->IdCentroCusto);
            if (preg_match('/^1\d{3}$/', $area)) {
                $area = '0' . mb_substr($area, 0, 1) . '.' . mb_substr($area, 1);
            }

            $rows[] = [
                'nome' => mb_substr($nome, 0, 255),
                'unicoop' => $unicoop !== '' ? mb_substr($unicoop, 0, 10) : null,
                'area' => $area !== '' ? mb_substr($area, 0, 50) : null,
                'ativo' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }
    } catch (\Throwable $e) {
        $this->components->warn('Sem complemento da intranet_cocari: ' . $e->getMessage());
    }

    if ($rows === []) {
        $this->components->warn('Nenhum departamento para sincronizar.');
        return 0;
    }

    DB::table('departamentos')->upsert(
        $rows,
        ['nome', 'unicoop', 'area'],
        ['ativo', 'updated_at']
    );

    // Limpeza: remove entradas que correspondem a unidades cadastradas.
    $unitNamesNorm = DB::table('unidades')
        ->pluck('unidade')
        ->map(fn ($name) => (string) Str::of((string) $name)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value())
        ->filter()
        ->values()
        ->all();

    if ($unitNamesNorm !== []) {
        DB::table('departamentos')
            ->get(['id', 'nome'])
            ->each(function ($dep) use ($unitNamesNorm): void {
                $depNorm = (string) Str::of((string) $dep->nome)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();
                if ($depNorm !== '' && in_array($depNorm, $unitNamesNorm, true)) {
                    DB::table('departamentos')->where('id', $dep->id)->delete();
                }
            });
    }

    $this->components->info('Sincronização concluída.');
    $this->line('Registros processados: ' . count($rows));

    return 0;
})->purpose('Sincroniza departamentos (nome, unicoop, area) para integrações futuras.');

Artisan::command('departamentos:import-areas {path=Areas.txt}', function () {
    $this->components->error('Comando desativado. Departamentos oficiais agora vêm de organization_departments via intranet:sync-organization.');

    return 1;

    $path = (string) $this->argument('path');
    $fullPath = str_starts_with($path, '/') ? $path : base_path($path);

    if (! is_file($fullPath)) {
        $this->components->error("Arquivo não encontrado: {$fullPath}");
        return 1;
    }

    $raw = file_get_contents($fullPath);
    if ($raw === false) {
        $this->components->error('Não foi possível ler o arquivo.');
        return 1;
    }

    // Normaliza encoding (relatório legado costuma vir CP850/ISO-8859-1)
    $text = @iconv('CP850', 'UTF-8//IGNORE', $raw);
    if ($text === false) {
        $text = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $raw);
    }
    if ($text === false) {
        $text = $raw;
    }

    $toTitle = static function (string $value): string {
        $value = mb_strtolower($value, 'UTF-8');
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    };

    $canonicalize = static function (string $nome) use ($toTitle): string {
        $n = trim($nome);
        $n = preg_replace('/\s+/u', ' ', $n) ?: $n;

        // Expansões de abreviações comuns do legado.
        $replace = [
            '/\bDEPART\.\b/ui' => 'DEPARTAMENTO',
            '/\bDEPTO\.\b/ui' => 'DEPARTAMENTO',
            '/\bDEPTO\b/ui' => 'DEPARTAMENTO',
            '/\bREC\.\b/ui' => 'RECURSOS',
            '/\bHUMANOS\b/ui' => 'HUMANOS',
            '/\bADM\.\b/ui' => 'ADMINISTRACAO',
            '/\bDIV\.\b/ui' => 'DIVISAO',
        ];
        foreach ($replace as $pattern => $with) {
            $n = preg_replace($pattern, $with, $n) ?: $n;
        }

        $n = preg_replace('/\s+/u', ' ', $n) ?: $n;
        $n = trim($n);

        // Casos canônicos obrigatórios.
        $norm = (string) Str::of($n)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();
        $forced = [
            'rh' => 'Departamento de Recursos Humanos',
            'departamentoderecursoshumanos' => 'Departamento de Recursos Humanos',
            'departrechumanos' => 'Departamento de Recursos Humanos',
            'deptoderecursoshumanos' => 'Departamento de Recursos Humanos',
            'depto derecursoshumanos' => 'Departamento de Recursos Humanos',
            'departamentorechumanos' => 'Departamento de Recursos Humanos',
            'departamento rec humanos' => 'Departamento de Recursos Humanos',
            'departamentorecursoshumanos' => 'Departamento de Recursos Humanos',
        ];
        if (isset($forced[$norm])) {
            return $forced[$norm];
        }

        return $toTitle($n);
    };

    $rows = [];
    foreach (preg_split('/\R/u', (string) $text) as $line) {
        // Lê linhas do bloco numérico: "001 ALGODAO ..."
        if (! preg_match('/^\s*(\d{3})\s+([A-Z0-9\.\-\/\(\)ÇÁÀÂÃÉÊÍÓÔÕÚÜ ]{3,})/u', $line, $m)) {
            continue;
        }

        $codigo = trim((string) $m[1]);
        $nome = trim((string) $m[2]);
        $nome = preg_replace('/\s{2,}.*/u', '', $nome) ?: $nome;
        $nome = trim((string) $nome);
        $nome = $canonicalize($nome);

        if ($nome === '' || $codigo === '') {
            continue;
        }

        $rows[] = [
            'nome' => mb_substr($nome, 0, 255),
            'unicoop' => '01',
            'area' => '01.' . $codigo,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    if ($rows === []) {
        $this->components->warn('Nenhum departamento encontrado no arquivo.');
        return 0;
    }

    DB::table('departamentos')->upsert(
        $rows,
        ['nome', 'unicoop', 'area'],
        ['ativo', 'updated_at']
    );

    $this->components->info('Importação do Areas.txt concluída.');
    $this->line('Registros processados: ' . count($rows));

    return 0;
})->purpose('Importa departamentos do arquivo Areas.txt para a tabela departamentos (unicoop 01).');

Artisan::command('sede-departamentos:sync', function () {
    $this->components->error('Comando desativado. Use organization_departments como catálogo canônico local.');

    return 1;

    $this->components->info('Sincronizando tabela sede_departamentos...');

    $rows = DB::table('departamentos')
        ->where('unicoop', '01')
        ->whereNotNull('nome')
        ->whereRaw('TRIM(nome) <> ""')
        ->select(['nome', 'unicoop', 'area', 'origem'])
        ->get()
        ->map(function ($row): array {
            return [
                'nome_departamento' => mb_substr(trim((string) $row->nome), 0, 255),
                'unicoop' => trim((string) ($row->unicoop ?? '01')) ?: '01',
                'area' => ($a = trim((string) ($row->area ?? ''))) !== '' ? mb_substr($a, 0, 50) : null,
                'ativo' => true,
                'origem' => 'departamentos',
                'updated_at' => now(),
                'created_at' => now(),
            ];
        })
        ->all();

    if ($rows === []) {
        $this->components->warn('Nenhum departamento de sede encontrado para sincronizar.');
        return 0;
    }

    DB::table('sede_departamentos')->upsert(
        $rows,
        ['nome_departamento'],
        ['unicoop', 'area', 'ativo', 'origem', 'updated_at']
    );

    $this->components->info('Sincronização concluída.');
    $this->line('Registros processados: ' . count($rows));

    return 0;
})->purpose('Mantém tabela de referência de departamentos da sede.');

Artisan::command('coreti:import-rateio-locais {path=storage/app/imports/coreti_rateio_locais_import_ajustado.csv}', function () {
    try {
        $result = app(CoretiRateioMaintenanceService::class)->importCsv((string) $this->argument('path'));
    } catch (\Throwable $e) {
        $this->components->error($e->getMessage());
        return 1;
    }

    $this->components->info('Importação concluída.');
    $this->line('Inseridos: ' . ($result['inserted'] ?? 0));
    $this->line('Atualizados: ' . ($result['updated'] ?? 0));
    $this->line('Ignorados: ' . ($result['ignored'] ?? 0));
    $this->line('Erros: ' . ($result['errors'] ?? 0));

    return 0;
})->purpose('Importa a base mestre de rateio (coreti_rateio_locais) a partir de CSV.');

Artisan::command('coreti:rateio-auditar {path?}', function () {
    try {
        $path = $this->argument('path');
        $result = app(CoretiRateioMaintenanceService::class)->auditRateioFile($path ? (string) $path : null);
    } catch (\Throwable $e) {
        $this->components->error($e->getMessage());
        return 1;
    }

    $summary = $result['summary'] ?? [];
    $this->components->info('Auditoria concluída.');
    $this->line('Total: ' . ($summary['total'] ?? 0));
    $this->line('Em conformidade: ' . ($summary['matched'] ?? 0));
    $this->line('Divergentes: ' . ($summary['divergent'] ?? 0));
    $this->line('Pendentes: ' . ($summary['pending'] ?? 0));
    $this->line('Cristalina II manual: ' . ($summary['manual'] ?? 0));
    $this->line('Relatório: ' . ($result['report_path'] ?? '-'));

    return 0;
})->purpose('Audita o rateio atual contra a base mestre e gera relatório.');

Artisan::command('coreti:rateio-corrigir {--dry-run} {--apply}', function () {
    $apply = (bool) $this->option('apply');
    $dryRun = $apply ? false : true;

    try {
        $result = app(CoretiRateioMaintenanceService::class)->correctRateioData($dryRun);
    } catch (\Throwable $e) {
        $this->components->error($e->getMessage());
        return 1;
    }

    $this->components->info($dryRun ? 'Correção em dry-run concluída.' : 'Correção aplicada.');
    $this->line('Registros corrigidos: ' . ($result['updated'] ?? 0));
    $this->line('Registros pendentes: ' . ($result['pending'] ?? 0));
    $this->line('Cristalina II manual: ' . ($result['manual'] ?? 0));
    $this->line('Relatório: ' . ($result['report_path'] ?? '-'));

    return 0;
})->purpose('Corrige a base de rateio com dry-run por padrão.');

Artisan::command('office:diagnose-collaborators {--limit=20}', function () {
    $limit = max((int) $this->option('limit'), 1);
    $matcher = app(OfficeCollaboratorMatcherService::class);
    $diagnostic = $matcher->diagnoseView($limit, 30);

    $metadata = $diagnostic['metadata'];
    $counts = $diagnostic['counts'];
    $fallbackMetadata = $diagnostic['fallback_metadata'] ?? [];
    $fallbackCounts = $diagnostic['fallback_counts'] ?? [];

    $this->info('Diagnóstico do snapshot local de colaboradores');
    $this->line('Fonte: ' . (($metadata['source'] ?? null) ?: 'não encontrada'));
    if (! empty($metadata['message'])) {
        $this->warn((string) $metadata['message']);
    }
    if (! empty($metadata['operation_source']) && ($metadata['operation_source'] ?? null) !== ($metadata['source'] ?? null)) {
        $this->line('Fonte operacional de fallback: ' . $metadata['operation_source']);
    }
    $this->line('Campo de nome escolhido: ' . (($metadata['name_field'] ?? null) ?: '-'));
    $this->line('Campo CentroCusto: ' . (($metadata['centro_custo_field'] ?? null) ?: '-'));
    $this->line('Campo NomeSetor: ' . (($metadata['nome_setor_field'] ?? null) ?: '-'));
    $this->line('Campo IdPessoa: ' . (($metadata['id_pessoa_field'] ?? null) ?: '-'));
    $this->line('Campo IdUnicoop: ' . (($metadata['id_unicoop_field'] ?? null) ?: '-'));
    $this->line('Campo IdArea: ' . (($metadata['id_area_field'] ?? null) ?: '-'));
    $this->line('Campo NomeDepartamento: ' . (($metadata['nome_departamento_field'] ?? null) ?: '-'));

    $this->newLine();
    $this->table(
        ['Campo candidato', 'Preenchidos'],
        collect($metadata['name_candidates'] ?? [])
            ->map(fn (array $item): array => [$item['name'], $item['filled']])
            ->all()
    );

    $this->newLine();
    $this->table(
        ['Métrica', 'Valor'],
        [
            ['Total de registros em hr_collaborators', $counts['total'] ?? 0],
            ['Total com campo de nome preenchido', $counts['name_filled'] ?? 0],
            ['Total com IdPessoa preenchido', $counts['id_pessoa_filled'] ?? 0],
            ['Total com CentroCusto preenchido', $counts['centro_custo_filled'] ?? 0],
            ['Total com NomeSetor preenchido', $counts['nome_setor_filled'] ?? 0],
            ['Total Ativo = 1', $counts['active_total'] ?? 0],
            ['Total Desligado = 0', $counts['not_disabled_total'] ?? 0],
            ['Total de nomes únicos', $counts['unique_names'] ?? 0],
        ]
    );

    $this->newLine();
    $this->line('Colunas reais da fonte local:');
    foreach (($metadata['columns'] ?? []) as $column) {
        $this->line('- ' . $column);
    }

    $this->newLine();
    $this->line('Amostra de registros:');
    foreach (($diagnostic['samples'] ?? []) as $index => $sample) {
        $this->line('[' . ($index + 1) . '] ' . json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $this->newLine();
    $this->line('Exemplos distintos de CentroCusto:');
    foreach (($diagnostic['cost_center_samples'] ?? []) as $value) {
        $this->line('- ' . $value);
    }

    $this->newLine();
    $this->line('Exemplos de NomeSetor:');
    foreach (($diagnostic['nome_setor_samples'] ?? []) as $value) {
        $this->line('- ' . $value);
    }

    if (($fallbackMetadata['source'] ?? null) !== null) {
        $this->newLine();
        $this->info('Diagnóstico da fonte fallback');
        $this->line('Fonte fallback: ' . (($fallbackMetadata['source'] ?? null) ?: 'não encontrada'));
        $this->line('Fonte operacional: ' . (($fallbackMetadata['source_view'] ?? null) ?: '-'));
        $this->line('Campo nome: ' . (($fallbackMetadata['name_field'] ?? null) ?: '-'));
        $this->line('Campo matrícula: ' . (($fallbackMetadata['matricula_field'] ?? null) ?: '-'));
        $this->line('Campo centro custo: ' . (($fallbackMetadata['centro_custo_field'] ?? null) ?: '-'));
        $this->line('Campo unidade: ' . (($fallbackMetadata['nome_unicoop_field'] ?? null) ?: '-'));
        $this->table(
            ['Métrica fallback', 'Valor'],
            [
                ['Total de registros', $fallbackCounts['total'] ?? 0],
                ['Total com nome preenchido', $fallbackCounts['name_filled'] ?? 0],
                ['Total com matrícula preenchida', $fallbackCounts['id_pessoa_filled'] ?? 0],
                ['Total ativos', $fallbackCounts['active_total'] ?? 0],
                ['Total nomes únicos', $fallbackCounts['unique_names'] ?? 0],
            ]
        );
    }

    return 0;
})->purpose('Diagnostica a estrutura e amostras do snapshot local de colaboradores.');

Artisan::command('office:sync-microsoft {--deactivate-missing} {--force-deactivate-missing} {--dry-run} {--limit=0}', function (CoreTiSyncNotificationService $notificationService) {
    $enabled = filter_var(env('MS_OFFICE_SYNC_ENABLED', false), FILTER_VALIDATE_BOOL);
    if (! $enabled) {
        $this->warn('Sincronização Microsoft Office desativada (MS_OFFICE_SYNC_ENABLED=false).');
        return 0;
    }

    $dryRun = (bool) $this->option('dry-run');
    $limit = max((int) $this->option('limit'), 0);

    $tenantId = trim((string) env('MS_TENANT_ID', ''));
    $clientId = trim((string) env('MS_CLIENT_ID', ''));
    $clientSecret = trim((string) env('MS_CLIENT_SECRET', ''));

    if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
        $this->error('Credenciais Microsoft ausentes no .env (MS_TENANT_ID/MS_CLIENT_ID/MS_CLIENT_SECRET).');
        return 1;
    }

    $parseSkuList = static function (string $envKey): array {
        $raw = trim((string) env($envKey, ''));
        if ($raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();
    };

    $skuOfficeApps = $parseSkuList('MS_SKU_OFFICE_APPS');
    $skuOfficeBusiness = $parseSkuList('MS_SKU_OFFICE_BUSINESS');
    $skuPowerBiPro = $parseSkuList('MS_SKU_POWERBI_PRO');
    $skuPowerBiPremium = $parseSkuList('MS_SKU_POWERBI_PREMIUM');
    $skuVisioPlan = $parseSkuList('MS_SKU_VISIO_PLAN');

    $tokenResponse = Http::asForm()
        ->timeout(30)
        ->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);

    if (! $tokenResponse->ok()) {
        $this->error('Falha ao obter token Microsoft Graph.');
        Log::error('office:sync-microsoft token error', ['body' => $tokenResponse->body()]);
        return 1;
    }

    $accessToken = (string) data_get($tokenResponse->json(), 'access_token', '');
    if ($accessToken === '') {
        $this->error('Token Microsoft Graph inválido.');
        return 1;
    }

    $skuIdToPart = [];
    $skuResponse = Http::withToken($accessToken)
        ->timeout(30)
        ->get('https://graph.microsoft.com/v1.0/subscribedSkus?$select=skuId,skuPartNumber');

    if ($skuResponse->ok()) {
        foreach (data_get($skuResponse->json(), 'value', []) as $sku) {
            $skuId = mb_strtolower(trim((string) ($sku['skuId'] ?? '')));
            $skuPart = mb_strtolower(trim((string) ($sku['skuPartNumber'] ?? '')));
            if ($skuId !== '') {
                $skuIdToPart[$skuId] = $skuPart;
            }
        }
    }

    $activityAvailable = true;
    $url = 'https://graph.microsoft.com/v1.0/users?$top=999&$select=id,displayName,mail,userPrincipalName,accountEnabled,employeeId,department,assignedLicenses,signInActivity';
    $users = collect();

    while ($url) {
        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->get($url);

        if (! $response->ok() && $activityAvailable && str_contains($url, 'signInActivity')) {
            // signInActivity requires additional Graph audit permissions. A
            // missing permission must not interrupt the license sync.
            $activityAvailable = false;
            $url = str_replace(',signInActivity', '', $url);
            $response = Http::withToken($accessToken)
                ->timeout(60)
                ->get($url);
            Log::warning('office:sync-microsoft signInActivity unavailable; continuing without activity data', [
                'status' => $response->status(),
            ]);
        }

        if (! $response->ok()) {
            $this->error('Falha ao consultar usuários no Microsoft Graph.');
            Log::error('office:sync-microsoft users error', ['body' => $response->body(), 'url' => $url]);
            return 1;
        }

        $payload = $response->json();
        $users = $users->concat(collect(data_get($payload, 'value', [])));
        $url = data_get($payload, '@odata.nextLink');
    }

    $existing = DB::table('office_licenses')->get([
        'id',
        'email',
        'nome',
        'matricula',
        'departamento_unidade',
        'unicoop_office',
        'area_office',
        'unidade_id',
        'unidade_nome',
        'area_nome',
        'departamento_id',
        'organization_unit_id',
        'organization_department_id',
        'organization_cost_center_id',
        'organization_assignment_source',
        'organization_assignment_locked_at',
        'organization_assignment_locked_by',
        'departamento_nome',
        'setor_nome',
        'cargo_nome',
        'empresa_nome',
        'empresa_id_cocari',
        'centro_custo',
        'centro_custo_nome',
        'colaborador_nome',
        'colaborador_source_view',
        'colaborador_match_score',
        'colaborador_match_type',
        'colaborador_match_status',
        'colaborador_match_message',
        'colaborador_matched_at',
        'microsoft_user_id', 'microsoft_upn', 'account_enabled', 'license_skus', 'account_classification',
        'organization_unit_source', 'organization_department_source', 'organization_cost_center_source',
        'organization_position_source', 'organization_position_id',
        'microsoft_last_sign_in_at', 'microsoft_last_non_interactive_sign_in_at',
        'microsoft_activity_source', 'microsoft_activity_checked_at',
    ])->keyBy(function ($row) {
        return mb_strtolower(trim((string) $row->email));
    });
    $officeUserSyncService = app(OfficeUserSyncService::class);
    $diagnostic = app(OfficeCollaboratorMatcherService::class)->diagnoseView(5, 10);

    $upsertRows = [];
    $pendingAssignmentRows = [];
    $seenEmails = [];
    $created = 0;
    $updated = 0;
    $relocated = 0;
    $isInvalidUnit = static function (?string $value): bool {
        $value = trim((string) $value);
        if ($value === '' || $value === 'N/D') {
            return true;
        }

        return in_array($value, ['0', '00'], true);
    };
    $summary = [
        'users_total' => 0,
        'users_with_license' => 0,
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
        'collaborators_total' => (int) ($diagnostic['counts']['total'] ?? 0),
        'collaborators_active' => (int) ($diagnostic['counts']['active_total'] ?? 0),
        'collaborators_not_disabled' => (int) ($diagnostic['counts']['not_disabled_total'] ?? 0),
        'collaborators_unique_names' => (int) ($diagnostic['counts']['unique_names'] ?? 0),
        'fallback_collaborators_total' => (int) ($diagnostic['fallback_counts']['total'] ?? 0),
        'fallback_collaborators_active' => (int) ($diagnostic['fallback_counts']['active_total'] ?? 0),
        'unit_found' => 0,
        'unit_missing' => 0,
        'department_found' => 0,
        'department_missing' => 0,
        'licenses_processed' => 0,
        'errors' => 0,
    ];

    $graphUsersByEmail = [];
    $changeCandidates = [];
    $classifyOfficeAccount = static function (string $email, string $name): ?string {
        $value = mb_strtolower(trim($email . ' ' . $name));
        foreach (['recepcao', 'financeiro', 'balanca', 'diretoria', 'sala.reuniao', 'power bi', 'powerbi'] as $token) {
            if (str_contains($value, $token)) {
                return 'generic_account';
            }
        }
        return null;
    };
    $processedLicensed = 0;
    $emitUserDiagnostics = function (array $officeUser, array $sync): void {
        $match = $sync['match'] ?? [];
        $attributes = $sync['attributes'] ?? [];

        $this->newLine();
        $this->line('Office: ' . trim((string) ($officeUser['nome'] ?? '')) . ' <' . trim((string) ($officeUser['email'] ?? '')) . '>');
        $this->line('UPN: ' . trim((string) ($officeUser['upn'] ?? '-')));
        $this->line('Nome normalizado: ' . trim((string) ($match['normalized_office_name'] ?? '-')));
        $this->line('Campo usado na view: ' . trim((string) ($match['source_name_field'] ?? '-')));
        $this->line('Candidatos encontrados por LIKE: ' . (int) ($match['like_candidates_count'] ?? 0));
        $this->line('View usada: ' . trim((string) ($attributes['colaborador_source_view'] ?? '-')));
        $best = $match['best_candidate'] ?? null;
        $second = $match['second_candidate'] ?? null;
        $this->line('Melhor candidato: ' . ($best ? (($best['nome'] ?? '-') . ' - ' . number_format((float) ($best['score'] ?? 0), 2, ',', '.') . '%') : '-'));
        $this->line('Segundo candidato: ' . ($second ? (($second['nome'] ?? '-') . ' - ' . number_format((float) ($second['score'] ?? 0), 2, ',', '.') . '%') : '-'));
        $this->line('Status: ' . trim((string) ($attributes['colaborador_match_status'] ?? '-')));
        $this->line('Motivo: ' . trim((string) ($attributes['colaborador_match_message'] ?? '-')));
        $this->line('CentroCusto: ' . trim((string) ($attributes['centro_custo'] ?? '-')));
        $this->line('Unicoop: ' . trim((string) ($attributes['unicoop_office'] ?? '-')));
        $this->line('Área: ' . trim((string) ($attributes['area_office'] ?? '-')));
        $this->line('NomeUnicoop: ' . trim((string) ($attributes['unidade_nome'] ?? '-')));
        $this->line('NomeArea: ' . trim((string) ($attributes['area_nome'] ?? '-')));
        $this->line('NomeDepartamento: ' . trim((string) ($attributes['departamento_nome'] ?? '-')));
        $this->line('NomeSetor: ' . trim((string) ($attributes['centro_custo_nome'] ?? '-')));
        $this->line('Setor: ' . trim((string) ($attributes['setor_nome'] ?? '-')));
        $this->line('Cargo: ' . trim((string) ($attributes['cargo_nome'] ?? '-')));
        $this->line('Empresa: ' . trim((string) ($attributes['empresa_nome'] ?? '-')));
        $this->line('IdPessoa: ' . trim((string) ($attributes['matricula'] ?? '-')));
        $this->line('Unidade encontrada: ' . trim((string) ($attributes['unidade_nome'] ?? '-')));
        $this->line('Departamento encontrado: ' . trim((string) ($attributes['departamento_nome'] ?? '-')));
    };

    foreach ($users as $user) {
        $mail = mb_strtolower(trim((string) ($user['mail'] ?? '')));
        $upn = mb_strtolower(trim((string) ($user['userPrincipalName'] ?? '')));
        $emailSource = $mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL) ? 'mail' : 'userPrincipalName';
        $email = $mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL) ? $mail : $upn;
        if ($email === '' || ! str_contains($email, '@')) {
            continue;
        }

        if (str_ends_with($email, '@cocaricoop.onmicrosoft.com')) {
            $email = (string) Str::of($email)->before('@') . '@cocari.com.br';
        }

        // A conta desabilitada ainda pode possuir uma licença atribuída. Ela
        // também precisa aparecer no painel para controle de custo e ajuste.
        $assignedLicenses = collect($user['assignedLicenses'] ?? [])->values();
        if ($assignedLicenses->isEmpty()) {
            continue;
        }

        $summary['users_total']++;
        $seenEmails[$email] = true;
        $graphUsersByEmail[$email] = [
            'id' => (string) ($user['id'] ?? ''),
            'employeeId' => trim((string) ($user['employeeId'] ?? '')),
            'department' => trim((string) ($user['department'] ?? '')),
            'email_source' => $emailSource,
        ];

        $skuIds = $assignedLicenses
            ->pluck('skuId')
            ->map(fn ($sku) => mb_strtolower(trim((string) $sku)))
            ->filter()
            ->values()
            ->all();

        $skuParts = collect($skuIds)
            ->map(fn (string $skuId) => $skuIdToPart[$skuId] ?? '')
            ->filter(fn (string $part) => $part !== '')
            ->values()
            ->all();

        $hasSku = static function (array $ids, array $parts, array $envConfigured, array $partNeedles): bool {
            if ($envConfigured !== [] && ! empty(array_intersect($ids, $envConfigured))) {
                return true;
            }
            foreach ($parts as $part) {
                foreach ($partNeedles as $needle) {
                    if ($part === $needle || str_starts_with($part, $needle . '_') || str_ends_with($part, '_' . $needle)) {
                        return true;
                    }
                }
            }
            return false;
        };

        // Contabiliza somente as 4 licenças definidas:
        // Microsoft 365 Apps for enterprise, Microsoft 365 Apps for business, Power BI Pro, Visio Plan 2.
        $hasOfficeApps = $hasSku($skuIds, $skuParts, $skuOfficeApps, ['m365_apps_for_enterprise', 'm365_apps_for_business', 'o365_proplus', 'officesubscription']);
        $hasOfficeBusiness = $hasSku($skuIds, $skuParts, $skuOfficeBusiness, ['m365_business_basic', 'm365_business_standard', 'm365_business_premium', 'o365_business']);
        $hasPowerBiPro = $hasSku($skuIds, $skuParts, $skuPowerBiPro, ['power_bi_pro']);
        $hasPowerBiPremium = $hasSku($skuIds, $skuParts, $skuPowerBiPremium, ['power_bi_premium']);
        $hasVisioPlan = $hasSku($skuIds, $skuParts, $skuVisioPlan, ['visio_plan', 'visio', 'visioclient', 'visio_client']);
        // A regra de inclusão é ter qualquer assignedLicense. As categorias
        // abaixo servem apenas para indicadores e rateio; SKU desconhecida
        // não pode excluir o usuário da sincronização.
        $skuDetails = collect($skuIds)->map(function (string $skuId) use ($skuIdToPart, $skuOfficeApps, $skuOfficeBusiness, $skuPowerBiPro, $skuPowerBiPremium, $skuVisioPlan, $hasSku): array {
            $part = $skuIdToPart[$skuId] ?? '';
            $category = $hasSku([$skuId], [$part], $skuOfficeApps, ['m365_apps_for_enterprise', 'm365_apps_for_business', 'o365_proplus', 'officesubscription']) ? 'office_apps'
                : ($hasSku([$skuId], [$part], $skuOfficeBusiness, ['m365_business_basic', 'm365_business_standard', 'm365_business_premium', 'o365_business']) ? 'office_business'
                : ($hasSku([$skuId], [$part], $skuPowerBiPro, ['power_bi_pro']) ? 'powerbi_pro'
                : ($hasSku([$skuId], [$part], $skuPowerBiPremium, ['power_bi_premium']) ? 'powerbi_premium'
                : ($hasSku([$skuId], [$part], $skuVisioPlan, ['visio_plan', 'visio', 'visioclient', 'visio_client']) ? 'visio_plan' : null))));
            return ['sku_id' => $skuId, 'sku_part_number' => $part, 'mapped_category' => $category, 'is_monitored' => $category !== null];
        })->all();

        foreach ($skuDetails as $skuDetail) {
            $skuDisplayName = OfficeLicenseReportPolicy::displayName(
                $skuDetail['sku_part_number'],
                $skuDetail['sku_part_number'] ?: $skuDetail['sku_id']
            );
            $pendingAssignmentRows[] = [
                'email' => $email,
                'microsoft_user_id' => (string) ($user['id'] ?? ''),
                'microsoft_upn' => $upn,
                'user_name' => (string) ($user['displayName'] ?? ''),
                'user_email' => $email,
                'sku_id' => $skuDetail['sku_id'],
                'sku_part_number' => $skuDetail['sku_part_number'] !== '' ? $skuDetail['sku_part_number'] : null,
                'sku_name' => $skuDisplayName,
                'license_category' => $skuDetail['mapped_category'],
                'included_in_report' => ! OfficeLicenseReportPolicy::isExcluded(
                    $skuDetail['sku_part_number'],
                    $skuDisplayName
                ),
                'exclusion_reason' => OfficeLicenseReportPolicy::exclusionReason(
                    $skuDetail['sku_part_number'],
                    $skuDisplayName
                ),
                'account_enabled' => (bool) ($user['accountEnabled'] ?? false),
                'license_source' => 'microsoft_graph',
                'metadata' => json_encode(['raw_sku_id' => $skuDetail['sku_id']], JSON_UNESCAPED_UNICODE),
                'synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $processedLicensed++;
        if ($limit > 0 && $processedLicensed > $limit) {
            break;
        }

        $summary['users_with_license']++;
        $summary['licenses_processed'] += $skuIds !== [] ? count($skuIds) : 0;

        $existingRow = $existing->get($email);
        $sync = $officeUserSyncService->buildOfficeLicenseAttributes([
            'nome' => (string) ($user['displayName'] ?? ''),
            'email' => $email,
            'upn' => (string) ($user['userPrincipalName'] ?? ''),
            'external_id' => (string) ($user['id'] ?? ''),
            'department' => (string) ($user['department'] ?? ''),
            'account_enabled' => (bool) ($user['accountEnabled'] ?? false),
        ], $existingRow);

        $sync['attributes']['microsoft_user_id'] = (string) ($user['id'] ?? '');
        $sync['attributes']['microsoft_upn'] = $upn;
        $sync['attributes']['account_enabled'] = (bool) ($user['accountEnabled'] ?? false);
        $sync['attributes']['license_skus'] = $skuIds;
        $sync['attributes']['license_source'] = 'microsoft_graph';
        $sync['attributes']['account_classification'] = $classifyOfficeAccount($email, (string) ($user['displayName'] ?? ''));

        $activityRow = [];
        if ($activityAvailable && array_key_exists('signInActivity', $user)) {
            $signInActivity = is_array($user['signInActivity']) ? $user['signInActivity'] : [];
            $activityRow = [
                'microsoft_last_sign_in_at' => $signInActivity['lastSuccessfulSignInDateTime'] ?? null,
                'microsoft_last_non_interactive_sign_in_at' => $signInActivity['lastNonInteractiveSignInDateTime'] ?? null,
                'microsoft_activity_source' => 'graph_sign_in_activity',
                'microsoft_activity_checked_at' => now(),
            ];
        }

        $sync['attributes']['organization_position_id'] = $sync['attributes']['organization_position_id'] ?? null;
        $sync['attributes']['organization_position_source'] = $existingRow?->organization_position_source ?? 'sync';

        $fieldMap = [
            'organization_unit_id' => 'organization_unit_source',
            'organization_department_id' => 'organization_department_source',
            'organization_cost_center_id' => 'organization_cost_center_source',
            'organization_position_id' => 'organization_position_source',
        ];
        foreach ($fieldMap as $field => $sourceField) {
            $currentSource = trim((string) ($existingRow?->{$sourceField} ?? ($existingRow?->organization_assignment_source ?? 'sync')));
            $proposed = $sync['attributes'][$field] ?? null;
            $current = $existingRow?->{$field} ?? null;
            if ($existingRow && $currentSource === 'manual') {
                if ((string) $current !== (string) $proposed && $proposed !== null) {
                    $changeCandidates[] = [
                        'email' => $email,
                        'field_name' => $field,
                        'current_value' => $current,
                        'proposed_value' => $proposed,
                        'hr_collaborator_id' => data_get($sync, 'match.collaborator.local_id'),
                        'current_source' => 'manual',
                    ];
                }
                $sync['attributes'][$field] = $current;
                foreach (match ($field) {
                    'organization_unit_id' => ['unicoop_office', 'unidade_id', 'unidade_nome', 'departamento_unidade'],
                    'organization_department_id' => ['area_office', 'departamento_id', 'area_nome', 'departamento_nome', 'setor_nome'],
                    'organization_cost_center_id' => ['centro_custo', 'centro_custo_nome'],
                    'organization_position_id' => ['cargo_nome'],
                    default => [],
                } as $relatedField) {
                    if (property_exists($existingRow, $relatedField)) {
                        $sync['attributes'][$relatedField] = $existingRow->{$relatedField};
                    }
                }
            }
        }

        foreach ($sync['counters'] as $key => $value) {
            if (array_key_exists($key, $summary)) {
                $summary[$key] += (int) $value;
            }
        }

        if ($dryRun || $this->output->isVerbose()) {
            $emitUserDiagnostics([
                'nome' => (string) ($user['displayName'] ?? ''),
                'email' => $email,
                'upn' => (string) ($user['userPrincipalName'] ?? ''),
            ], $sync);
        }

        $finalDepartment = trim((string) ($sync['attributes']['departamento_unidade'] ?? ''));
        if ($isInvalidUnit($finalDepartment)) {
            $finalDepartment = 'N/D';
            $sync['attributes']['departamento_unidade'] = $finalDepartment;
        }

        $finalUnicoop = trim((string) ($sync['attributes']['unicoop_office'] ?? '')) ?: null;
        $finalArea = trim((string) ($sync['attributes']['area_office'] ?? '')) ?: null;
        $assignmentSource = ($existingRow?->organization_assignment_source ?? null) === 'manual' ? 'manual' : 'sync';

        if ($existingRow && (
            trim((string) ($existingRow->departamento_unidade ?? '')) !== trim((string) ($finalDepartment ?? ''))
            || trim((string) ($existingRow->unicoop_office ?? '')) !== trim((string) ($finalUnicoop ?? ''))
            || trim((string) ($existingRow->area_office ?? '')) !== trim((string) ($finalArea ?? ''))
        )) {
            $relocated++;
        }

        $row = array_merge([
            'email' => $email,
            'nome' => $sync['attributes']['nome'],
            'matricula' => $sync['attributes']['matricula'],
            'departamento_unidade' => $finalDepartment,
            'unicoop_office' => $finalUnicoop,
            'area_office' => $finalArea,
            'unidade_id' => $sync['attributes']['unidade_id'],
            'organization_unit_id' => $sync['attributes']['organization_unit_id'],
            'unidade_nome' => $sync['attributes']['unidade_nome'],
            'area_nome' => $sync['attributes']['area_nome'],
            'departamento_id' => $sync['attributes']['departamento_id'],
            'organization_department_id' => $sync['attributes']['organization_department_id'],
            'organization_cost_center_id' => $sync['attributes']['organization_cost_center_id'],
            'organization_assignment_source' => $sync['attributes']['organization_assignment_source'],
            'organization_assignment_locked_at' => $sync['attributes']['organization_assignment_locked_at'],
            'organization_assignment_locked_by' => $sync['attributes']['organization_assignment_locked_by'],
            'departamento_nome' => $sync['attributes']['departamento_nome'],
            'setor_nome' => $sync['attributes']['setor_nome'],
            'cargo_nome' => $sync['attributes']['cargo_nome'],
            'empresa_nome' => $sync['attributes']['empresa_nome'],
            'empresa_id_cocari' => $sync['attributes']['empresa_id_cocari'],
            'centro_custo' => $sync['attributes']['centro_custo'],
            'centro_custo_nome' => $sync['attributes']['centro_custo_nome'],
            'colaborador_nome' => $sync['attributes']['colaborador_nome'],
            'colaborador_source_view' => $sync['attributes']['colaborador_source_view'],
            'colaborador_match_score' => $sync['attributes']['colaborador_match_score'],
            'colaborador_match_type' => $sync['attributes']['colaborador_match_type'],
            'colaborador_matched_at' => $sync['attributes']['colaborador_matched_at'],
            'colaborador_match_status' => $sync['attributes']['colaborador_match_status'],
            'colaborador_match_message' => $sync['attributes']['colaborador_match_message'],
            'microsoft_user_id' => $sync['attributes']['microsoft_user_id'],
            'microsoft_upn' => $sync['attributes']['microsoft_upn'],
            'account_enabled' => $sync['attributes']['account_enabled'],
            'license_skus' => json_encode($skuDetails, JSON_UNESCAPED_UNICODE),
            'license_source' => $sync['attributes']['license_source'],
            'account_classification' => $sync['attributes']['account_classification'],
            'organization_position_id' => $sync['attributes']['organization_position_id'],
            'organization_unit_source' => $existingRow?->organization_unit_source ?: $assignmentSource,
            'organization_department_source' => $existingRow?->organization_department_source ?: $assignmentSource,
            'organization_cost_center_source' => $existingRow?->organization_cost_center_source ?: $assignmentSource,
            'organization_position_source' => $existingRow?->organization_position_source ?: $assignmentSource,
            'office_apps' => $hasOfficeApps,
            'office_business' => $hasOfficeBusiness,
            'powerbi_pro' => $hasPowerBiPro,
            'powerbi_premium' => $hasPowerBiPremium,
            'visio_plan' => $hasVisioPlan,
            // Usuário ativo com qualquer licença atribuída permanece no painel,
            // inclusive quando a SKU ainda não está mapeada em uma categoria.
            'ativo' => true,
            'created_at' => $existingRow ? null : now(),
            'updated_at' => now(),
        ], $activityRow);

        if (! $existingRow) {
            $created++;
        } else {
            $updated++;
        }

        $upsertRows[] = $row;
    }

    if (! $dryRun && $upsertRows !== []) {
        DB::table('office_licenses')->upsert(
            $upsertRows,
            ['email'],
            ['nome', 'matricula', 'departamento_unidade', 'unicoop_office', 'area_office', 'unidade_id', 'organization_unit_id', 'unidade_nome', 'area_nome', 'departamento_id', 'organization_department_id', 'organization_cost_center_id', 'organization_position_id', 'organization_assignment_source', 'organization_assignment_locked_at', 'organization_assignment_locked_by', 'organization_unit_source', 'organization_department_source', 'organization_cost_center_source', 'organization_position_source', 'departamento_nome', 'setor_nome', 'cargo_nome', 'empresa_nome', 'empresa_id_cocari', 'centro_custo', 'centro_custo_nome', 'colaborador_nome', 'colaborador_source_view', 'colaborador_match_score', 'colaborador_match_type', 'colaborador_matched_at', 'colaborador_match_status', 'colaborador_match_message', 'microsoft_user_id', 'microsoft_upn', 'account_enabled', 'license_skus', 'license_source', 'account_classification', 'office_apps', 'office_business', 'powerbi_pro', 'powerbi_premium', 'visio_plan', 'ativo', 'updated_at', 'microsoft_last_sign_in_at', 'microsoft_last_non_interactive_sign_in_at', 'microsoft_activity_source', 'microsoft_activity_checked_at']
        );
    }

    if (! $dryRun && $limit === 0 && Schema::hasTable('office_license_assignments')) {
        $licenseIdsByEmail = DB::table('office_licenses')
            ->whereIn('email', array_keys($seenEmails))
            ->pluck('id', 'email');

        $assignmentRows = collect($pendingAssignmentRows)
            ->map(function (array $row) use ($licenseIdsByEmail): ?array {
                $officeLicenseId = $licenseIdsByEmail->get($row['email']);
                if (! $officeLicenseId) {
                    return null;
                }

                unset($row['email']);
                $row['office_license_id'] = $officeLicenseId;
                return $row;
            })
            ->filter()
            ->values();

        // A full Graph sync is the authoritative snapshot: assignments
        // removed in Microsoft must disappear locally as well.
        DB::table('office_license_assignments')->delete();
        foreach ($assignmentRows->chunk(500) as $chunk) {
            DB::table('office_license_assignments')->insert($chunk->all());
        }
    }

    // Opcional: envia para Microsoft (admin.cloud) matrícula e unidade/departamento corrigidos manualmente no CoreTI.
    $pushEnabled = filter_var(env('MS_OFFICE_PUSH_ENABLED', false), FILTER_VALIDATE_BOOL);
    if (! $dryRun && $pushEnabled) {
        $pushed = 0;
        $skipped = 0;
        $errors = 0;

        $rowsForPush = DB::table('office_licenses')
            ->select(['email', 'matricula', 'departamento_unidade'])
            ->whereNotNull('email')
            ->get();

        foreach ($rowsForPush as $rowPush) {
            $email = mb_strtolower(trim((string) ($rowPush->email ?? '')));
            if ($email === '' || ! isset($graphUsersByEmail[$email])) {
                $skipped++;
                continue;
            }

            $graphUser = $graphUsersByEmail[$email];
            $graphId = trim((string) ($graphUser['id'] ?? ''));
            if ($graphId === '') {
                $skipped++;
                continue;
            }

            $localMatricula = trim((string) ($rowPush->matricula ?? ''));
            $localDepartment = trim((string) ($rowPush->departamento_unidade ?? ''));
            if ($isInvalidUnit($localDepartment)) {
                $localDepartment = '';
            }

            $patch = [];
            if ($localMatricula !== '' && $localMatricula !== trim((string) ($graphUser['employeeId'] ?? ''))) {
                $patch['employeeId'] = $localMatricula;
            }
            if ($localDepartment !== '' && $localDepartment !== trim((string) ($graphUser['department'] ?? ''))) {
                $patch['department'] = $localDepartment;
            }

            if ($patch === []) {
                $skipped++;
                continue;
            }

            $pushResponse = Http::withToken($accessToken)
                ->timeout(30)
                ->patch("https://graph.microsoft.com/v1.0/users/{$graphId}", $patch);

            if ($pushResponse->successful()) {
                $pushed++;
            } else {
                $errors++;
                Log::warning('office:sync-microsoft push failed', [
                    'email' => $email,
                    'status' => $pushResponse->status(),
                    'body' => $pushResponse->body(),
                ]);
            }
        }

        $this->line("Push Microsoft: enviados {$pushed} | ignorados {$skipped} | erros {$errors}");
    }

    $deactivated = 0;
    $deleted = 0;
    $allowDeactivation = ! $dryRun && (bool) $this->option('deactivate-missing') && $limit === 0;
    if ($allowDeactivation) {
        $emailsSet = array_keys($seenEmails);
        $existingActiveCount = (int) DB::table('office_licenses')->where('ativo', true)->count();
        $reductionIsSuspicious = $existingActiveCount > 0 && count($emailsSet) < (int) floor($existingActiveCount * 0.8);
        if ($reductionIsSuspicious && ! $this->option('force-deactivate-missing')) {
            $this->warn("Desativação protegida: o Graph retornou " . count($emailsSet) . " usuários contra {$existingActiveCount} ativos. Use --force-deactivate-missing para confirmar.");
            $allowDeactivation = false;
        }
    }
    if ($allowDeactivation) {
        $emailsSet = array_keys($seenEmails);
        $query = DB::table('office_licenses');
        if (! empty($emailsSet)) {
            $query->whereNotIn('email', $emailsSet);
        }
        $deleteMissing = filter_var(env('MS_OFFICE_DELETE_MISSING', true), FILTER_VALIDATE_BOOL);
        if ($deleteMissing) {
            $deleted = $query->delete();
        } else {
            $deactivated = $query->update([
                'ativo' => false,
                'office_apps' => false,
                'office_business' => false,
                'powerbi_pro' => false,
                'powerbi_premium' => false,
                'visio_plan' => false,
                'updated_at' => now(),
            ]);
        }
    }

    foreach ($changeCandidates as $candidate) {
        if ($dryRun) {
            continue;
        }
        $licenseId = DB::table('office_licenses')->where('email', $candidate['email'])->value('id');
        if (! $licenseId || ! Schema::hasTable('office_sync_change_requests')) {
            continue;
        }
        $hash = hash('sha256', $candidate['field_name'] . '|' . (string) $candidate['current_value'] . '|' . (string) $candidate['proposed_value']);
        $duplicate = DB::table('office_sync_change_requests')
            ->where('office_license_id', $licenseId)
            ->where('field_name', $candidate['field_name'])
            ->where('proposal_hash', $hash)
            ->whereIn('status', ['pending', 'rejected'])
            ->exists();
        if (! $duplicate) {
            // The matcher may come from a read-only source view whose ID is
            // not the local snapshot primary key. The FK must only receive a
            // real hr_collaborators.id; the change request itself remains
            // valid without that optional link.
            $hrCollaboratorId = $candidate['hr_collaborator_id'] ?? null;
            if ($hrCollaboratorId !== null && ! DB::table('hr_collaborators')->where('id', $hrCollaboratorId)->exists()) {
                $hrCollaboratorId = null;
            }

            DB::table('office_sync_change_requests')->insert([
                'office_license_id' => $licenseId,
                'hr_collaborator_id' => $hrCollaboratorId,
                'field_name' => $candidate['field_name'],
                'current_value' => (string) $candidate['current_value'],
                'proposed_value' => (string) $candidate['proposed_value'],
                'current_source' => 'manual',
                'status' => 'pending',
                'detected_at' => now(),
                'proposal_hash' => $hash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    Log::info('office:sync-microsoft summary', [
        'total_usuarios_office' => $summary['users_total'],
        'total_usuarios_com_licenca' => $summary['users_with_license'],
        'total_colaboradores_carregados_view' => $summary['collaborators_total'],
        'total_colaboradores_ativos' => $summary['collaborators_active'],
        'total_colaboradores_nao_desligados' => $summary['collaborators_not_disabled'],
        'total_nomes_unicos_view' => $summary['collaborators_unique_names'],
        'total_colaboradores_fallback' => $summary['fallback_collaborators_total'],
        'total_colaboradores_fallback_ativos' => $summary['fallback_collaborators_active'],
        'total_vinculados_colaborador' => $summary['matched'],
        'vinculados_pela_view_principal' => $summary['matched_primary_view'],
        'vinculados_pela_view_empresa' => $summary['matched_fallback_view'],
        'nao_encontrados_apos_fallback' => $summary['not_found_after_fallback'],
        'ambiguos_na_view_principal' => $summary['ambiguous_primary_view'],
        'ambiguos_na_view_empresa' => $summary['ambiguous_fallback_view'],
        'total_nao_encontrados' => $summary['not_found'],
        'total_ambiguos' => $summary['ambiguous'],
        'resolvidos_por_prioridade_ativo' => $summary['resolved_active_priority'],
        'resolvidos_por_nome_exato' => $summary['resolved_exact_name'],
        'ambiguos_entre_ativos' => $summary['ambiguous_active_tie'],
        'ambiguos_por_score_equivalente' => $summary['ambiguous_equivalent_score'],
        'total_centro_custo_valido' => $summary['centro_custo_valid'],
        'total_centro_custo_invalido' => $summary['centro_custo_invalid'],
        'total_matricula_encontrada' => $summary['matricula_found'],
        'total_sem_matricula' => $summary['matricula_missing'],
        'matricula_from_service_desk' => $summary['matricula_from_service_desk'],
        'total_com_unidade_encontrada' => $summary['unit_found'],
        'total_sem_unidade_encontrada' => $summary['unit_missing'],
        'total_com_departamento_encontrado' => $summary['department_found'],
        'total_sem_departamento_encontrado' => $summary['department_missing'],
        'total_licencas_processadas' => $summary['licenses_processed'],
        'total_erros' => $summary['errors'],
        'criados' => $created,
        'atualizados' => $updated,
        'realocados' => $relocated,
        'desativados_ausentes' => $deactivated,
        'excluidos_ausentes' => $deleted,
    ]);

    $modeLabel = $dryRun ? 'DRY-RUN Office concluído.' : 'Sincronização Office concluída.';
    $this->info("{$modeLabel} Office licenciados: {$summary['users_with_license']} | Colab. principal: {$summary['collaborators_total']} | Ativos principal: {$summary['collaborators_active']} | Colab. fallback: {$summary['fallback_collaborators_total']} | Ativos fallback: {$summary['fallback_collaborators_active']} | Criados: {$created} | Atualizados: {$updated} | Realocados: {$relocated} | Vinculados: {$summary['matched']} | Principal: {$summary['matched_primary_view']} | Empresa: {$summary['matched_fallback_view']} | Não encontrados pós-fallback: {$summary['not_found_after_fallback']} | Ambíguos principal: {$summary['ambiguous_primary_view']} | Ambíguos empresa: {$summary['ambiguous_fallback_view']} | Por ativo: {$summary['resolved_active_priority']} | Por nome exato: {$summary['resolved_exact_name']} | Ambíguos entre ativos: {$summary['ambiguous_active_tie']} | Ambíguos por score: {$summary['ambiguous_equivalent_score']} | CC preenchido: {$summary['centro_custo_valid']} | CC zerado/vazio: {$summary['centro_custo_invalid']} | Unidade encontrada: {$summary['unit_found']} | Sem unidade: {$summary['unit_missing']} | Departamento encontrado: {$summary['department_found']} | Sem departamento: {$summary['department_missing']} | Sem matrícula: {$summary['matricula_missing']} | Desativados ausentes: {$deactivated} | Excluídos ausentes: {$deleted}");
    if (! $dryRun) {
        $officeNotificationSummary = array_merge($summary, [
            'created' => $created,
            'updated' => $updated,
            'relocated' => $relocated,
            'deactivated' => $deactivated,
            'deleted' => $deleted,
        ]);
        $officeStatus = ((int) ($summary['not_found'] ?? 0) > 0
            || (int) ($summary['ambiguous'] ?? 0) > 0
            || (int) ($summary['unit_missing'] ?? 0) > 0
            || (int) ($summary['department_missing'] ?? 0) > 0
            || (int) ($summary['errors'] ?? 0) > 0) ? 'warning' : 'success';
        $notificationService->notify('Office/Microsoft 365', $officeNotificationSummary, $officeStatus, null, [
            'url' => url('/service-desk/office'),
        ]);
    }
    return 0;
})->purpose('Sincroniza licenças Office com Microsoft Graph');

Artisan::command('office:diagnose-skus {--show-unmapped}', function () {
    $rows = DB::table('office_licenses')->where('ativo', true)->get(['license_skus']);
    $counts = [];
    foreach ($rows as $row) {
        $skus = json_decode((string) ($row->license_skus ?? '[]'), true);
        foreach (is_array($skus) ? $skus : [] as $sku) {
            $sku = is_array($sku) ? mb_strtolower(trim((string) ($sku['sku_part_number'] ?? $sku['sku_id'] ?? ''))) : mb_strtolower(trim((string) $sku));
            if ($sku !== '') $counts[$sku] = ($counts[$sku] ?? 0) + 1;
        }
    }
    if ($counts === []) {
        $this->warn('Nenhuma SKU foi armazenada ainda. Execute office:sync-microsoft para coletar o catálogo atual.');
        return 0;
    }
    $configured = collect(['MS_SKU_OFFICE_APPS', 'MS_SKU_OFFICE_BUSINESS', 'MS_SKU_POWERBI_PRO', 'MS_SKU_POWERBI_PREMIUM', 'MS_SKU_VISIO_PLAN'])
        ->flatMap(fn (string $key) => array_filter(array_map('trim', explode(',', (string) env($key, '')))))
        ->map(fn (string $sku) => mb_strtolower($sku))->all();
    $data = collect($counts)->sortDesc()->map(fn (int $total, string $sku): array => [$sku, $total, in_array($sku, $configured, true) ? 'configured_id' : 'part_number_or_unmapped'])->values()->all();
    if (! $this->option('show-unmapped')) {
        $data = array_values(array_filter($data, fn (array $row): bool => $row[2] === 'configured_id'));
    }
    $this->table(['SKU/ID armazenada', 'Usuários', 'Origem'], $data);
    return 0;
})->purpose('Diagnostica SKUs licenciadas do Microsoft 365 armazenadas no Office.');

Artisan::command('office:report-missing-matriculas {--all}', function () {
    $rows = DB::table('office_licenses')
        ->when(! $this->option('all'), fn ($query) => $query->where('ativo', true))
        ->where(function ($query): void {
            $query->whereNull('matricula')
                ->orWhereRaw('TRIM(COALESCE(matricula, "")) = ""');
        })
        ->orderBy('email')
        ->get([
            'id', 'nome', 'email', 'departamento_unidade', 'unicoop_office',
            'area_office', 'office_apps', 'office_business', 'powerbi_pro',
            'powerbi_premium', 'visio_plan', 'ativo',
        ]);

    $directory = storage_path('app/private/reports');
    File::ensureDirectoryExists($directory);
    $path = $directory . '/office_registros_sem_matricula_' . now()->format('Ymd_His') . '.csv';
    $handle = fopen($path, 'wb');
    fputcsv($handle, [
        'id', 'nome', 'email', 'departamento_unidade', 'unicoop', 'area',
        'licencas', 'ativo', 'motivo',
    ]);

    foreach ($rows as $row) {
        $licenses = collect([
            $row->office_apps ? 'Office Apps' : null,
            $row->office_business ? 'Office Business' : null,
            $row->powerbi_pro ? 'Power BI Pro' : null,
            $row->powerbi_premium ? 'Power BI Premium' : null,
            $row->visio_plan ? 'Visio Plan' : null,
        ])->filter()->implode('; ');

        fputcsv($handle, [
            $row->id,
            $row->nome,
            $row->email,
            $row->departamento_unidade,
            $row->unicoop_office,
            $row->area_office,
            $licenses,
            $row->ativo ? 'Ativo' : 'Inativo',
            'Matrícula não preenchida',
        ]);
    }

    fclose($handle);
    $this->table(['Indicador', 'Valor'], [
        ['Registros analisados', $rows->count()],
        ['Escopo', $this->option('all') ? 'Todos' : 'Somente ativos'],
        ['Arquivo', $path],
    ]);

    return 0;
})->purpose('Gera relação dos usuários Office sem matrícula.');

Schedule::command('office:sync-microsoft --deactivate-missing')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Artisan::command('office:audit-organizational-enrichment {--email=} {--office-license-id=} {--limit=100} {--only-errors}', function (OfficeUserSyncService $syncService) {
    $query = OfficeLicense::query()->orderBy('nome');
    $email = trim((string) $this->option('email'));
    $id = trim((string) $this->option('office-license-id'));
    $limit = max((int) $this->option('limit'), 1);
    $onlyErrors = (bool) $this->option('only-errors');

    if ($email !== '') {
        $query->whereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
    }
    if ($id !== '') {
        $query->where('id', (int) $id);
    }

    $rows = $query->limit($limit)->get();
    $summary = [
        'total' => 0,
        'com_colaborador' => 0,
        'sem_colaborador' => 0,
        'unidade_resolvida' => 0,
        'unidade_pendente' => 0,
        'departamento_resolvido' => 0,
        'departamento_pendente' => 0,
        'centro_resolvido' => 0,
        'centro_pendente' => 0,
        'precisam_atualizar' => 0,
    ];
    $table = [];

    foreach ($rows as $row) {
        $sync = $syncService->buildOfficeLicenseAttributes([
            'nome' => (string) $row->nome,
            'email' => (string) $row->email,
            'department' => (string) $row->departamento_unidade,
        ], $row);
        $attrs = $sync['attributes'];
        $diffs = [];
        foreach (['organization_unit_id', 'organization_department_id', 'organization_cost_center_id', 'centro_custo', 'unicoop_office', 'area_office'] as $field) {
            if (trim((string) ($row->{$field} ?? '')) !== trim((string) ($attrs[$field] ?? ''))) {
                $diffs[] = $field;
            }
        }

        $status = (string) ($attrs['colaborador_match_status'] ?? '');
        $hasError = $status !== 'matched'
            || empty($attrs['organization_unit_id'])
            || empty($attrs['organization_cost_center_id'])
            || $diffs !== [];

        if ($onlyErrors && ! $hasError) {
            continue;
        }

        $summary['total']++;
        $summary[$status === 'matched' ? 'com_colaborador' : 'sem_colaborador']++;
        $summary[! empty($attrs['organization_unit_id']) ? 'unidade_resolvida' : 'unidade_pendente']++;
        $summary[! empty($attrs['organization_department_id']) ? 'departamento_resolvido' : 'departamento_pendente']++;
        $summary[! empty($attrs['organization_cost_center_id']) ? 'centro_resolvido' : 'centro_pendente']++;
        $summary['precisam_atualizar'] += $diffs !== [] ? 1 : 0;

        $table[] = [
            $row->id,
            $row->email,
            $status ?: '-',
            $row->organization_unit_id ?: '-',
            $attrs['organization_unit_id'] ?: '-',
            $row->organization_department_id ?: '-',
            $attrs['organization_department_id'] ?: '-',
            $row->organization_cost_center_id ?: '-',
            $attrs['organization_cost_center_id'] ?: '-',
            $diffs !== [] ? implode(', ', $diffs) : '-',
        ];
    }

    $this->table(['ID', 'E-mail', 'Match', 'Unid. atual', 'Unid. nova', 'Depto atual', 'Depto novo', 'CC atual', 'CC novo', 'Diferenças'], $table);
    $this->table(['Métrica', 'Valor'], collect($summary)->map(fn ($value, $key): array => [$key, $value])->values()->all());

    return 0;
})->purpose('Audita enriquecimento organizacional local das licenças Office.');

Artisan::command('office:backfill-organizational-links {--dry-run} {--limit=} {--email=} {--office-license-id=} {--only-missing} {--force-refresh}', function (OfficeUserSyncService $syncService) {
    $query = OfficeLicense::query()->orderBy('id');
    $dryRun = (bool) $this->option('dry-run');
    $onlyMissing = (bool) $this->option('only-missing');
    $forceRefresh = (bool) $this->option('force-refresh');
    $email = trim((string) $this->option('email'));
    $id = trim((string) $this->option('office-license-id'));
    $limit = trim((string) $this->option('limit'));

    if ($email !== '') {
        $query->whereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
    }
    if ($id !== '') {
        $query->where('id', (int) $id);
    }
    if ($onlyMissing) {
        $query->where(function ($q): void {
            $q->whereNull('matricula')
                ->orWhereRaw('TRIM(COALESCE(matricula, "")) = ""')
                ->orWhereNull('organization_unit_id')
                ->orWhereNull('organization_cost_center_id')
                ->orWhereNull('centro_custo')
                ->orWhereRaw('TRIM(COALESCE(centro_custo, "")) = ""')
                ->orWhereNull('unicoop_office')
                ->orWhereNull('area_office');
        });
    }
    if ($limit !== '') {
        $query->limit(max((int) $limit, 1));
    }

    $analyzed = 0;
    $updated = 0;
    $unchanged = 0;
    $manualPreserved = 0;
    $unitFilled = 0;
    $departmentFilled = 0;
    $costCenterFilled = 0;
    $notFound = 0;
    $ambiguous = 0;
    $samples = [];

    foreach ($query->get() as $row) {
        $analyzed++;
        $sync = $syncService->buildOfficeLicenseAttributes([
            'nome' => (string) $row->nome,
            'email' => (string) $row->email,
            'department' => (string) $row->departamento_unidade,
        ], $row);
        $attrs = $sync['attributes'];

        if (($row->organization_assignment_source ?? null) === 'manual') {
            $manualPreserved++;
        }

        $payload = [];
        foreach ([
            'matricula',
            'departamento_unidade',
            'unicoop_office',
            'area_office',
            'organization_unit_id',
            'organization_department_id',
            'organization_cost_center_id',
            'organization_position_id',
            'organization_position_source',
            'organization_assignment_source',
            'organization_assignment_locked_at',
            'organization_assignment_locked_by',
            'unidade_nome',
            'area_nome',
            'departamento_nome',
            'setor_nome',
            'cargo_nome',
            'empresa_nome',
            'empresa_id_cocari',
            'centro_custo',
            'centro_custo_nome',
            'colaborador_nome',
            'colaborador_source_view',
            'colaborador_match_score',
            'colaborador_match_type',
            'colaborador_matched_at',
            'colaborador_match_status',
            'colaborador_match_message',
        ] as $field) {
            if (! array_key_exists($field, $attrs)) {
                continue;
            }
            if (! $forceRefresh && ($attrs[$field] === null || trim((string) $attrs[$field]) === '')) {
                continue;
            }
            if ($field === 'colaborador_match_score'
                && is_numeric($row->{$field} ?? null)
                && is_numeric($attrs[$field] ?? null)
                && round((float) $row->{$field}, 2) === round((float) $attrs[$field], 2)) {
                continue;
            }
            if ($field === 'colaborador_matched_at' && ! empty($row->{$field})) {
                continue;
            }
            if (trim((string) ($row->{$field} ?? '')) !== trim((string) ($attrs[$field] ?? ''))) {
                $payload[$field] = $attrs[$field];
            }
        }

        $status = (string) ($attrs['colaborador_match_status'] ?? '');
        $notFound += $status === 'not_found' ? 1 : 0;
        $ambiguous += $status === 'ambiguous' ? 1 : 0;

        if ($payload === []) {
            $unchanged++;
            continue;
        }

        $unitFilled += array_key_exists('organization_unit_id', $payload) && ! empty($payload['organization_unit_id']) ? 1 : 0;
        $departmentFilled += array_key_exists('organization_department_id', $payload) && ! empty($payload['organization_department_id']) ? 1 : 0;
        $costCenterFilled += array_key_exists('organization_cost_center_id', $payload) && ! empty($payload['organization_cost_center_id']) ? 1 : 0;
        $updated++;

        if (count($samples) < 10) {
            $samples[] = [$row->id, $row->email, implode(', ', array_keys($payload))];
        }

        if (! $dryRun) {
            $payload['updated_at'] = now();
            OfficeLicense::query()->whereKey($row->id)->update($payload);
        }
    }

    if ($samples !== []) {
        $this->table(['ID', 'E-mail', $dryRun ? 'Campos que seriam atualizados' : 'Campos atualizados'], $samples);
    }

    $this->table(['Métrica', 'Valor'], [
        ['Modo', $dryRun ? 'dry-run' : 'apply'],
        ['Analisados', $analyzed],
        ['Atualizados', $updated],
        ['Sem alteração', $unchanged],
        ['Overrides manuais preservados', $manualPreserved],
        ['Unidades preenchidas', $unitFilled],
        ['Departamentos preenchidos', $departmentFilled],
        ['Centros de custo preenchidos', $costCenterFilled],
        ['Não encontrados', $notFound],
        ['Ambíguos', $ambiguous],
    ]);

    return 0;
})->purpose('Preenche vínculos organizacionais canônicos nas licenças Office usando snapshots locais.');

Artisan::command('office:backfill-missing-departments', function () {
    $this->components->info('Backfill de usuários Office sem departamento/unidade...');
    $officeUserSyncService = app(OfficeUserSyncService::class);

    $missingRows = DB::table('office_licenses')
        ->where('ativo', true)
        ->where(function ($q): void {
            $q->whereNull('departamento_unidade')
                ->orWhereRaw('TRIM(departamento_unidade) = ""')
                ->orWhere('departamento_unidade', 'N/D')
                ->orWhereRaw('TRIM(departamento_unidade) IN ("0", "00")');
        })
        ->get([
            'id',
            'nome',
            'email',
            'matricula',
            'departamento_unidade',
            'unicoop_office',
            'area_office',
            'unidade_id',
            'unidade_nome',
            'departamento_id',
            'departamento_nome',
            'centro_custo',
            'centro_custo_nome',
            'colaborador_nome',
            'colaborador_match_score',
            'colaborador_match_type',
            'colaborador_match_status',
            'colaborador_match_message',
            'colaborador_matched_at',
        ]);

    if ($missingRows->isEmpty()) {
        $this->components->info('Nenhum usuário pendente.');
        return 0;
    }

    $updated = 0;
    $matched = 0;
    $ambiguous = 0;
    $noMatch = 0;
    $invalidCentroCusto = 0;
    $unitFound = 0;
    $unitMissing = 0;
    $departmentFound = 0;
    $departmentMissing = 0;

    foreach ($missingRows as $row) {
        $email = mb_strtolower(trim((string) $row->email));
        if ($email === '') {
            $noMatch++;
            continue;
        }

        $sync = $officeUserSyncService->buildOfficeLicenseAttributes([
            'nome' => (string) ($row->nome ?? ''),
            'email' => $email,
            'department' => (string) ($row->departamento_unidade ?? ''),
        ], $row);

        $changes = $sync['attributes'];
        $changes['updated_at'] = now();

        DB::table('office_licenses')->where('id', $row->id)->update($changes);
        $updated++;

        $status = (string) ($changes['colaborador_match_status'] ?? '');
        $unitFound += ! empty($changes['unidade_id']) ? 1 : 0;
        $unitMissing += empty($changes['unidade_id']) ? 1 : 0;
        $departmentFound += ! empty($changes['departamento_id']) ? 1 : 0;
        $departmentMissing += empty($changes['departamento_id']) ? 1 : 0;
        if ($status === 'matched' || $status === 'missing_matricula') {
            $matched++;
        } elseif ($status === 'ambiguous') {
            $ambiguous++;
        } elseif ($status === 'invalid_centro_custo') {
            $invalidCentroCusto++;
        } else {
            $noMatch++;
        }
    }

    $pending = DB::table('office_licenses')
        ->where('ativo', true)
        ->where(function ($q): void {
            $q->whereNull('departamento_unidade')
                ->orWhereRaw('TRIM(departamento_unidade) = ""')
                ->orWhere('departamento_unidade', 'N/D')
                ->orWhereRaw('TRIM(departamento_unidade) IN ("0", "00")');
        })
        ->count();

    $this->table(
        ['Métrica', 'Valor'],
        [
            ['Pendentes antes', $missingRows->count()],
            ['Atualizados', $updated],
            ['Vinculados', $matched],
            ['Ambíguos', $ambiguous],
            ['Centro custo inválido', $invalidCentroCusto],
            ['Unidade encontrada', $unitFound],
            ['Sem unidade', $unitMissing],
            ['Departamento encontrado', $departmentFound],
            ['Sem departamento', $departmentMissing],
            ['Sem correspondência', $noMatch],
            ['Pendentes após', $pending],
        ]
    );

    return 0;
})->purpose('Preenche departamento/unidade (e matrícula quando possível) para usuários Office pendentes.');

Artisan::command('circuits:repair-unit-links {--apply}', function () {
    $this->components->error('Comando desativado. Circuitos usam organization_unit_id; use organization:backfill-links para vínculos legados.');

    return 1;

    $normalize = static function (string $value): string {
        return (string) Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();
    };

    $units = DB::table('unidades')->get(['id_unidades', 'unidade'])
        ->map(function ($u) use ($normalize) {
            return (object) [
                'id_unidades' => (int) $u->id_unidades,
                'unidade' => (string) $u->unidade,
                'norm' => $normalize((string) $u->unidade),
            ];
        })
        ->filter(fn ($u) => $u->norm !== '')
        ->values();

    $circuits = DB::table('circuitos_unidades')
        ->get(['id_circuitos', 'id_unidades', 'unidades_circuitos']);

    $fixes = [];
    $unmatched = 0;

    foreach ($circuits as $c) {
        $text = trim((string) ($c->unidades_circuitos ?? ''));
        if ($text === '') {
            $unmatched++;
            continue;
        }

        $norm = $normalize($text);
        if ($norm === '') {
            $unmatched++;
            continue;
        }

        $best = null;
        $bestScore = -100000;

        // 1) Match exato sempre vence.
        $exact = $units->first(fn ($u) => $u->norm === $norm);
        if ($exact) {
            $best = $exact;
            $bestScore = 100000;
        }

        foreach ($units as $u) {
            if ($u->norm === '') {
                continue;
            }

            $contains = str_contains($norm, $u->norm) || str_contains($u->norm, $norm);
            if (! $contains) {
                continue;
            }

            $score = strlen($u->norm);

            // Prioriza quando o texto começa com o nome da unidade.
            if (str_starts_with($norm, $u->norm)) {
                $score += 20;
            }

            // Evita escolher versões II/V quando o texto não contém esse sufixo.
            if (preg_match('/\bii\b/u', $u->norm) && ! preg_match('/\bii\b/u', $norm)) {
                $score -= 120;
            }
            if (preg_match('/\bv\b/u', $u->norm) && ! preg_match('/\bv\b/u', $norm)) {
                $score -= 80;
            }

            // Se o texto menciona "sede", prioriza unidade de sede.
            if (str_contains($norm, 'sede') && str_contains($u->norm, 'sede')) {
                $score += 60;
            }

            if ($score > $bestScore) {
                $best = $u;
                $bestScore = $score;
            }
        }

        if (! $best) {
            $unmatched++;
            continue;
        }

        $currentId = (int) ($c->id_unidades ?? 0);
        if ($currentId !== (int) $best->id_unidades) {
            $fixes[] = [
                'id_circuitos' => (int) $c->id_circuitos,
                'from' => $currentId > 0 ? $currentId : null,
                'to' => (int) $best->id_unidades,
                'texto' => $text,
                'unidade_alvo' => $best->unidade,
            ];
        }
    }

    $this->info('Análise concluída.');
    $this->line('Circuitos totais: ' . $circuits->count());
    $this->line('Correções sugeridas: ' . count($fixes));
    $this->line('Sem match: ' . $unmatched);

    foreach (array_slice($fixes, 0, 40) as $fix) {
        $this->line("#{$fix['id_circuitos']} {$fix['texto']} :: {$fix['from']} -> {$fix['to']} ({$fix['unidade_alvo']})");
    }

    if (! $this->option('apply')) {
        $this->warn('Dry-run: rode com --apply para gravar.');
        return 0;
    }

    DB::transaction(function () use ($fixes): void {
        foreach ($fixes as $fix) {
            DB::table('circuitos_unidades')
                ->where('id_circuitos', $fix['id_circuitos'])
                ->update(['id_unidades' => $fix['to']]);
        }
    });

    $this->info('Correções aplicadas: ' . count($fixes));
    return 0;
})->purpose('Repara vínculo id_unidades dos circuitos com base no nome textual da unidade.');

Artisan::command('coreti:google-emails-import {path=storage/app/imports/google_admin_emails.csv}', function () {
    $path = (string) $this->argument('path');

    try {
        $result = app(CoretiGoogleEmailImportService::class)->importFromFile($path);
    } catch (\Throwable $e) {
        $this->components->error($e->getMessage());
        return 1;
    }

    $this->components->info('Importação de e-mails do Google Admin concluída.');
    $this->line('Total importado: ' . ($result['total'] ?? 0));
    $this->line('Encontrados no AD: ' . ($result['found_ad'] ?? 0));
    $this->line('Não encontrados no AD: ' . ($result['not_found_ad'] ?? 0));
    $this->line('Mapeados: ' . ($result['mapped'] ?? 0));
    $this->line('Pendentes: ' . ($result['pending'] ?? 0));
    $this->line('Relatório: ' . ($result['report_path'] ?? '-'));

    return 0;
})->purpose('Importa e-mails do Google Admin e realiza o primeiro mapeamento de rateio.');

Artisan::command('coreti:google-emails-sync-service-desk {--dry-run} {--apply}', function () {
    $apply = (bool) $this->option('apply');
    $dryRun = ! $apply;

    if ($dryRun) {
        $this->components->info('Dry-run: nenhuma alteração será gravada. Use --apply para aplicar.');
    }

    try {
        $result = app(CoretiGoogleEmailImportService::class)->syncFromServiceDeskEmails($dryRun);
    } catch (\Throwable $e) {
        $this->components->error($e->getMessage());
        return 1;
    }

    $this->components->info($dryRun ? 'Dry-run concluído.' : 'Sincronização aplicada.');
    $this->line('Total legado: ' . ($result['total'] ?? 0));
    $this->line('Encontrados no CoreTI: ' . ($result['matched'] ?? 0));
    $this->line('Atualizados: ' . ($result['updated'] ?? 0));
    $this->line('Já sincronizados: ' . ($result['already_in_sync'] ?? 0));
    $this->line('Sem correspondência no CoreTI: ' . ($result['missing_in_coreti'] ?? 0));
    $this->line('Overrides manuais aplicados: ' . ($result['manual_overrides'] ?? 0));
    $this->line('Relatório: ' . ($result['report_path'] ?? '-'));

    return 0;
})->purpose('Copia o rateio atualizado de service_desk_emails para coreti_google_emails.');

Artisan::command('coreti:google-emails-mapear-rateio {--dry-run} {--apply}', function () {
    $apply = (bool) $this->option('apply');
    $dryRun = ! $apply;

    if ($dryRun) {
        $this->components->info('Dry-run: não serão aplicadas alterações. Use --apply para gravar.');
    }

    try {
        $result = app(CoretiGoogleEmailImportService::class)->remapRateio($dryRun);
    } catch (\Throwable $e) {
        $this->components->error($e->getMessage());
        return 1;
    }

    $this->components->info($dryRun ? 'Dry-run concluído.' : 'Remapeamento aplicado.');
    $this->line('Total processado: ' . ($result['total'] ?? 0));
    if (! $dryRun) {
        $this->line('Registros atualizados: ' . ($result['updated'] ?? 0));
    }
    $this->line('Encontrados no AD: ' . ($result['found_ad'] ?? 0));
    $this->line('Não encontrados no AD: ' . ($result['not_found_ad'] ?? 0));
    $this->line('Mapeados: ' . ($result['mapped'] ?? 0));
    $this->line('Pendentes: ' . ($result['pending'] ?? 0));
    $this->line('Relatório: ' . ($result['report_path'] ?? '-'));

    return 0;
})->purpose('Recalcula o mapeamento de rateio dos e-mails importados, em dry-run por padrão.');

Artisan::command('cmdb:import-servers-spreadsheet {path=/var/www/html/coreti/Servidores_cocari.xlsx}', function () {
    $path = (string) $this->argument('path');

    if (! file_exists($path)) {
        $this->error("Arquivo não encontrado: {$path}");

        return 1;
    }

    $summary = app(CmdbServerSpreadsheetImportService::class)->import($path);

    $this->info('Importação concluída.');
    $this->table(
        ['Total', 'Criados', 'Atualizados', 'Linhas em branco', 'Erros'],
        [[
            $summary['total_rows'],
            $summary['created'],
            $summary['updated'],
            $summary['skipped_blank'],
            $summary['errors'],
        ]]
    );

    if (! empty($summary['issues'])) {
        $this->warn('Linhas com erro:');
        foreach ($summary['issues'] as $issue) {
            $this->line("Linha {$issue['row']} - {$issue['name']}: {$issue['message']}");
        }
    }

    return 0;
})->purpose('Importa a planilha Servidores_cocari.xlsx para os ativos do tipo servidor no CMDB.');

Artisan::command('cmdb:update-server-details-spreadsheet {path=/var/www/html/coreti/Servidores COCARI.xlsx}', function () {
    $path = (string) $this->argument('path');

    if (! file_exists($path)) {
        $this->error("Arquivo não encontrado: {$path}");

        return 1;
    }

    $summary = app(CmdbServerSpreadsheetImportService::class)->updateExisting($path);

    $this->info('Atualização complementar concluída sem criação de ativos.');
    $this->line('Linhas preenchidas: ' . $summary['total_rows']);
    $this->line('Ativos atualizados: ' . $summary['updated']);
    $this->line('Sem alteração: ' . $summary['unchanged']);
    $this->line('Sem correspondência: ' . $summary['unmatched']);
    $this->line('Correspondências ambíguas: ' . $summary['ambiguous']);
    $this->line('Linhas em branco: ' . $summary['skipped_blank']);

    if (! empty($summary['issues'])) {
        $this->warn('Linhas que precisam de revisão:');
        foreach ($summary['issues'] as $issue) {
            $this->line("Linha {$issue['row']} - {$issue['name']}: {$issue['message']}");
        }
    }

    return 0;
})->purpose('Complementa servidores e VMs existentes com dados da planilha, sem criar duplicidades.');

Artisan::command('cmdb:sync-server-ips {--subnet-mask=255.255.255.0} {--gateway=172.17.255.254}', function () {
    $summary = app(CmdbServerIpSyncService::class)->sync(
        (string) $this->option('subnet-mask'),
        (string) $this->option('gateway')
    );

    $this->info('Sincronização de IPs concluída.');
    $this->table(
        ['Hosts', 'Resolvidos', 'Criados', 'Atualizados', 'Sem alteração', 'Sem resolução'],
        [[
            $summary['total_hosts'],
            $summary['resolved'],
            $summary['created'],
            $summary['updated'],
            $summary['unchanged'],
            count($summary['unresolved']),
        ]]
    );

    if (! empty($summary['unresolved'])) {
        $this->warn('Hostnames sem resolução DNS local:');
        foreach ($summary['unresolved'] as $item) {
            $this->line("{$item['asset_id']} - {$item['name']} ({$item['hostname']})");
        }
    }

    return 0;
})->purpose('Resolve os hostnames dos servidores do CMDB e grava IP principal, máscara e gateway.');

Schedule::command('intranet:sync-organization --full')
    ->dailyAt((string) env('INTRANET_ORGANIZATION_SYNC_TIME', '02:00'))
    ->withoutOverlapping();

Schedule::command('intranet:sync-hr-snapshots --full')
    ->dailyAt((string) env('INTRANET_HR_SYNC_TIME', '02:30'))
    ->withoutOverlapping();

Schedule::command('cmdb:veeam-sync')
    ->hourly()
    ->withoutOverlapping();

// LEGADO: backups:verificar-atrasados lê apenas cmdb_database_backups, a
// tabela alimentada pelo webhook do agente/n8n. Com a auditoria autônoma, o
// silêncio deixou de ser sinal — o CoreTI vai olhar o servidor de qualquer
// jeito. Fora do agendamento; segue disponível para consultar o histórico
// legado à mão.

// Auditoria autônoma de backups: o CoreTI conecta nos servidores e verifica
// os alvos cadastrados. Não depende de webhook, agente ou n8n.
//
// Roda de hora em hora, mas cada alvo só é auditado quando sua periodicidade
// manda — quem decide é CmdbBackupTarget::deveVerificarAgora(), o que evita
// abrir SSH desnecessário para um backup diário.
Schedule::command('backups:verificar')
    ->hourly()
    ->withoutOverlapping();

// Enriquecimento de rede das VMs (IP, máscara, gateway, DNS).
//
// Separado do inventário de propósito: a coleta exige duas chamadas por VM
// ligada e o vCenter consulta o VMware Tools em cada uma. Rodar junto do
// inventário levava o ciclo de 12s para 45s a cada hora, sem ganho — IP de VM
// quase não muda. Três vezes ao dia cobre com folga.
Schedule::command('cmdb:enriquecer-rede')
    ->cron((string) env('CMDB_ENRIQUECER_REDE_CRON', '15 6,12,18 * * *'))
    ->withoutOverlapping();

// Aprovação automática das descobertas do vCenter.
//
// Roda depois do ciclo de inventário, aplicando as regras de config/cmdb.php:
// VM e host entram sozinhos; template, cópia de backup, restauração e objeto
// que sumiu do vCenter são marcados como ignorados, com motivo, e continuam
// visíveis para reabertura na revisão.
Schedule::command('cmdb:aprovar-descobertas')
    ->cron((string) env('CMDB_APROVAR_DESCOBERTAS_CRON', '40 * * * *'))
    ->withoutOverlapping();

/*
 | Expurgo do Active Directory.
 |
 | A prévia é montada e enviada pelo próprio servidor do AD (tarefa agendada
 | para o dia 1º). Aqui só roda a expiração: aprovação parada perde a validade
 | porque a lista conferida envelhece — contas são reativadas, pessoas voltam.
 */
Schedule::command('ad:expirar-lotes')
    ->dailyAt('06:30')
    ->onOneServer()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Impressoras — EKM Insight API
|--------------------------------------------------------------------------
|
| Quatro execuções por dia, nos horários definidos pela TI. O fuso vai
| declarado porque o app roda em UTC: sem ele, 00:00 cairia às 21:00 de
| Brasília, e os horários combinados não seriam os horários reais.
|
| Custo por execução: 4 chamadas (devices, consumíveis, contadores, pedidos).
| A autenticação é uma só por dia, porque o token vive 24h. Dá 17 chamadas
| diárias — o teto em config/insight.php existe para o caso de algo fugir
| desse desenho, não para o uso normal.
|
| withoutOverlapping evita que uma execução lenta se sobreponha à seguinte e
| dobre o consumo.
*/
foreach (['00:00', '07:00', '12:30', '19:00'] as $horario) {
    Schedule::command('impressoras:sincronizar')
        ->dailyAt($horario)
        ->timezone('America/Sao_Paulo')
        ->onOneServer()
        ->withoutOverlapping();
}
