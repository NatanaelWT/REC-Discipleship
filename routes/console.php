<?php

use App\Enums\PublicMaterialMenuKey;
use App\Services\Branches\BranchCatalog;
use App\Services\Developer\DeveloperUserService;
use App\Services\DiscipleshipDashboard\DiscipleshipDashboardSummaryQuery;
use App\Services\PublicMaterials\PublicMaterialTextExtractor;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('discipleship:cache-warm', function (BranchCatalog $branches, DiscipleshipDashboardSummaryQuery $summary): int {
    RuntimeBootstrap::load();
    $branchIds = array_map(static fn (array $option): int => $option['id'], $branches->options());
    foreach ($branchIds as $branchId) {
        $summary->warm([$branchId]);
    }
    $summary->warm($branchIds);
    $this->info('Cache dashboard Pemuridan telah dipanaskan untuk '.count($branchIds).' cabang dan Semua Cabang.');

    return 0;
})->purpose('Warm cached dashboard summaries for all discipleship branch scopes');

Artisan::command('developer:ensure-user', function (DeveloperUserService $users): int {
    RuntimeBootstrap::load();

    $result = $users->ensureDeveloperUserFromEnvironment();
    if ($result['status'] === 'missing_password') {
        $this->error('DEVELOPER_PASSWORD belum diisi.');

        return Command::FAILURE;
    }

    $this->info('Developer user ensured: '.$result['username']);

    return Command::SUCCESS;
})->purpose('Create or update the developer superuser from DEVELOPER_* environment variables');

