<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Application extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'file_name',
        'file_extension',
        'file_size',
        'file_path',
        'image_path',
        'is_bundle',
        'bundle_files',
        'source_path',
        'is_active',
        'downloads_count',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_bundle' => 'boolean',
            'bundle_files' => 'array',
            'is_active' => 'boolean',
            'downloads_count' => 'integer',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset($this->image_path) : null;
    }

    public function fileExists(): bool
    {
        $disk = Storage::disk('local');

        if ($this->is_bundle) {
            return collect($this->bundle_files ?? [])->every(function ($file) use ($disk) {
                $path = $file['path'] ?? '';
                // If path is an absolute filesystem path, check directly
                if ($path && str_starts_with($path, DIRECTORY_SEPARATOR) && file_exists($path)) {
                    return true;
                }

                if ($path && $disk->exists($path)) {
                    return true;
                }

                $basename = basename((string) $path);
                if ($basename) {
                    $candidates = [
                        "applications/files/{$basename}",
                        "applications/files/manual/{$this->slug}/{$basename}",
                    ];

                    foreach ($candidates as $c) {
                        if ($disk->exists($c)) {
                            return true;
                        }
                    }
                }

                $storagePrefix = rtrim(storage_path('app/private'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if ($path && str_starts_with($path, $storagePrefix)) {
                    $relative = ltrim(str_replace($storagePrefix, '', $path), DIRECTORY_SEPARATOR);
                    if ($disk->exists($relative)) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $this->resolvedFilePath() !== null;
    }

    public function resolvedFilePath(): ?string
    {
        $disk = Storage::disk('local');

        // Prefer an explicit absolute source path if present and accessible
        if (! empty($this->source_path) && str_starts_with($this->source_path, DIRECTORY_SEPARATOR) && file_exists($this->source_path)) {
            return $this->source_path;
        }

        $path = $this->file_path ?? '';
        if ($path && $disk->exists($path)) {
            return $path;
        }

        $basename = basename((string) $path);
        if ($basename) {
            $candidates = [
                "applications/files/{$basename}",
                "applications/files/manual/{$this->slug}/{$basename}",
            ];

            foreach ($candidates as $c) {
                if ($disk->exists($c)) {
                    return $c;
                }
            }
        }

        $storagePrefix = rtrim(storage_path('app/private'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($path && str_starts_with($path, $storagePrefix)) {
            $relative = ltrim(str_replace($storagePrefix, '', $path), DIRECTORY_SEPARATOR);
            if ($disk->exists($relative)) {
                return $relative;
            }
        }

        return null;
    }

    public function downloadLabel(): string
    {
        return $this->is_bundle ? 'Baixar kit' : 'Baixar';
    }

    public function displaySize(): string
    {
        $bytes = (int) $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return number_format($bytes, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
    }
}
