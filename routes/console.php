<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('env:use {profile : local, main, atau production} {--cache : Rebuild config cache setelah switch}', function (string $profile): int {
    $profile = strtolower(trim($profile));
    $aliases = [
        'dev' => 'local',
        'development' => 'local',
        'prod' => 'production',
        'live' => 'production',
    ];
    $profile = $aliases[$profile] ?? $profile;

    if (! in_array($profile, ['local', 'main', 'production'], true)) {
        $this->error('Profile env tidak dikenal. Pakai: local, main, atau production.');

        return Command::FAILURE;
    }

    $source = base_path('.env.' . $profile);
    $target = base_path('.env');
    if (! is_file($source)) {
        $this->error('File sumber tidak ditemukan: .env.' . $profile);

        return Command::FAILURE;
    }

    if (! copy($source, $target)) {
        $this->error('Gagal menyalin .env.' . $profile . ' ke .env.');

        return Command::FAILURE;
    }

    $this->info('Aktifkan env: ' . $profile);
    $this->line('Sumber: .env.' . $profile . ' -> .env');

    Artisan::call('optimize:clear');
    $this->line(trim(Artisan::output()));

    if ($this->option('cache')) {
        Artisan::call('config:cache');
        $this->line(trim(Artisan::output()));
    }

    $readEnv = static function (string $key) use ($target): string {
        foreach (file($target, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || ! str_starts_with($line, $key . '=')) {
                continue;
            }

            return trim(substr($line, strlen($key) + 1), "\"'");
        }

        return '';
    };

    $this->line('APP_ENV=' . $readEnv('APP_ENV'));
    $this->line('DB_DATABASE=' . $readEnv('DB_DATABASE'));

    return Command::SUCCESS;
})->purpose('Switch active Laravel .env file and clear cached config');

Artisan::command('materials:audit-files', function (): int {
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
        if ($path === '' || ! is_upload_path($path)) {
            $invalid[] = [
                (string) $file->menu_key,
                (string) $file->public_id,
                (string) $file->title,
                (string) $file->relative_path,
            ];
            continue;
        }

        if (resolve_relative_upload_path($path) === null) {
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
        $folderPath = trim(str_replace('\\', '/', (string) $menu->folder_path), '/');
        if ($folderPath === '') {
            continue;
        }

        $relativeFolder = 'uploads/files/' . $folderPath;
        $runtimeFolder = rec_runtime_path($relativeFolder);
        $publicFolder = rec_public_path($relativeFolder);
        $storagePublicFolder = storage_path('app/public/' . $relativeFolder);
        $baseFolder = base_path($relativeFolder);
        $fullFolder = is_dir($runtimeFolder)
            ? $runtimeFolder
            : (is_dir($publicFolder)
                ? $publicFolder
                : (is_dir($storagePublicFolder)
                    ? $storagePublicFolder
                    : (is_dir($baseFolder) ? $baseFolder : '')));

        if ($fullFolder === '' || ! is_dir($fullFolder)) {
            continue;
        }

        $physicalFiles = glob(rtrim(str_replace('\\', '/', $fullFolder), '/') . '/*') ?: [];
        sort($physicalFiles, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($physicalFiles as $fullPath) {
            if (! is_file($fullPath)) {
                continue;
            }

            $relativePath = sanitize_relative_upload_path($relativeFolder . '/' . basename($fullPath));
            if ($relativePath === '' || DB::table('public_material_files')->where('relative_path', $relativePath)->exists()) {
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

Artisan::command('materials:sync-files', function (): int {
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
        $folderPath = trim(str_replace('\\', '/', (string) $menu->folder_path), '/');
        if ($folderPath === '') {
            $skipped++;
            continue;
        }

        $relativeFolder = 'uploads/files/' . $folderPath;
        $fullFolder = resolve_relative_upload_path($relativeFolder . '/.keep');
        if ($fullFolder !== null) {
            $fullFolder = dirname($fullFolder);
        } else {
            $runtimeFolder = rec_runtime_path($relativeFolder);
            $publicFolder = rec_public_path($relativeFolder);
            $storagePublicFolder = storage_path('app/public/' . $relativeFolder);
            $baseFolder = base_path($relativeFolder);
            $fullFolder = is_dir($runtimeFolder)
                ? $runtimeFolder
                : (is_dir($publicFolder)
                    ? $publicFolder
                    : (is_dir($storagePublicFolder)
                        ? $storagePublicFolder
                        : (is_dir($baseFolder) ? $baseFolder : '')));
        }

        if ($fullFolder === '' || ! is_dir($fullFolder)) {
            $skipped++;
            continue;
        }

        $files = glob(rtrim(str_replace('\\', '/', $fullFolder), '/') . '/*') ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        $nextSortOrder = ((int) DB::table('public_material_files')
            ->where('public_material_menu_id', $menu->id)
            ->max('sort_order')) + 1;

        foreach ($files as $fullPath) {
            if (! is_file($fullPath)) {
                continue;
            }

            $relativePath = $relativeFolder . '/' . basename($fullPath);
            $relativePath = sanitize_relative_upload_path($relativePath);
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