Artisan::command('identifiers:audit', function (): int {
    $references = [
        ['kelompok_dg', 'parent_group_id', 'parent_group_public_id', 'kelompok_dg'],
        ['kelompok_dg', 'source_group_id', 'source_group_public_id', 'kelompok_dg'],
        ['kelompok_dg', 'initiated_by_person_id', 'initiated_by_person_public_id', 'orang'],
        ['relasi_dg', 'mentor_person_id', 'mentor_person_public_id', 'orang'],
        ['relasi_dg', 'disciple_person_id', 'disciple_person_public_id', 'orang'],
        ['relasi_dg', 'context_group_id', 'context_group_public_id', 'kelompok_dg'],
        ['keanggotaan_kelompok_dg', 'discipleship_group_id', 'group_public_id', 'kelompok_dg'],
        ['keanggotaan_kelompok_dg', 'person_id', 'person_public_id', 'orang'],
        ['jurnal_temu_dg', 'leader_person_id', 'leader_person_public_id', 'orang'],
        ['jurnal_temu_dg', 'discipleship_group_id', 'discipleship_group_public_id', 'kelompok_dg'],
    ];

    $issues = [];
    foreach ($references as [$table, $foreignColumn, $legacyColumn, $targetTable]) {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $legacyColumn)
            || ! Schema::hasColumn($table, $foreignColumn)
            || ! Schema::hasTable($targetTable)
            || ! Schema::hasColumn($targetTable, 'public_id')) {
            continue;
        }

        $rows = DB::table($table.' as source')
            ->leftJoin($targetTable.' as target', static function ($join) use ($legacyColumn): void {
                $join->on('target.branch_id', '=', 'source.branch_id')
                    ->on('target.public_id', '=', 'source.'.$legacyColumn);
            })
            ->whereNotNull('source.'.$legacyColumn)
            ->where('source.'.$legacyColumn, '!=', '')
            ->where('source.'.$legacyColumn, '!=', 'virtual_injil')
            ->select([
                'source.id',
                'source.'.$foreignColumn.' as numeric_id',
                DB::raw('count(target.id) as match_count'),
                DB::raw('min(target.id) as resolved_id'),
            ])
            ->groupBy('source.id', 'source.'.$foreignColumn)
            ->get();

        foreach ($rows as $row) {
            if ((int) $row->match_count !== 1
                || ($row->numeric_id !== null && (int) $row->numeric_id !== (int) $row->resolved_id)) {
                $issues[] = [$table, (string) $row->id, $legacyColumn, (string) $row->match_count];
            }
        }
    }

    $numericReferences = [
        ['kelompok_dg', 'parent_group_id', 'kelompok_dg'],
        ['kelompok_dg', 'source_group_id', 'kelompok_dg'],
        ['kelompok_dg', 'initiated_by_person_id', 'orang'],
        ['relasi_dg', 'mentor_person_id', 'orang'],
        ['relasi_dg', 'disciple_person_id', 'orang'],
        ['relasi_dg', 'context_group_id', 'kelompok_dg'],
        ['keanggotaan_kelompok_dg', 'discipleship_group_id', 'kelompok_dg'],
        ['keanggotaan_kelompok_dg', 'person_id', 'orang'],
        ['jurnal_temu_dg', 'leader_person_id', 'orang'],
        ['jurnal_temu_dg', 'discipleship_group_id', 'kelompok_dg'],
        ['jurnal_umpan_balik', 'discipleship_group_id', 'kelompok_dg'],
        ['jurnal_umpan_balik', 'leader_person_id', 'orang'],
        ['jurnal_umpan_balik', 'respondent_person_id', 'orang'],
    ];

    foreach ($numericReferences as [$table, $foreignColumn, $targetTable]) {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'branch_id')
            || ! Schema::hasColumn($table, $foreignColumn)
            || ! Schema::hasTable($targetTable)
            || ! Schema::hasColumn($targetTable, 'branch_id')) {
            continue;
        }

        $invalidIds = DB::table($table.' as source')
            ->leftJoin($targetTable.' as target', 'target.id', '=', 'source.'.$foreignColumn)
            ->whereNotNull('source.'.$foreignColumn)
            ->where(static function ($query): void {
                $query->whereNull('target.id')
                    ->orWhereNull('source.branch_id')
                    ->orWhereColumn('target.branch_id', '!=', 'source.branch_id');
            })
            ->pluck('source.id');

        foreach ($invalidIds as $invalidId) {
            $issues[] = [$table, (string) $invalidId, $foreignColumn, 'invalid_numeric_reference'];
        }
    }

    if (Schema::hasTable('orang')
        && Schema::hasColumn('orang', 'member_public_id')
        && Schema::hasTable('orang')
        && Schema::hasColumn('orang', 'member_public_id')
        && Schema::hasColumn('orang', 'public_id')) {
        $peopleByLegacyId = [];
        foreach (DB::table('orang')
            ->select(['id', 'branch_id', 'public_id', 'member_public_id', 'full_name', 'whatsapp', 'status'])
            ->get() as $person) {
            foreach (array_unique([(string) $person->public_id, (string) $person->member_public_id]) as $legacyId) {
                $legacyId = trim($legacyId);
                if ($legacyId !== '') {
                    $peopleByLegacyId[(int) $person->branch_id.'|'.$legacyId][(int) $person->id] = $person;
                }
            }
        }

        $claimedPeople = [];
        foreach (DB::table('orang')
            ->select(['id', 'branch_id', 'member_public_id', 'full_name', 'whatsapp'])
            ->orderBy('id')
            ->get() as $participant) {
            $legacyId = trim((string) $participant->member_public_id);
            if ($legacyId === '') {
                continue;
            }

            $matches = array_values($peopleByLegacyId[(int) $participant->branch_id.'|'.$legacyId] ?? []);
            if (count($matches) > 1) {
                $participantPhone = trim((string) $participant->whatsapp);
                $identityMatches = array_values(array_filter(
                    $matches,
                    static fn (object $person): bool => (string) $person->full_name === (string) $participant->full_name
                        && ($participantPhone === '' || (string) $person->whatsapp === $participantPhone),
                ));
                if ($identityMatches !== []) {
                    $matches = $identityMatches;
                }
            }

            if (count($matches) > 1) {
                $activeMatches = array_values(array_filter(
                    $matches,
                    static fn (object $person): bool => (string) $person->status === 'active',
                ));
                if (count($activeMatches) === 1) {
                    $matches = $activeMatches;
                }
            }

            if ($matches === []) {
                continue;
            }

            if (count($matches) !== 1) {
                $issues[] = ['orang', (string) $participant->id, 'member_public_id', 'ambiguous_person_match'];

                continue;
            }

            $personId = (int) $matches[0]->id;
            if (isset($claimedPeople[$personId])) {
                $issues[] = ['orang', (string) $participant->id, 'member_public_id', 'duplicate_person_link'];

                continue;
            }

            $claimedPeople[$personId] = true;
        }
    }

    if (Schema::hasTable('jurnal_temu_dg')
        && Schema::hasColumn('jurnal_temu_dg', 'absences')
        && Schema::hasColumn('jurnal_temu_dg', 'meditation_sharers')) {
        foreach (DB::table('jurnal_temu_dg')->select(['id', 'absences', 'meditation_sharers'])->get() as $report) {
            foreach (['absences', 'meditation_sharers'] as $column) {
                $items = json_decode((string) ($report->{$column} ?? '[]'), true);
                foreach (is_array($items) ? $items : [] as $item) {
                    if (is_array($item) && (int) ($item['person_id'] ?? 0) < 1) {
                        $issues[] = ['jurnal_temu_dg', (string) $report->id, $column, 'missing_person_id'];
                    }
                }
            }
        }
    }

    if ($issues !== []) {
        $this->error('Audit identifier menemukan '.count($issues).' masalah.');
        $this->table(['Tabel', 'Row/ID', 'Referensi', 'Masalah'], $issues);

        return Command::FAILURE;
    }

    $this->info('Audit identifier selesai tanpa masalah.');

    return Command::SUCCESS;
})->purpose('Audit legacy public identifiers before the numeric identifier migration');

