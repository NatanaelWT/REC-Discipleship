<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * @return array<int, string>
 */
$materialFolderCandidates = static function (string $folderPath): array {
    if (! is_valid_public_material_folder_path($folderPath)) {
        return [];
    }

    $folderPath = public_material_menu_folder_path($folderPath);
    $legacyFolder = public_material_legacy_folder_relative_path($folderPath);

    return [
        public_material_folder_full_path($folderPath),
        rec_runtime_path($legacyFolder),
        rec_public_path($legacyFolder),
        storage_path('app/public/' . $legacyFolder),
        base_path($legacyFolder),
    ];
};

$firstExistingMaterialFolder = static function (string $folderPath) use ($materialFolderCandidates): string {
    $seen = [];
    foreach ($materialFolderCandidates($folderPath) as $folder) {
        $folder = rtrim(str_replace('\\', '/', (string) $folder), '/');
        if ($folder === '' || isset($seen[$folder])) {
            continue;
        }
        $seen[$folder] = true;

        if (is_dir($folder)) {
            return $folder;
        }
    }

    return '';
};

/**
 * @return array<int, string>
 */
$materialPhysicalFiles = static function (string $folderPath) use ($firstExistingMaterialFolder): array {
    $folder = $firstExistingMaterialFolder($folderPath);
    if ($folder === '') {
        return [];
    }

    $files = glob(rtrim(str_replace('\\', '/', $folder), '/') . '/*') ?: [];
    $files = array_values(array_filter($files, 'is_file'));
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    return $files;
};

