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

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

Schedule::command('workspace:sync-dry-run --cache-preview')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Artisan::command('units:sync-intranet', function () {
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

Schedule::command('units:sync-intranet')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->runInBackground();

Artisan::command('departamentos:sync', function () {
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

Artisan::command('office:sync-microsoft {--deactivate-missing}', function () {
    $enabled = filter_var(env('MS_OFFICE_SYNC_ENABLED', false), FILTER_VALIDATE_BOOL);
    if (! $enabled) {
        $this->warn('Sincronização Microsoft Office desativada (MS_OFFICE_SYNC_ENABLED=false).');
        return 0;
    }

    $tenantId = trim((string) env('MS_TENANT_ID', ''));
    $clientId = trim((string) env('MS_CLIENT_ID', ''));
    $clientSecret = trim((string) env('MS_CLIENT_SECRET', ''));
    $allowedDomain = mb_strtolower(trim((string) env('MS_OFFICE_ALLOWED_DOMAIN', 'cocari.com.br')));

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

    $url = 'https://graph.microsoft.com/v1.0/users?$top=999&$select=id,displayName,mail,userPrincipalName,accountEnabled,employeeId,department,assignedLicenses';
    $users = collect();

    while ($url) {
        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->get($url);

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
    ])->keyBy(function ($row) {
        return mb_strtolower(trim((string) $row->email));
    });
    $emailOrgFallback = DB::table('service_desk_emails')
        ->select(['email', 'colaborador_nome', 'matricula', 'centro_custo', 'unicoop_sede', 'area_sede'])
        ->get()
        ->keyBy(function ($row) {
            return mb_strtolower(trim((string) $row->email));
        });
    $adUsersRows = DB::table('ad_users')
        ->select(['nome_completo', 'nome_usuario', 'email', 'unidade_setor', 'e_ativo'])
        ->get();
    $adUsersByEmail = [];
    $adUsersByUsername = [];
    foreach ($adUsersRows as $adUser) {
        $adEmail = mb_strtolower(trim((string) ($adUser->email ?? '')));
        if ($adEmail !== '') {
            $adUsersByEmail[$adEmail] = $adUser;
        }
        $adUsername = mb_strtolower(trim((string) ($adUser->nome_usuario ?? '')));
        if ($adUsername !== '') {
            $adUsersByUsername[$adUsername] = $adUser;
            if (! str_contains($adUsername, '@')) {
                $adUsersByUsername[$adUsername . '@cocari.com.br'] = $adUser;
            }
        }
    }

    // Mapa de centro de custo RH por (unicoop + nome) para preencher código/área quando faltante.
    $ccRhByUnicoopName = [];
    try {
        $ccRhRows = DB::connection('intranet_cocari')
            ->table('cadCentroCustoRh')
            ->select(['IdCentroCusto', 'Nome', 'IdUnicoop'])
            ->get();
        foreach ($ccRhRows as $ccRh) {
            $u = trim((string) ($ccRh->IdUnicoop ?? ''));
            $u = $u !== '' && ctype_digit($u) ? str_pad($u, 2, '0', STR_PAD_LEFT) : $u;
            $n = $norm((string) ($ccRh->Nome ?? ''));
            if ($u === '' || $n === '') {
                continue;
            }
            $ccRhByUnicoopName[$u . '|' . $n] = (string) ($ccRh->IdCentroCusto ?? '');
        }
    } catch (\Throwable) {
        // Sem a origem externa, segue fluxo normal.
    }

    $upsertRows = [];
    $cristalinaOverrides = [];
    $seenEmails = [];
    $created = 0;
    $updated = 0;
    $norm = static fn (?string $v): string => (string) Str::of((string) $v)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->value();
    $isInvalidUnit = static function (?string $value): bool {
        $value = trim((string) $value);
        if ($value === '' || $value === 'N/D') {
            return true;
        }

        return in_array($value, ['0', '00'], true);
    };
    $isBlank = static fn (?string $v): bool => trim((string) $v) === '';

    // Unidades válidas para Office: bases locais e intranet_cocari.
    $allowedUnitNorm = [];
    $canonicalUnitByNorm = [];
    $unitMetaByNorm = [];
    foreach (DB::table('service_desk_email_cost_centers')->pluck('name') as $name) {
        $k = $norm($name);
        if ($k !== '') {
            $allowedUnitNorm[$k] = true;
        }
    }
    foreach (DB::table('unidades')->get(['unidade', 'unicoop', 'area']) as $unitRow) {
        $name = (string) ($unitRow->unidade ?? '');
        $k = $norm($name);
        if ($k !== '') {
            $allowedUnitNorm[$k] = true;
            $canonicalUnitByNorm[$k] = $name;
            $unitMetaByNorm[$k] = [
                'unicoop' => trim((string) ($unitRow->unicoop ?? '')),
                'area' => trim((string) ($unitRow->area ?? '')),
            ];
        }
    }
    try {
        foreach (DB::connection('intranet_cocari')->table('cadUnicoop')->where('Ativo', 1)->pluck('Nome') as $name) {
            $k = $norm($name);
            if ($k !== '') {
                $allowedUnitNorm[$k] = true;
            }
        }
    } catch (\Throwable) {
        // Se a origem externa falhar, seguimos com as bases locais.
    }

    $orgByMatricula = static function (?string $matricula): ?array {
        $matricula = trim((string) $matricula);
        if ($matricula === '') {
            return null;
        }

        static $cache = [];
        if (array_key_exists($matricula, $cache)) {
            return $cache[$matricula];
        }

        try {
            $conn = DB::connection('intranet_cocari');

            $viewRow = null;
            try {
                $viewRow = $conn
                    ->table('vwCadColaboradorEmpresa')
                    ->select([
                        'CodColaborador',
                        'CentroCusto',
                        'IdUnicoop',
                        'IdDepartamento',
                        'NomeUnidade',
                        'NomeDepartamento',
                        'Ativo',
                    ])
                    ->where('CodColaborador', $matricula)
                    ->orderByDesc('Ativo')
                    ->first();
            } catch (\Throwable) {
                $viewRow = null;
            }

            if ($viewRow) {
                $unicoop = trim((string) ($viewRow->IdUnicoop ?? ''));
                $unicoop = $unicoop !== '' && ctype_digit($unicoop)
                    ? str_pad($unicoop, 2, '0', STR_PAD_LEFT)
                    : ($unicoop !== '' ? $unicoop : null);

                $centroCusto = trim((string) ($viewRow->CentroCusto ?? ''));
                $departamentoCodigo = trim((string) ($viewRow->IdDepartamento ?? ''));
                $departamentoCodigo = $departamentoCodigo !== '' ? preg_replace('/\D+/', '', $departamentoCodigo) : '';
                $departamentoCodigo = $departamentoCodigo !== '' ? ltrim($departamentoCodigo, '0') : '';
                $centroCustoCodigo = trim((string) ($viewRow->CentroCusto ?? ''));
                $centroCustoCodigo = $centroCustoCodigo !== '' ? preg_replace('/\D+/', '', $centroCustoCodigo) : '';
                $centroCustoCodigo = $centroCustoCodigo !== '' ? ltrim($centroCustoCodigo, '0') : '';
                if ($centroCustoCodigo !== '' && mb_strlen($centroCustoCodigo) >= 3) {
                    $centroCustoCodigo = mb_substr($centroCustoCodigo, -3);
                }

                $areaCodigo = $unicoop === '01'
                    ? ($departamentoCodigo !== '' ? $departamentoCodigo : $centroCustoCodigo)
                    : ($centroCustoCodigo !== '' ? $centroCustoCodigo : $departamentoCodigo);
                $areaCodigo = $areaCodigo !== '' ? $areaCodigo : null;

                $departamentoUnidade = $unicoop === '01'
                    ? trim((string) ($viewRow->NomeDepartamento ?? ''))
                    : trim((string) ($viewRow->NomeUnidade ?? ''));

                if ($departamentoUnidade === '') {
                    $departamentoUnidade = $centroCusto;
                }

                $cache[$matricula] = [
                    'departamento_unidade' => $departamentoUnidade !== '' ? mb_substr($departamentoUnidade, 0, 255) : null,
                    'unicoop_office' => $unicoop,
                    'area_office' => $areaCodigo ? mb_substr($areaCodigo, 0, 50) : null,
                ];

                return $cache[$matricula];
            }

            $row = $conn
                ->table('cadColaboradorEmpresa as cce')
                ->leftJoin('cadDepartamento as dep', 'dep.IdDepartamento', '=', 'cce.IdDepartamento')
                ->leftJoin('cadCentroCustoRh as rh', 'rh.IdCentroCusto', '=', 'cce.CentroCusto')
                ->leftJoin('cadCentroCustos as cc', 'cc.IdCCusto', '=', 'cce.CentroCusto')
                ->select([
                    DB::raw('COALESCE(cce.NomeCentroCusto, rh.Nome, cc.Nome, dep.Nome, cce.CentroCusto) as centro_custo'),
                    DB::raw('COALESCE(cce.IdUnicoop, rh.IdUnicoop, cc.IdUnicoop) as id_unicoop'),
                    DB::raw('cce.CentroCusto as centro_custo_codigo'),
                    DB::raw('cce.IdDepartamento as id_departamento_codigo'),
                    DB::raw('COALESCE(dep.Nome, cce.IdDepartamento) as departamento'),
                ])
                ->where('cce.Matricula', $matricula)
                ->orderByDesc('cce.Ativo')
                ->orderByDesc('cce.IdColaborador')
                ->first();

            if (! $row) {
                $row = $conn
                    ->table('cadColaborador as c')
                    ->leftJoin('cadDepartamento as dep', 'dep.IdDepartamento', '=', 'c.IdDepartamento')
                    ->leftJoin('cadCentroCustoRh as rh', 'rh.IdCentroCusto', '=', 'c.CentroCusto')
                    ->leftJoin('cadCentroCustos as cc', 'cc.IdCCusto', '=', 'c.CentroCusto')
                    ->select([
                        DB::raw('COALESCE(rh.Nome, cc.Nome, dep.Nome, c.CentroCusto) as centro_custo'),
                        DB::raw('COALESCE(c.IdUnicoop, rh.IdUnicoop, cc.IdUnicoop) as id_unicoop'),
                        DB::raw('c.CentroCusto as centro_custo_codigo'),
                        DB::raw('c.IdDepartamento as id_departamento_codigo'),
                        DB::raw('COALESCE(dep.Nome, c.IdDepartamento) as departamento'),
                    ])
                    ->where('c.Matricula', $matricula)
                    ->orderByDesc('c.Ativo')
                    ->first();
            }

            if (! $row) {
                $cache[$matricula] = null;
                return null;
            }

            $unicoop = trim((string) ($row->id_unicoop ?? ''));
            $unicoop = $unicoop !== '' && ctype_digit($unicoop)
                ? str_pad($unicoop, 2, '0', STR_PAD_LEFT)
                : ($unicoop !== '' ? $unicoop : null);

            $centro = trim((string) ($row->centro_custo ?? ''));
            $departamento = trim((string) ($row->departamento ?? ''));

            $departamentoCodigo = trim((string) ($row->id_departamento_codigo ?? ''));
            $departamentoCodigo = $departamentoCodigo !== '' ? preg_replace('/\D+/', '', $departamentoCodigo) : '';
            $departamentoCodigo = $departamentoCodigo !== '' ? ltrim($departamentoCodigo, '0') : '';

            $centroCustoCodigo = trim((string) ($row->centro_custo_codigo ?? ''));
            $centroCustoCodigo = $centroCustoCodigo !== '' ? preg_replace('/\D+/', '', $centroCustoCodigo) : '';
            $centroCustoCodigo = $centroCustoCodigo !== '' ? ltrim($centroCustoCodigo, '0') : '';
            if ($centroCustoCodigo !== '' && mb_strlen($centroCustoCodigo) >= 3) {
                $centroCustoCodigo = mb_substr($centroCustoCodigo, -3);
            }

            $areaCodigo = $unicoop === '01'
                ? ($departamentoCodigo !== '' ? $departamentoCodigo : $centroCustoCodigo)
                : ($centroCustoCodigo !== '' ? $centroCustoCodigo : $departamentoCodigo);
            $areaCodigo = $areaCodigo !== '' ? $areaCodigo : null;

            $departamentoUnidade = $unicoop === '01' ? ($departamento !== '' ? $departamento : $centro) : $centro;

            $cache[$matricula] = [
                'departamento_unidade' => $departamentoUnidade !== '' ? mb_substr($departamentoUnidade, 0, 255) : null,
                'unicoop_office' => $unicoop,
                'area_office' => $areaCodigo ? mb_substr($areaCodigo, 0, 50) : null,
            ];

            return $cache[$matricula];
        } catch (\Throwable) {
            $cache[$matricula] = null;
            return null;
        } finally {
            try {
                DB::connection('intranet_cocari')->disconnect();
            } catch (\Throwable) {
            }
        }
    };

    $orgByGasUsuarioForCristalina = static function (?string $email, ?string $matricula): ?array {
        $email = mb_strtolower(trim((string) $email));
        $matricula = trim((string) $matricula);
        $cacheKey = $email . '|' . $matricula;

        static $cache = [];
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            $conn = DB::connection('intranet_cocari');

            $query = $conn
                ->table('gasUsuario as gu')
                ->leftJoin('cadColaborador as c', 'c.IdPessoa', '=', 'gu.IdPessoa')
                ->leftJoin('cadCentroCustoRh as rh', 'rh.IdCentroCusto', '=', 'c.CentroCusto')
                ->leftJoin('cadCentroCustos as cc', 'cc.IdCCusto', '=', 'c.CentroCusto')
                ->select([
                    DB::raw('COALESCE(c.IdUnicoop, rh.IdUnicoop, cc.IdUnicoop) as id_unicoop'),
                    DB::raw('COALESCE(c.CentroCusto, rh.IdCentroCusto, cc.IdCCusto) as centro_custo_codigo'),
                ]);

            if ($email !== '') {
                $userAd = trim((string) Str::of($email)->before('@'));
                $query->where(function ($q) use ($email, $userAd): void {
                    $q->where('c.Email', $email);
                    if ($userAd !== '') {
                        $q->orWhere('gu.UsuarioAd', $userAd);
                    }
                });
            }

            if ($matricula !== '') {
                $query->orWhere('c.Matricula', $matricula);
            }

            $row = $query->orderByDesc('c.Ativo')->first();
            if (! $row) {
                $cache[$cacheKey] = null;
                return null;
            }

            $unicoop = trim((string) ($row->id_unicoop ?? ''));
            $unicoop = $unicoop !== '' && ctype_digit($unicoop)
                ? str_pad($unicoop, 2, '0', STR_PAD_LEFT)
                : ($unicoop !== '' ? $unicoop : null);

            $area = trim((string) ($row->centro_custo_codigo ?? ''));
            $area = $area !== '' ? preg_replace('/\D+/', '', $area) : '';
            $area = $area !== '' ? ltrim($area, '0') : '';
            if ($area !== '' && mb_strlen($area) >= 3) {
                $area = mb_substr($area, -3);
            }
            $area = $area !== '' ? $area : null;

            $cache[$cacheKey] = [
                'unicoop_office' => $unicoop,
                'area_office' => $area,
            ];

            return $cache[$cacheKey];
        } catch (\Throwable) {
            $cache[$cacheKey] = null;
            return null;
        }
    };

    $graphUsersByEmail = [];

    foreach ($users as $user) {
        $email = mb_strtolower(trim((string) ($user['mail'] ?? $user['userPrincipalName'] ?? '')));
        if ($email === '' || ! str_contains($email, '@')) {
            continue;
        }

        if (str_ends_with($email, '@cocaricoop.onmicrosoft.com')) {
            $email = (string) Str::of($email)->before('@') . '@cocari.com.br';
        }

        if ($allowedDomain !== '' && ! str_ends_with($email, '@' . $allowedDomain)) {
            continue;
        }

        $seenEmails[$email] = true;
        $graphUsersByEmail[$email] = [
            'id' => (string) ($user['id'] ?? ''),
            'employeeId' => trim((string) ($user['employeeId'] ?? '')),
            'department' => trim((string) ($user['department'] ?? '')),
        ];

        $skuIds = collect($user['assignedLicenses'] ?? [])
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
            if ($envConfigured !== []) {
                return ! empty(array_intersect($ids, $envConfigured));
            }
            foreach ($parts as $part) {
                foreach ($partNeedles as $needle) {
                    if (str_contains($part, $needle)) {
                        return true;
                    }
                }
            }
            return false;
        };

        $existingRow = $existing->get($email);
        $employeeId = trim((string) ($user['employeeId'] ?? ''));
        if ($employeeId === '?' || $employeeId === '-' || $employeeId === '--') {
            $employeeId = '';
        }
        $fallback = $emailOrgFallback->get($email);
        $emailUserPart = mb_strtolower(trim((string) explode('@', $email)[0]));
        $adUser = $adUsersByEmail[$email]
            ?? ($adUsersByUsername[$email] ?? null)
            ?? ($adUsersByUsername[$emailUserPart] ?? null);
        $fallbackMatricula = trim((string) ($fallback->matricula ?? ''));
        $currentMatricula = trim((string) ($existingRow->matricula ?? ''));
        // Prioridade: matrícula do CoreTI (service_desk_emails) -> Microsoft employeeId -> já existente.
        $matricula = $fallbackMatricula !== ''
            ? $fallbackMatricula
            : ($employeeId !== '' ? $employeeId : ($currentMatricula !== '' ? $currentMatricula : null));

        $orgData = $orgByMatricula($matricula);
        $fallbackCentro = trim((string) ($fallback->centro_custo ?? ''));
        $fallbackUnicoop = trim((string) ($fallback->unicoop_sede ?? ''));
        $fallbackArea = trim((string) ($fallback->area_sede ?? ''));
        $fallbackNome = trim((string) ($fallback->colaborador_nome ?? ''));
        $adNome = trim((string) ($adUser->nome_completo ?? ''));
        $adUnidadeSetor = trim((string) ($adUser->unidade_setor ?? ''));
        $adUnidadeSetorNorm = $norm($adUnidadeSetor);
        $adUnidadeSetorCanon = $adUnidadeSetorNorm !== '' && isset($canonicalUnitByNorm[$adUnidadeSetorNorm])
            ? $canonicalUnitByNorm[$adUnidadeSetorNorm]
            : $adUnidadeSetor;

        // Contabiliza somente as 4 licenças definidas:
        // Microsoft 365 Apps for enterprise, Microsoft 365 Apps for business, Power BI Pro, Visio Plan 2.
        $hasOfficeApps = $hasSku($skuIds, $skuParts, $skuOfficeApps, ['m365_apps', 'o365_proplus', 'officesubscription']);
        $hasOfficeBusiness = $hasSku($skuIds, $skuParts, $skuOfficeBusiness, ['business', 'm365_business', 'o365_business']);
        $hasPowerBiPro = $hasSku($skuIds, $skuParts, $skuPowerBiPro, ['power_bi_pro']);
        $hasVisioPlan = $hasSku($skuIds, $skuParts, $skuVisioPlan, ['visio']);

        $hasAnyTrackedLicense = $hasOfficeApps || $hasOfficeBusiness || $hasPowerBiPro || $hasVisioPlan;

        if (! $hasAnyTrackedLicense) {
            // Regra: não adicionar no painel Office usuários sem licenças monitoradas.
            continue;
        }

        $resolvedDepartment = $adUnidadeSetorCanon !== '' ? $adUnidadeSetorCanon : null;
        if ($isInvalidUnit($resolvedDepartment)) {
            $resolvedDepartment = $orgData['departamento_unidade'] ?? null;
        }
        if ($isInvalidUnit($resolvedDepartment)) {
            $resolvedDepartment = $fallbackCentro !== '' ? $fallbackCentro : null;
        }
        if (! $isInvalidUnit($resolvedDepartment)) {
            $candidateNorm = $norm($resolvedDepartment);
            if ($candidateNorm === '' || ! isset($allowedUnitNorm[$candidateNorm])) {
                $resolvedDepartment = null;
            }
        }
        if ($isInvalidUnit($resolvedDepartment)) {
            $resolvedDepartment = 'N/D';
        }

        // Preserva ajustes manuais: para registros já existentes, sincronização NÃO altera
        // unidade/unicoop/área (apenas nome, matrícula, licenças e ativo).
        $existingNome = trim((string) ($existingRow->nome ?? ''));
        $existingMatricula = trim((string) ($existingRow->matricula ?? ''));
        $existingDepartamento = trim((string) ($existingRow->departamento_unidade ?? ''));
        $existingUnicoop = trim((string) ($existingRow->unicoop_office ?? ''));
        $existingArea = trim((string) ($existingRow->area_office ?? ''));

        $finalNome = $existingNome !== ''
            ? $existingNome
            : ($adNome !== '' ? $adNome : (trim((string) ($user['displayName'] ?? '')) !== '' ? trim((string) $user['displayName']) : ($fallbackNome !== '' ? $fallbackNome : $email)));

        $finalMatricula = $existingMatricula !== '' ? $existingMatricula : $matricula;

        $finalDepartment = $existingRow
            ? ($existingRow->departamento_unidade ?? null)
            : $resolvedDepartment;

        $finalUnicoop = $existingRow
            ? ($existingRow->unicoop_office ?? null)
            : ($orgData['unicoop_office'] ?? ($fallbackUnicoop !== '' ? $fallbackUnicoop : null));
        $finalArea = $existingRow
            ? ($existingRow->area_office ?? null)
            : ($orgData['area_office'] ?? ($fallbackArea !== '' ? $fallbackArea : null));

        // Exceção solicitada: colaboradores em Cristalina II podem buscar unicoop/área via gasUsuario.
        $depNormForGas = (string) Str::of((string) $finalDepartment)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '');
        if (str_starts_with($depNormForGas, 'cristalina')) {
            $gasOrg = $orgByGasUsuarioForCristalina($email, $finalMatricula);
            if ($gasOrg) {
                if (! $isBlank($gasOrg['unicoop_office'] ?? null)) {
                    $finalUnicoop = $gasOrg['unicoop_office'];
                }
                if (! $isBlank($gasOrg['area_office'] ?? null)) {
                    $finalArea = $gasOrg['area_office'];
                }

                if ($existingRow) {
                    $cristalinaOverrides[$email] = [
                        'unicoop_office' => $finalUnicoop,
                        'area_office' => $finalArea,
                    ];
                }
            }
        }

        // Regra operacional: registros em "Cristalina II" com unicoop 16
        // pertencem à unidade "Cristalina" (exceto e-mails explicitamente definidos).
        if (
            (string) Str::of((string) $finalDepartment)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '') === 'cristalinaii'
            && trim((string) $finalUnicoop) === '16'
            && mb_strtolower((string) $email) !== 'fabio.xavier@cocari.com.br'
        ) {
            $finalDepartment = 'Cristalina';
        }

        // Se for unidade conhecida, completa unicoop/área diretamente da tabela unidades.
        $depNorm = $norm((string) $finalDepartment);
        if ($depNorm !== '' && isset($unitMetaByNorm[$depNorm])) {
            $uMeta = $unitMetaByNorm[$depNorm];
            if ($isBlank($finalUnicoop) && ! $isBlank($uMeta['unicoop'] ?? null)) {
                $finalUnicoop = $uMeta['unicoop'];
            }
            if ($isBlank($finalArea) && ! $isBlank($uMeta['area'] ?? null)) {
                $finalArea = $uMeta['area'];
            }
        }

        // Se ainda estiver sem área/código, tenta derivar de cadCentroCustoRh por (unicoop + unidade/departamento).
        if ($isBlank($finalArea) && ! $isBlank($finalUnicoop) && ! $isBlank($finalDepartment)) {
            $ccKey = trim((string) $finalUnicoop) . '|' . $norm((string) $finalDepartment);
            $ccId = trim((string) ($ccRhByUnicoopName[$ccKey] ?? ''));
            if ($ccId !== '' && ctype_digit($ccId)) {
                // Regra solicitada: 1.xxx recebe 0 à esquerda => 01.xxx
                if (preg_match('/^1\\d{3}$/', $ccId)) {
                    $finalArea = '0' . mb_substr($ccId, 0, 1) . '.' . mb_substr($ccId, 1);
                } else {
                    $finalArea = $ccId;
                }
            }
        }

        $row = [
            'email' => $email,
            'nome' => $finalNome,
            'matricula' => $finalMatricula,
            'departamento_unidade' => $finalDepartment,
            'unicoop_office' => $finalUnicoop,
            'area_office' => $finalArea,
            'office_apps' => $hasOfficeApps,
            'office_business' => $hasOfficeBusiness,
            'powerbi_pro' => $hasPowerBiPro,
            'visio_plan' => $hasVisioPlan,
            // Para o painel de licenciamento, "ativo" segue a presença de licença monitorada.
            'ativo' => $hasAnyTrackedLicense,
            'created_at' => $existingRow ? null : now(),
            'updated_at' => now(),
        ];

        if (! $existingRow) {
            $created++;
        } else {
            $updated++;
        }

        $upsertRows[] = $row;
    }

    if ($upsertRows !== []) {
        DB::table('office_licenses')->upsert(
            $upsertRows,
            ['email'],
            ['nome', 'matricula', 'office_apps', 'office_business', 'powerbi_pro', 'visio_plan', 'ativo', 'updated_at']
        );
    }

    if ($cristalinaOverrides !== []) {
        foreach ($cristalinaOverrides as $emailKey => $override) {
            DB::table('office_licenses')
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $emailKey)])
                ->update([
                    'unicoop_office' => $override['unicoop_office'] ?? null,
                    'area_office' => $override['area_office'] ?? null,
                    'updated_at' => now(),
                ]);
        }
    }

    // Opcional: envia para Microsoft (admin.cloud) matrícula e unidade/departamento corrigidos manualmente no CoreTI.
    $pushEnabled = filter_var(env('MS_OFFICE_PUSH_ENABLED', false), FILTER_VALIDATE_BOOL);
    if ($pushEnabled) {
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
    if ($this->option('deactivate-missing')) {
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
                'visio_plan' => false,
                'updated_at' => now(),
            ]);
        }
    }

    $this->info("Sincronização Office concluída. Criados: {$created} | Atualizados: {$updated} | Desativados ausentes: {$deactivated} | Excluídos ausentes: {$deleted}");
    return 0;
})->purpose('Sincroniza licenças Office com Microsoft Graph');

