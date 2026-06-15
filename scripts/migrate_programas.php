<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

$srcBase = '/var/www/html/plataforma_chamado/templates/programas/';
$imgBase = '/var/www/html/plataforma_chamado/templates/img/';
$disk = Storage::disk('local');

File::ensureDirectoryExists($disk->path('applications/files'));
File::ensureDirectoryExists(public_path('applications/images'));

echo "Scanning applications to migrate...\n";

$apps = Application::query()->get();
$summary = ['migrated' => 0, 'skipped' => 0, 'errors' => []];

foreach ($apps as $app) {
    $changed = false;
    echo "Processing ID {$app->id} - {$app->name}\n";

    // Migrate single file when source_path points to external project
    if (! empty($app->source_path) && str_starts_with($app->source_path, DIRECTORY_SEPARATOR) && file_exists($app->source_path)) {
        $src = $app->source_path;
        if (str_starts_with($src, $srcBase)) {
            $rel = ltrim(str_replace($srcBase, '', $src), DIRECTORY_SEPARATOR);
            $targetRel = 'applications/files/' . $rel;
        } else {
            $targetRel = 'applications/files/' . basename($src);
        }

        $targetFull = $disk->path($targetRel);
        File::ensureDirectoryExists(dirname($targetFull));

        if (! file_exists($targetFull)) {
            if (! copy($src, $targetFull)) {
                $summary['errors'][] = "Failed to copy $src to $targetFull";
                echo "  ERROR copying file\n";
            } else {
                @chmod($targetFull, 0644);
                echo "  copied to $targetFull\n";
            }
        } else {
            echo "  target already exists, skipping copy\n";
        }

        $app->file_path = $targetRel;
        $app->source_path = null;
        try {
            $app->file_size = (int) $disk->size($targetRel);
        } catch (Throwable $e) {
            // ignore size errors
        }
        $changed = true;
    }

    // If file_path is absolute (points to external project), copy it
    if (! $changed && ! empty($app->file_path) && str_starts_with($app->file_path, DIRECTORY_SEPARATOR) && file_exists($app->file_path)) {
        $src = $app->file_path;
        if (str_starts_with($src, $srcBase)) {
            $rel = ltrim(str_replace($srcBase, '', $src), DIRECTORY_SEPARATOR);
            $targetRel = 'applications/files/' . $rel;
        } else {
            $targetRel = 'applications/files/' . basename($src);
        }

        $targetFull = $disk->path($targetRel);
        File::ensureDirectoryExists(dirname($targetFull));

        if (! file_exists($targetFull)) {
            if (! copy($src, $targetFull)) {
                $summary['errors'][] = "Failed to copy $src to $targetFull";
                echo "  ERROR copying file_path\n";
            } else {
                @chmod($targetFull, 0644);
                echo "  copied to $targetFull\n";
            }
        } else {
            echo "  target already exists, skipping copy\n";
        }

        $app->file_path = $targetRel;
        try {
            $app->file_size = (int) $disk->size($targetRel);
        } catch (Throwable $e) {}
        $changed = true;
    }

    // Handle bundles
    if ($app->is_bundle) {
        $bundle = $app->bundle_files ?? [];
        $updated = false;
        foreach ($bundle as $idx => $bf) {
            $path = $bf['path'] ?? '';
            if (empty($path)) continue;

            // absolute path from external project
            if (str_starts_with($path, $srcBase) && file_exists($path)) {
                $rel = ltrim(str_replace($srcBase, '', $path), DIRECTORY_SEPARATOR);
                $targetRel = 'applications/files/' . $rel;
                $targetFull = $disk->path($targetRel);
                File::ensureDirectoryExists(dirname($targetFull));
                if (! file_exists($targetFull)) {
                    if (! copy($path, $targetFull)) {
                        $summary['errors'][] = "Failed to copy bundle $path to $targetFull";
                        echo "  ERROR copying bundle file\n";
                        continue;
                    } else {
                        @chmod($targetFull, 0644);
                    }
                }
                $bundle[$idx]['path'] = $targetRel;
                $updated = true;
            } elseif (str_starts_with($path, DIRECTORY_SEPARATOR) && file_exists($path)) {
                // other absolute path
                $targetRel = 'applications/files/' . basename($path);
                $targetFull = $disk->path($targetRel);
                File::ensureDirectoryExists(dirname($targetFull));
                if (! file_exists($targetFull)) {
                    if (! copy($path, $targetFull)) {
                        $summary['errors'][] = "Failed to copy bundle $path to $targetFull";
                        echo "  ERROR copying bundle file absolute\n";
                        continue;
                    } else {
                        @chmod($targetFull, 0644);
                    }
                }
                $bundle[$idx]['path'] = $targetRel;
                $updated = true;
            }
        }

        if ($updated) {
            $app->bundle_files = $bundle;
            // recompute total size
            $total = 0;
            foreach ($bundle as $bf) {
                $p = $bf['path'] ?? '';
                if ($p && $disk->exists($p)) {
                    $total += (int) $disk->size($p);
                }
            }
            $app->file_size = $total;
            $changed = true;
        }
    }

    // Handle images pointing to external project
    if (! empty($app->image_path) && str_starts_with($app->image_path, DIRECTORY_SEPARATOR) && file_exists($app->image_path)) {
        $src = $app->image_path;
        if (str_starts_with($src, $imgBase)) {
            $basename = basename($src);
        } else {
            $basename = basename($src);
        }
        $targetPublic = public_path('applications/images/' . $basename);
        File::ensureDirectoryExists(dirname($targetPublic));
        if (! file_exists($targetPublic)) {
            if (! copy($src, $targetPublic)) {
                $summary['errors'][] = "Failed to copy image $src to $targetPublic";
                echo "  ERROR copying image\n";
            } else {
                @chmod($targetPublic, 0644);
            }
        }
        $app->image_path = 'applications/images/' . $basename;
        $changed = true;
    }

    if ($changed) {
        $app->save();
        $summary['migrated']++;
        echo "  DB updated and saved.\n";
    } else {
        $summary['skipped']++;
        echo "  nothing to do, skipped.\n";
    }

    echo "---\n";
}

echo "Migration complete. Migrated: {$summary['migrated']}, Skipped: {$summary['skipped']}.\n";
if (! empty($summary['errors'])) {
    echo "Errors:\n" . implode("\n", $summary['errors']) . "\n";
}

echo "Done.\n";