Artisan::command('materials:audit-files', function () use ($materialPhysicalFiles): int {
    \App\Support\RuntimeBootstrap::load();

    if (! Schema::hasTable('public_material_files')) {
        $this->error('Tabel public_material_files tidak ditemukan.');

        return Command::FAILURE;
    }

    $files = DB::table('public_material_files')
        ->leftJoin('public_material_menus', 'public_material_menus.id', '=', 'public_material_files.public_material_menu_id')
        ->select([
            'public_material_files.public_id',
            'public_material_files.title',
            'public_material_files.relative_path',
            'public_material_menus.menu_key',
        ])
        ->orderBy('public_material_menus.menu_key')
        ->orderBy('public_material_files.sort_order')
        ->orderBy('public_material_files.title')
        ->get();

    $missing = [];
    $invalid = [];
    $unregistered = [];
    foreach ($files as $file) {
        $path = sanitize_relative_upload_path((string) $file->relative_path);
        if ($path === '' || ! is_public_material_path($path)) {
            $invalid[] = [
                (string) $file->menu_key,
                (string) $file->public_id,
                (string) $file->title,
                (string) $file->relative_path,
            ];
            continue;
        }

        if (public_material_resolve_path($path) === null) {
            $missing[] = [
                (string) $file->menu_key,
                (string) $file->public_id,
                (string) $file->title,
                $path,
            ];
        }
    }

    $menus = DB::table('public_material_menus')
        ->orderBy('sort_order')
        ->orderBy('menu_key')
        ->get();

    foreach ($menus as $menu) {
        $folderPath = (string) $menu->folder_path;
        if (! is_valid_public_material_folder_path($folderPath)) {
            continue;
        }

        $folderPath = public_material_menu_folder_path($folderPath);
        $physicalFiles = $materialPhysicalFiles($folderPath);

        foreach ($physicalFiles as $fullPath) {
            if (! is_file($fullPath)) {
                continue;
            }

            $relativePath = public_material_file_relative_path($folderPath, basename($fullPath));
            $legacyRelativePath = sanitize_relative_upload_path(public_material_legacy_folder_relative_path($folderPath) . '/' . basename($fullPath));
            if ($relativePath === '' || DB::table('public_material_files')->whereIn('relative_path', array_filter([$relativePath, $legacyRelativePath]))->exists()) {
                continue;
            }

            $unregistered[] = [
                (string) $menu->menu_key,
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
})->purpose('Audit public material file records against uploaded files');

Artisan::command('materials:sync-files', function () use ($materialPhysicalFiles): int {
    \App\Support\RuntimeBootstrap::load();

    if (! Schema::hasTable('public_material_files') || ! Schema::hasTable('public_material_menus')) {
        $this->error('Tabel public_material_menus/public_material_files tidak ditemukan.');

        return Command::FAILURE;
    }

    $inserted = [];
    $skipped = 0;
    $menus = DB::table('public_material_menus')
        ->orderBy('sort_order')
        ->orderBy('menu_key')
        ->get();

    foreach ($menus as $menu) {
        $folderPath = (string) $menu->folder_path;
        if (! is_valid_public_material_folder_path($folderPath)) {
            $skipped++;
            continue;
        }

        $folderPath = public_material_menu_folder_path($folderPath);
        $files = $materialPhysicalFiles($folderPath);
        if (count($files) === 0) {
            $skipped++;
            continue;
        }

        $nextSortOrder = ((int) DB::table('public_material_files')
            ->where('public_material_menu_id', $menu->id)
            ->max('sort_order')) + 1;

        foreach ($files as $fullPath) {
            if (! is_file($fullPath)) {
                continue;
            }

            $relativePath = public_material_file_relative_path($folderPath, basename($fullPath));
            $legacyRelativePath = sanitize_relative_upload_path(public_material_legacy_folder_relative_path($folderPath) . '/' . basename($fullPath));
            if ($relativePath === '' || DB::table('public_material_files')->whereIn('relative_path', array_filter([$relativePath, $legacyRelativePath]))->exists()) {
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
                'public_material_menu_id' => $menu->id,
                'public_id' => $publicId,
                'title' => $title,
                'category_name' => null,
                'description' => null,
                'relative_path' => $relativePath,
                'original_file_name' => basename($fullPath),
                'size_bytes' => max(0, (int) @filesize($fullPath)),
                'mime_type' => detect_file_mime_type($fullPath),
                'branch_id' => null,
                'branch_code' => null,
                'sort_order' => $nextSortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inserted[] = [
                (string) $menu->menu_key,
                $publicId,
                $title,
                $relativePath,
            ];
            $nextSortOrder++;
        }
    }

    $this->info('Menu dilewati karena folder tidak ditemukan/kosong: ' . (string) $skipped);
    $this->info('File baru ditambahkan: ' . (string) count($inserted));

    if (count($inserted) > 0) {
        $this->table(['Menu', 'Public ID', 'Judul', 'Path'], $inserted);
    }

    return Command::SUCCESS;
})->purpose('Sync public material records from uploaded files');

Artisan::command('materials:publish-files {--force : Overwrite existing public files when file sizes differ}', function (): int {
    \App\Support\RuntimeBootstrap::load();

    $sourceRoot = rtrim(str_replace('\\', '/', rec_runtime_path(public_material_legacy_base_relative_path())), '/');
    $targetRoot = rtrim(str_replace('\\', '/', public_material_folder_full_path()), '/');
    if ($sourceRoot === $targetRoot) {
        $this->error('Folder sumber dan tujuan sama. Pastikan target storage publik benar.');

        return Command::FAILURE;
    }

    if (! is_dir($sourceRoot)) {
        $this->error('Folder sumber tidak ditemukan: ' . $sourceRoot);

        return Command::FAILURE;
    }

    File::ensureDirectoryExists($targetRoot);

    $copied = 0;
    $overwritten = 0;
    $skippedSame = 0;
    $conflicts = [];
    $failed = [];
    $force = (bool) $this->option('force');
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::LEAVES_ONLY,
    );

    foreach ($iterator as $fileInfo) {
        if (! $fileInfo->isFile()) {
            continue;
        }

        $sourcePath = str_replace('\\', '/', $fileInfo->getPathname());
        $relativePath = ltrim(substr($sourcePath, strlen($sourceRoot)), '/');
        if ($relativePath === '') {
            continue;
        }

        $targetPath = $targetRoot . '/' . $relativePath;
        File::ensureDirectoryExists(dirname($targetPath));

        if (is_file($targetPath)) {
            $sourceSize = max(0, (int) @filesize($sourcePath));
            $targetSize = max(0, (int) @filesize($targetPath));
            if ($sourceSize === $targetSize) {
                $skippedSame++;
                continue;
            }

            if (! $force) {
                $conflicts[] = [$relativePath, (string) $sourceSize, (string) $targetSize];
                continue;
            }

            if (! copy($sourcePath, $targetPath)) {
                $failed[] = [$relativePath, 'Gagal overwrite'];
                continue;
            }

            $overwritten++;
            continue;
        }

        if (! copy($sourcePath, $targetPath)) {
            $failed[] = [$relativePath, 'Gagal copy'];
            continue;
        }

        $copied++;
    }

    $this->info('Publish file materi selesai.');
    $this->line('Sumber: ' . $sourceRoot);
    $this->line('Tujuan: ' . $targetRoot);
    $this->line('File disalin: ' . (string) $copied);
    $this->line('File overwrite: ' . (string) $overwritten);
    $this->line('File dilewati karena sudah sama: ' . (string) $skippedSame);
    $this->line('File konflik ukuran: ' . (string) count($conflicts));
    $this->line('File gagal: ' . (string) count($failed));

    if (count($conflicts) > 0) {
        $this->warn('File tujuan sudah ada dengan ukuran berbeda. Jalankan ulang dengan --force jika memang ingin overwrite.');
        $this->table(['Path', 'Size lama', 'Size public'], $conflicts);
    }

    if (count($failed) > 0) {
        $this->error('Ada file yang gagal disalin.');
        $this->table(['Path', 'Error'], $failed);
    }

    $this->line('');
    $this->line('Menjalankan audit setelah publish...');
    $auditCode = Artisan::call('materials:audit-files');
    $auditOutput = trim(Artisan::output());
    if ($auditOutput !== '') {
        $this->line($auditOutput);
    }

    if ($auditCode !== Command::SUCCESS || count($conflicts) > 0 || count($failed) > 0) {
        return Command::FAILURE;
    }

    return Command::SUCCESS;
})->purpose('Copy public material files from private runtime storage to storage/app/public/msk-dg');
