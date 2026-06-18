<?php

use App\Enums\PublicMaterialMenuKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * @return array<int, string>
 */
$materialPhysicalFiles = static function (PublicMaterialMenuKey $menu): array {
    $folder = rtrim(str_replace('\\', '/', public_material_folder_full_path($menu->folder())), '/');
    if ($folder === '' || ! is_dir($folder)) {
        return [];
    }

    $files = glob($folder . '/*') ?: [];
    $files = array_values(array_filter($files, 'is_file'));
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    return $files;
};

$materialPathBelongsToMenu = static function (PublicMaterialMenuKey $menu, string $path): bool {
    $path = public_material_current_relative_path($path);
    if ($path === '') {
        return false;
    }

    $menuFolder = public_material_folder_relative_path($menu->folder());

    return $path !== $menuFolder && str_starts_with($path, $menuFolder . '/');
};

Artisan::command('materials:audit-files', function () use ($materialPhysicalFiles, $materialPathBelongsToMenu): int {
    \App\Support\RuntimeBootstrap::load();

    if (! Schema::hasTable('public_material_files')) {
        $this->error('Tabel public_material_files tidak ditemukan.');

        return Command::FAILURE;
    }

    $files = DB::table('public_material_files')
        ->select(['menu', 'public_id', 'title', 'relative_path'])
        ->orderBy('menu')
        ->orderBy('title')
        ->get();

    $missing = [];
    $invalid = [];
    $unregistered = [];
    foreach ($files as $file) {
        $menu = PublicMaterialMenuKey::fromKey((string) ($file->menu ?? ''));
        $path = sanitize_relative_upload_path((string) $file->relative_path);
        if (! $menu instanceof PublicMaterialMenuKey || $path === '' || ! $materialPathBelongsToMenu($menu, $path)) {
            $invalid[] = [
                (string) ($file->menu ?? ''),
                (string) $file->public_id,
                (string) $file->title,
                (string) $file->relative_path,
            ];
            continue;
        }

        if (public_material_resolve_path($path) === null) {
            $missing[] = [
                $menu->value,
                (string) $file->public_id,
                (string) $file->title,
                $path,
            ];
        }
    }

    $registeredPaths = DB::table('public_material_files')
        ->pluck('relative_path')
        ->map(static fn ($path): string => sanitize_relative_upload_path((string) $path))
        ->filter()
        ->flip();

    foreach (PublicMaterialMenuKey::cases() as $menu) {
        foreach ($materialPhysicalFiles($menu) as $fullPath) {
            if (! is_file($fullPath)) {
                continue;
            }

            $relativePath = public_material_file_relative_path($menu->folder(), basename($fullPath));
            if ($relativePath === '' || $registeredPaths->has($relativePath)) {
                continue;
            }

            $unregistered[] = [
                $menu->value,
                basename($fullPath),
                $relativePath,
            ];
        }
    }

    $this->info('Total file materi: ' . (string) $files->count());
    $this->info('Path invalid: ' . (string) count($invalid));
    $this->info('File fisik hilang: ' . (string) count($missing));
    $this->info('File fisik belum terdaftar: ' . (string) count($unregistered));

    if (count($invalid) > 0) {
        $this->warn('Path invalid:');
        $this->table(['Menu', 'Public ID', 'Judul', 'Path'], $invalid);

        return Command::FAILURE;
    }

    if (count($missing) > 0) {
        $this->warn('File fisik hilang:');
        $this->table(['Menu', 'Public ID', 'Judul', 'Path'], $missing);

        return Command::FAILURE;
    }

    if (count($unregistered) > 0) {
        $this->warn('File fisik belum terdaftar. Jalankan php artisan materials:sync-files untuk menambah record.');
        $this->table(['Menu', 'File', 'Path'], $unregistered);

        return Command::FAILURE;
    }

    $this->info('Semua file materi yang tercatat bisa ditemukan.');

    return Command::SUCCESS;
})->purpose('Audit public material file records against public material files');

Artisan::command('materials:sync-files', function () use ($materialPhysicalFiles): int {
    \App\Support\RuntimeBootstrap::load();

    if (! Schema::hasTable('public_material_files')) {
        $this->error('Tabel public_material_files tidak ditemukan.');

        return Command::FAILURE;
    }

    $inserted = [];
    $skipped = 0;

    foreach (PublicMaterialMenuKey::cases() as $menu) {
        $files = $materialPhysicalFiles($menu);
        if (count($files) === 0) {
            $skipped++;
            continue;
        }

        foreach ($files as $fullPath) {
            if (! is_file($fullPath)) {
                continue;
            }

            $relativePath = public_material_file_relative_path($menu->folder(), basename($fullPath));
            if ($relativePath === '' || DB::table('public_material_files')->where('relative_path', $relativePath)->exists()) {
                continue;
            }

            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $allowedExtensions = secure_file_allowed_extensions();
            if ($extension === '' || ! isset($allowedExtensions[$extension])) {
                continue;
            }

            $title = pathinfo($fullPath, PATHINFO_FILENAME);
            $title = preg_replace('/[_-]+/', ' ', $title) ?? $title;
            $title = trim((string) preg_replace('/\s+/', ' ', $title));
            if ($title === '') {
                $title = basename($fullPath);
            }

            $publicIdBase = 'church_file_' . substr(sha1($relativePath), 0, 8);
            $publicId = $publicIdBase;
            $suffix = 1;
            while (DB::table('public_material_files')->where('public_id', $publicId)->exists()) {
                $publicId = $publicIdBase . '_' . (string) $suffix;
                $suffix++;
            }

            DB::table('public_material_files')->insert([
                'menu' => $menu->value,
                'public_id' => $publicId,
                'title' => $title,
                'category_name' => null,
                'description' => null,
                'relative_path' => $relativePath,
                'original_file_name' => basename($fullPath),
                'size_bytes' => max(0, (int) @filesize($fullPath)),
                'mime_type' => detect_file_mime_type($fullPath),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted[] = [
                $menu->value,
                $publicId,
                $title,
                $relativePath,
            ];
        }
    }

    $this->info('Menu dilewati karena folder tidak ditemukan/kosong: ' . (string) $skipped);
    $this->info('File baru ditambahkan: ' . (string) count($inserted));

    if (count($inserted) > 0) {
        $this->table(['Menu', 'Public ID', 'Judul', 'Path'], $inserted);
    }

    return Command::SUCCESS;
})->purpose('Sync public material records from public material files');
