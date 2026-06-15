<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Application;

try {
    $apps = Application::whereNotNull('source_path')->orderBy('id')->get();
    if ($apps->isEmpty()) {
        echo "No applications with source_path found.\n";
        exit(0);
    }

    foreach ($apps as $a) {
        echo "ID: {$a->id} | slug: {$a->slug} | name: {$a->name}\n";
        echo "  source_path: " . ($a->source_path ?? 'NULL') . "\n";
        echo "  file_path:   " . ($a->file_path ?? 'NULL') . "\n";
        echo "  is_bundle:   " . ((int)($a->is_bundle ?? 0)) . "\n";
        $resolved = $a->resolvedFilePath();
        echo "  resolved:    " . ($resolved ?? 'NULL') . "\n";
        echo "  fileExists():" . ($a->fileExists() ? ' true' : ' false') . "\n";
        if (is_string($a->source_path) && str_starts_with($a->source_path, DIRECTORY_SEPARATOR)) {
            echo "  physical exists (source_path): " . (file_exists($a->source_path) ? 'true' : 'false') . "\n";
        }
        echo "  stored physical (storage/app/private/...) exists: " . (file_exists(storage_path('app/private/' . ($a->file_path ?? ''))) ? 'true' : 'false') . "\n";
        echo "---\n";
    }
} catch (Throwable $e) {
    echo "Exception: " . get_class($e) . " - " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "Done.\n";