Schedule::command('office:sync-microsoft --deactivate-missing')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Artisan::command('office:backfill-missing-departments', function () {
    $this->components->info('Backfill de usuários Office sem departamento/unidade...');

    $normalize = static fn (?string $v): string => (string) Str::of((string) $v)
        ->lower()
        ->ascii()
        ->replaceMatches('/[^a-z0-9]+/', ' ')
        ->trim()
        ->value();

    $missingRows = DB::table('office_licenses')
        ->where('ativo', true)
        ->where(function ($q): void {
            $q->whereNull('departamento_unidade')
                ->orWhereRaw('TRIM(departamento_unidade) = ""')
                ->orWhere('departamento_unidade', 'N/D')
                ->orWhereRaw('TRIM(departamento_unidade) IN ("0", "00")');
        })
        ->get(['id', 'nome', 'email', 'matricula', 'departamento_unidade', 'unicoop_office', 'area_office']);

    if ($missingRows->isEmpty()) {
        $this->components->info('Nenhum usuário pendente.');
        return 0;
    }

    $emails = DB::table('service_desk_emails')
        ->where('ativo', true)
        ->get(['email', 'colaborador_nome', 'matricula', 'centro_custo', 'unicoop_sede', 'area_sede']);

    $byEmail = [];
    $byUsername = [];
    $nameIndex = [];

    foreach ($emails as $item) {
        $email = mb_strtolower(trim((string) $item->email));
        if ($email === '') {
            continue;
        }
        $byEmail[$email] = $item;

        $username = mb_strtolower(trim((string) explode('@', $email)[0]));
        if ($username !== '' && ! isset($byUsername[$username])) {
            $byUsername[$username] = $item;
        }

        $n = $normalize((string) ($item->colaborador_nome ?? ''));
        if ($n !== '') {
            $nameIndex[] = [$n, $item];
        }
    }

    $updated = 0;
    $matchedByEmail = 0;
    $matchedByUsername = 0;
    $matchedByName = 0;
    $noMatch = 0;

    foreach ($missingRows as $row) {
        $email = mb_strtolower(trim((string) $row->email));
        $username = mb_strtolower(trim((string) explode('@', $email)[0]));
        $candidate = null;

        // 1) e-mail direto
        if ($email !== '' && isset($byEmail[$email])) {
            $candidate = $byEmail[$email];
            $matchedByEmail++;
        }

        // 2) nome de usuário + @cocari.com.br
        if (! $candidate && $username !== '') {
            $probeEmail = $username . '@cocari.com.br';
            if (isset($byEmail[$probeEmail])) {
                $candidate = $byEmail[$probeEmail];
                $matchedByUsername++;
            } elseif (isset($byUsername[$username])) {
                $candidate = $byUsername[$username];
                $matchedByUsername++;
            }
        }

        // 3) similaridade por nome
        if (! $candidate) {
            $target = $normalize((string) ($row->nome ?? ''));
            if ($target !== '') {
                $best = null;
                $bestScore = 0.0;
                foreach ($nameIndex as [$nameNorm, $item]) {
                    similar_text($target, $nameNorm, $pct);
                    if ($pct > $bestScore) {
                        $bestScore = $pct;
                        $best = $item;
                    }
                }
                if ($best && $bestScore >= 82.0) {
                    $candidate = $best;
                    $matchedByName++;
                }
            }
        }

        if (! $candidate) {
            $noMatch++;
            continue;
        }

        $centro = trim((string) ($candidate->centro_custo ?? ''));
        $unicoop = trim((string) ($candidate->unicoop_sede ?? ''));
        $area = trim((string) ($candidate->area_sede ?? ''));
        $matricula = trim((string) ($candidate->matricula ?? ''));

        $changes = [];
        $currMatricula = trim((string) ($row->matricula ?? ''));
        if ($currMatricula === '' && $matricula !== '') {
            $changes['matricula'] = $matricula;
        }

        if ($centro !== '') {
            $changes['departamento_unidade'] = $centro;
        }
        if ($unicoop !== '' && trim((string) ($row->unicoop_office ?? '')) === '') {
            $changes['unicoop_office'] = $unicoop;
        }
        if ($area !== '' && trim((string) ($row->area_office ?? '')) === '') {
            $changes['area_office'] = $area;
        }

        if ($changes === []) {
            continue;
        }

        $changes['updated_at'] = now();
        DB::table('office_licenses')->where('id', $row->id)->update($changes);
        $updated++;
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
            ['Match por e-mail', $matchedByEmail],
            ['Match por usuário', $matchedByUsername],
            ['Match por nome similar', $matchedByName],
            ['Sem correspondência', $noMatch],
            ['Pendentes após', $pending],
        ]
    );

    return 0;
})->purpose('Preenche departamento/unidade (e matrícula quando possível) para usuários Office pendentes.');

Artisan::command('circuits:repair-unit-links {--apply}', function () {
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
