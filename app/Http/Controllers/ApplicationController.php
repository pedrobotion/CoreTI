<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ZipArchive;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));

        $query = Application::query()->where('is_active', true);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        $applications = $query
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        $categories = Application::query()
            ->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('applications.index', [
            'applications' => $applications,
            'categories' => $categories,
            'search' => $search,
            'category' => $category,
        ]);
    }

    public function create(): View
    {
        $categories = Application::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('applications.create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'image' => ['required', 'image'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file'],
        ]);

        $name = trim($data['name']);
        $category = filled($data['category'] ?? null) ? trim($data['category']) : 'Geral';
        $slug = $this->uniqueSlug($name);
        $files = $request->file('files');
        $isBundle = count($files) > 1;

        File::ensureDirectoryExists(public_path('applications/images'));
        Storage::disk('local')->makeDirectory("applications/files/manual/{$slug}");

        $image = $request->file('image');
        $imageName = "{$slug}.{$image->extension()}";
        $image->move(public_path('applications/images'), $imageName);

        $bundleFiles = [];
        $fileSize = 0;
        $singleFilePath = '';
        $singleFileName = '';
        $singleExtension = $isBundle ? 'zip' : null;

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $storedName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . strtolower($file->getClientOriginalExtension());
            $storedPath = "applications/files/manual/{$slug}/{$storedName}";

            File::ensureDirectoryExists(dirname(Storage::disk('local')->path($storedPath)));
            $file->move(dirname(Storage::disk('local')->path($storedPath)), basename($storedPath));

            $size = Storage::disk('local')->size($storedPath);
            $fileSize += $size;

            $bundleFiles[] = [
                'path' => $storedPath,
                'name' => $originalName,
            ];

            if (! $isBundle) {
                $singleFilePath = $storedPath;
                $singleFileName = $originalName;
                $singleExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            }
        }

        Application::create([
            'name' => $name,
            'slug' => $slug,
            'category' => $category,
            'file_name' => $isBundle ? "{$slug}.zip" : $singleFileName,
            'file_extension' => $singleExtension,
            'file_size' => $fileSize,
            'file_path' => $isBundle ? '' : $singleFilePath,
            'image_path' => "applications/images/{$imageName}",
            'is_bundle' => $isBundle,
            'bundle_files' => $isBundle ? $bundleFiles : null,
            'source_path' => null,
            'is_active' => true,
        ]);

        return redirect()->route('applications.index')
            ->with('success', 'Aplicativo cadastrado com sucesso.');
    }

    public function download(Application $application)
    {
        if (! $application->is_active || ! $application->fileExists()) {
            return redirect()->route('applications.index')
                ->with('error', 'Arquivo não encontrado no servidor. Reimporte ou recadastre o aplicativo.');
        }

        $application->increment('downloads_count');

        if ($application->is_bundle) {
            return $this->downloadBundle($application);
        }

        $path = $application->resolvedFilePath() ?? $application->file_path;

        // If the resolved path is an absolute filesystem path, serve it directly
        if (is_string($path) && str_starts_with($path, DIRECTORY_SEPARATOR) && file_exists($path)) {
            return response()->download($path, $application->file_name);
        }

        return Storage::disk('local')->download($path, $application->file_name);
    }

    private function downloadBundle(Application $application)
    {
        $zipName = "{$application->slug}.zip";
        $zipPath = storage_path("app/temp/{$zipName}");

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive();
        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);

        foreach ($application->bundle_files ?? [] as $file) {
            $path = $file['path'] ?? null;
            $name = $file['name'] ?? basename((string) $path);

            $resolved = null;
            if ($path && Storage::disk('local')->exists($path)) {
                $resolved = $path;
            } else {
                $basename = basename((string) $path);
                if ($basename) {
                    $candidates = [
                        "applications/files/{$basename}",
                        "applications/files/manual/{$application->slug}/{$basename}",
                    ];

                    foreach ($candidates as $c) {
                        if (Storage::disk('local')->exists($c)) {
                            $resolved = $c;
                            break;
                        }
                    }
                }

                $storagePrefix = rtrim(storage_path('app/private'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (! $resolved && $path && str_starts_with($path, $storagePrefix)) {
                    $relative = ltrim(str_replace($storagePrefix, '', $path), DIRECTORY_SEPARATOR);
                    if (Storage::disk('local')->exists($relative)) {
                        $resolved = $relative;
                    }
                }
            }

            if ($resolved) {
                if (str_starts_with((string) $resolved, DIRECTORY_SEPARATOR) && file_exists($resolved)) {
                    $zip->addFile($resolved, $name);
                } elseif (Storage::disk('local')->exists($resolved)) {
                    $zip->addFile(Storage::disk('local')->path($resolved), $name);
                }
            }
        }

        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (Application::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
