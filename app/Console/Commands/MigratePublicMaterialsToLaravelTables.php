<?php

namespace App\Console\Commands;

use App\Support\LegacyRuntimeBootstrap;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigratePublicMaterialsToLaravelTables extends Command
{
    protected $signature = 'rec:migrate-public-materials {--dry-run : Count rows without writing}';

    protected $description = 'Migrate church files and public material menus from rec_church_files to normalized Laravel tables.';

    public function handle(): int
    {
        LegacyRuntimeBootstrap::load();

        foreach (['rec_church_files', 'church_files', 'public_material_menus', 'public_material_menu_files'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table {$table} does not exist. Run migrations first.");

                return self::FAILURE;
            }
        }

        $legacyRows = DB::table('rec_church_files')->orderBy('id')->get();
        if ($this->option('dry-run')) {
            $publicCount = 0;
            foreach (array_keys(public_material_menu_options()) as $menuKey) {
                $publicCount += $this->legacyRowsForMenu($legacyRows->all(), $menuKey)->count();
            }

            $this->info('Church files ready to migrate: ' . $legacyRows->count());
            $this->info('Public material menu file links ready to migrate: ' . $publicCount);

            return self::SUCCESS;
        }

        $fileIdsByPublicId = [];
        DB::transaction(function () use ($legacyRows, &$fileIdsByPublicId): void {
            foreach ($legacyRows as $legacyRow) {
                $publicId = $this->publicId($legacyRow);
                $createdAt = $this->timestamp($legacyRow->uploaded_at_text ?? null)
                    ?? $this->timestamp($legacyRow->created_at ?? null)
                    ?? now();
                $updatedAt = $this->timestamp($legacyRow->updated_at_text ?? null)
                    ?? $this->timestamp($legacyRow->updated_at ?? null)
                    ?? $createdAt;

                DB::table('church_files')->updateOrInsert(
                    ['public_id' => $publicId],
                    [
                        'title' => $this->nullableString($legacyRow->title ?? null),
                        'category_name' => $this->nullableString($legacyRow->category ?? null),
                        'description' => $this->nullableString($legacyRow->description ?? null),
                        'relative_path' => sanitize_relative_upload_path((string) ($legacyRow->path ?? '')),
                        'original_file_name' => $this->nullableString($legacyRow->file_name ?? null),
                        'size_bytes' => max(0, (int) ($legacyRow->size ?? 0)),
                        'mime_type' => $this->nullableString($legacyRow->mime ?? null),
                        'branch_code' => $this->nullableString($legacyRow->branch ?? null),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ],
                );

                $fileIdsByPublicId[$publicId] = (int) DB::table('church_files')
                    ->where('public_id', $publicId)
                    ->value('id');
            }

            DB::table('public_material_menu_files')->delete();

            $menuSortOrder = 0;
            foreach (public_material_menu_options() as $menuKey => $option) {
                $folderPath = normalize_church_folder_path((string) ($option['folder'] ?? ''));
                DB::table('public_material_menus')->updateOrInsert(
                    ['menu_key' => $menuKey],
                    [
                        'label' => trim((string) ($option['label'] ?? 'Materi')),
                        'subtitle' => $this->nullableString($option['subtitle'] ?? null),
                        'folder_path' => $folderPath,
                        'sort_order' => $menuSortOrder,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $menuId = (int) DB::table('public_material_menus')->where('menu_key', $menuKey)->value('id');
                $menuRows = $this->legacyRowsForMenu($legacyRows->all(), (string) $menuKey)->values();
                foreach ($menuRows as $sortOrder => $legacyRow) {
                    $publicId = $this->publicId($legacyRow);
                    $churchFileId = $fileIdsByPublicId[$publicId] ?? 0;
                    if ($churchFileId < 1) {
                        continue;
                    }

                    DB::table('public_material_menu_files')->insert([
                        'public_material_menu_id' => $menuId,
                        'church_file_id' => $churchFileId,
                        'sort_order' => $sortOrder,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $menuSortOrder++;
            }
        });

        $this->info('Migrated ' . $legacyRows->count() . ' church files.');
        $this->info('Created ' . DB::table('public_material_menu_files')->count() . ' public material file links.');

        return self::SUCCESS;
    }

    /**
     * @param array<int, object> $legacyRows
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function legacyRowsForMenu(array $legacyRows, string $menuKey): \Illuminate\Support\Collection
    {
        $records = array_map(function (object $row): array {
            return [
                'id' => $this->publicId($row),
                'title' => (string) ($row->title ?? ''),
                'category' => (string) ($row->category ?? ''),
                'description' => (string) ($row->description ?? ''),
                'path' => (string) ($row->path ?? ''),
                'file_name' => (string) ($row->file_name ?? ''),
                'size' => max(0, (int) ($row->size ?? 0)),
                'mime' => (string) ($row->mime ?? ''),
            ];
        }, $legacyRows);

        $allowedRecords = church_files_for_public_material($records, $menuKey);
        $allowedIds = [];
        foreach ($allowedRecords as $record) {
            $allowedIds[(string) ($record['id'] ?? '')] = true;
        }

        return collect($legacyRows)
            ->filter(fn (object $row): bool => isset($allowedIds[$this->publicId($row)]))
            ->sort(function (object $a, object $b): int {
                $aTitle = trim((string) ($a->title ?? $a->file_name ?? ''));
                $bTitle = trim((string) ($b->title ?? $b->file_name ?? ''));
                $cmp = strcasecmp($aTitle, $bTitle);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcasecmp(trim((string) ($a->file_name ?? '')), trim((string) ($b->file_name ?? '')));
            });
    }

    private function publicId(object $row): string
    {
        $publicId = trim((string) ($row->record_uid ?? ''));
        if ($publicId !== '') {
            return $publicId;
        }

        return 'church_file_' . (string) ($row->id ?? md5((string) ($row->path ?? uniqid('', true))));
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