/**
 * @return array<int, string>
 */
$materialPhysicalFiles = static function (PublicMaterialMenuKey $menu): array {
    $folder = rtrim(str_replace('\\', '/', public_material_folder_full_path($menu->folder())), '/');
    if ($folder === '' || ! is_dir($folder)) {
        return [];
    }

    $files = glob($folder.'/*') ?: [];
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

    return $path !== $menuFolder && str_starts_with($path, $menuFolder.'/');
};

Artisan::command('materials:audit-files', function () use ($materialPhysicalFiles, $materialPathBelongsToMenu): int {
    RuntimeBootstrap::load();

    if (! Schema::hasTable('materi_publik')) {
        $this->error('Tabel materi_publik tidak ditemukan.');

        return Command::FAILURE;
    }

    $files = DB::table('materi_publik')
        ->select(['id', 'menu', 'title', 'relative_path'])
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
                (string) $file->id,
                (string) $file->title,
                (string) $file->relative_path,
            ];

            continue;
        }

        if (public_material_resolve_path($path) === null) {
            $missing[] = [
                $menu->value,
                (string) $file->id,
                (string) $file->title,
                $path,
            ];
        }
    }

    $registeredPaths = DB::table('materi_publik')
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

    $this->info('Total file materi: '.(string) $files->count());
    $this->info('Path invalid: '.(string) count($invalid));
    $this->info('File fisik hilang: '.(string) count($missing));
    $this->info('File fisik belum terdaftar: '.(string) count($unregistered));

    if (count($invalid) > 0) {
        $this->warn('Path invalid:');
        $this->table(['Menu', 'ID', 'Judul', 'Path'], $invalid);

        return Command::FAILURE;
    }

    if (count($missing) > 0) {
        $this->warn('File fisik hilang:');
        $this->table(['Menu', 'ID', 'Judul', 'Path'], $missing);

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

Artisan::command('materials:sync-files', function (PublicMaterialTextExtractor $textExtractor) use ($materialPhysicalFiles): int {
    RuntimeBootstrap::load();

    if (! Schema::hasTable('materi_publik')) {
        $this->error('Tabel materi_publik tidak ditemukan.');

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
            if ($relativePath === '' || DB::table('materi_publik')->where('relative_path', $relativePath)->exists()) {
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

            $fileId = DB::table('materi_publik')->insertGetId(array_merge([
                'menu' => $menu->value,
                'title' => $title,
                'category_name' => null,
                'description' => null,
                'relative_path' => $relativePath,
                'original_file_name' => basename($fullPath),
                'size_bytes' => max(0, (int) @filesize($fullPath)),
                'mime_type' => detect_file_mime_type($fullPath),
                'created_at' => now(),
                'updated_at' => now(),
            ], $textExtractor->extractForStorage($menu, $fullPath)));

            $inserted[] = [
                $menu->value,
                (string) $fileId,
                $title,
                $relativePath,
            ];
        }
    }

    $this->info('Menu dilewati karena folder tidak ditemukan/kosong: '.(string) $skipped);
    $this->info('File baru ditambahkan: '.(string) count($inserted));

    if (count($inserted) > 0) {
        $this->table(['Menu', 'Public ID', 'Judul', 'Path'], $inserted);
    }

    return Command::SUCCESS;
})->purpose('Sync public material records from public material files');

Artisan::command('materials:extract-text {--menu= : Public material menu key. Empty means all DG menus} {--force : Re-extract files that already have text}', function (PublicMaterialTextExtractor $textExtractor): int {
    RuntimeBootstrap::load();

    if (! Schema::hasTable('materi_publik')) {
        $this->error('Tabel materi_publik tidak ditemukan.');

        return Command::FAILURE;
    }

    foreach (['text_content', 'text_extracted_at', 'text_extraction_error'] as $column) {
        if (! Schema::hasColumn('materi_publik', $column)) {
            $this->error('Kolom teks materi belum tersedia. Jalankan migrasi terlebih dahulu.');

            return Command::FAILURE;
        }
    }

    $menuOption = trim((string) $this->option('menu'));
    $menus = [];
    if ($menuOption === '') {
        $menus = array_values(array_filter(
            PublicMaterialMenuKey::cases(),
            static fn (PublicMaterialMenuKey $menu): bool => $menu->isDgSessionMenu(),
        ));
    } else {
        $menu = PublicMaterialMenuKey::fromKey($menuOption);
        if (! $menu instanceof PublicMaterialMenuKey) {
            $this->error('Menu materi tidak valid.');

            return Command::FAILURE;
        }

        if (! $menu->isDgSessionMenu()) {
            $this->error('Ekstraksi teks saat ini hanya tersedia untuk materi DG-1, DG-2, dan DG-3.');

            return Command::FAILURE;
        }

        $menus = [$menu];
    }

    $force = (bool) $this->option('force');
    $extracted = [];
    $failed = [];
    $skipped = 0;

    foreach ($menus as $menu) {
        $rows = DB::table('materi_publik')
            ->select(['id', 'menu', 'title', 'relative_path', 'text_content'])
            ->where('menu', $menu->value)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $path = sanitize_relative_upload_path((string) ($row->relative_path ?? ''));
            if ($path === '' || secure_file_extension($path) !== 'pdf') {
                $skipped++;

                continue;
            }

            if (! $force && trim((string) ($row->text_content ?? '')) !== '') {
                $skipped++;

                continue;
            }

            $fullPath = public_material_resolve_path($path);
            if ($fullPath === null) {
                DB::table('materi_publik')
                    ->where('id', $row->id)
                    ->update([
                        'text_content' => null,
                        'text_extracted_at' => now(),
                        'text_extraction_error' => 'File PDF tidak ditemukan.',
                        'updated_at' => now(),
                    ]);
                $failed[] = [$menu->value, (string) $row->id, (string) ($row->title ?? ''), 'File PDF tidak ditemukan.'];

                continue;
            }

            $payload = $textExtractor->extractForStorage($menu, $fullPath);
            if ($payload === []) {
                $skipped++;

                continue;
            }

            DB::table('materi_publik')
                ->where('id', $row->id)
                ->update(array_merge($payload, ['updated_at' => now()]));

            $error = trim((string) ($payload['text_extraction_error'] ?? ''));
            if ($error !== '') {
                $failed[] = [$menu->value, (string) $row->id, (string) ($row->title ?? ''), $error];

                continue;
            }

            $extracted[] = [$menu->value, (string) $row->id, (string) ($row->title ?? ''), $path];
        }
    }

    $this->info('File teks berhasil diekstrak: '.(string) count($extracted));
    $this->info('File gagal/teks kosong: '.(string) count($failed));
    $this->info('File dilewati: '.(string) $skipped);

    if (count($extracted) > 0) {
        $this->table(['Menu', 'ID', 'Judul', 'Path'], $extracted);
    }
    if (count($failed) > 0) {
        $this->warn('File dengan error ekstraksi:');
        $this->table(['Menu', 'ID', 'Judul', 'Error'], $failed);
    }

    return count($failed) > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Extract text content for DG public material PDFs');
