<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Application;
use Illuminate\Support\Facades\Storage;

$apps = Application::limit(12)->get();
foreach ($apps as $a) {
    echo "ID: {$a->id} | slug: {$a->slug}\n";
    echo " file_path: {$a->file_path}\n";
    echo " is_bundle: " . ((int) $a->is_bundle) . "\n";
    echo " fileExists(): " . ($a->fileExists() ? 'true' : 'false') . "\n";
    echo " resolvedFilePath(): " . ($a->resolvedFilePath() ?? 'NULL') . "\n";
    echo " physical check (storage/app/private/...): " . (file_exists(storage_path('app/private/' . ($a->file_path ?? ''))) ? 'true' : 'false') . "\n";
    echo "---\n";
}

echo "Done\n";
