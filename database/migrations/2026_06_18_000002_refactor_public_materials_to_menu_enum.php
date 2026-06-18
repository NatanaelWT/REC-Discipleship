<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_META = [
        'materi_dg_1' => [
            'label' => 'Materi DG-1 (BePI)',
            'subtitle' => 'Berpusat Pada Injil',
            'folder_path' => 'Materi-DG/DG-1',
        ],
        'materi_dg_2' => [
            'label' => 'Materi DG-2 (BOI)',
            'subtitle' => 'Berubah Oleh Injil',
            'folder_path' => 'Materi-DG/DG-2',
        ],
        'materi_dg_3' => [
            'label' => 'Materi DG-3',
            'subtitle' => 'Bertumbuh Dalam Pemuridan Lanjutan',
            'folder_path' => 'Materi-DG/DG-3',
        ],
        'meditasi_injil' => [
            'label' => 'Meditasi Injil (BePI)',
            'subtitle' => 'Merenungkan Injil Setiap Hari',
            'folder_path' => 'Materi-DG/Meditasi-Injil',
        ],
        'handbook_perjanjian_kelompok' => [
            'label' => 'Handbook & Perjanjian Kelompok',
            'subtitle' => 'Bertumbuh Dalam Komitmen Kelompok',
            'folder_path' => 'Materi-DG/Handbook-Perjanjian-Kelompok',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('public_material_files')) {
            Schema::dropIfExists('public_material_menus');

            return;
        }

        if (! Schema::hasColumn('public_material_files', 'menu')) {
            Schema::table('public_material_files', function (Blueprint $table): void {
                $table->string('menu', 80)->nullable()->index();
            });
        }

        $this->backfillMenuColumn();
        $this->normalizeRelativePaths();

        DB::table('public_material_files')
            ->whereNull('menu')
            ->orWhereNotIn('menu', array_keys(self::MENU_META))
            ->delete();

        $this->dropLegacyFileColumns();

        Schema::table('public_material_files', function (Blueprint $table): void {
            $table->string('menu', 80)->nullable(false)->change();
        });

        $this->addIndexIfPossible('public_material_files', 'menu', 'public_material_files_menu_index');
        $this->addIndexIfPossible('public_material_files', 'relative_path', 'public_material_files_relative_path_index');

        Schema::dropIfExists('public_material_menu_files');
        Schema::dropIfExists('public_material_menus');
    }

    public function down(): void
    {
        if (! Schema::hasTable('public_material_files')) {
            return;
        }

        $this->recreatePublicMaterialMenus();

        Schema::table('public_material_files', function (Blueprint $table): void {
            if (! Schema::hasColumn('public_material_files', 'public_material_menu_id')) {
                $table->unsignedBigInteger('public_material_menu_id')->nullable()->index();
            }
            if (! Schema::hasColumn('public_material_files', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable();
            }
            if (! Schema::hasColumn('public_material_files', 'branch_code')) {
                $table->string('branch_code', 40)->nullable()->index();
            }
            if (! Schema::hasColumn('public_material_files', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->index();
            }
        });

        $menuIds = DB::table('public_material_menus')->pluck('id', 'menu_key')->all();
        DB::table('public_material_files')
            ->select(['id', 'menu'])
            ->orderBy('id')
            ->get()
            ->each(function (object $file) use ($menuIds): void {
                $menuId = $menuIds[(string) ($file->menu ?? '')] ?? null;
                DB::table('public_material_files')
                    ->where('id', $file->id)
                    ->update(['public_material_menu_id' => $menuId]);
            });

        if (Schema::hasColumn('public_material_files', 'menu')) {
            Schema::table('public_material_files', function (Blueprint $table): void {
                $table->dropColumn('menu');
            });
        }
    }

    private function backfillMenuColumn(): void
    {
        if (
            ! Schema::hasTable('public_material_menus')
            || ! Schema::hasColumn('public_material_files', 'public_material_menu_id')
        ) {
            return;
        }

        $menuKeys = DB::table('public_material_menus')->pluck('menu_key', 'id')->all();
        DB::table('public_material_files')
            ->select(['id', 'menu', 'public_material_menu_id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $file) use ($menuKeys): void {
                if (trim((string) ($file->menu ?? '')) !== '') {
                    return;
                }

                $menuKey = $menuKeys[$file->public_material_menu_id] ?? null;
                if ($menuKey === null) {
                    return;
                }

                DB::table('public_material_files')
                    ->where('id', $file->id)
                    ->update([
                        'menu' => $menuKey,
                        'updated_at' => now(),
                    ]);
            });
    }

    private function normalizeRelativePaths(): void
    {
        DB::table('public_material_files')
            ->select(['id', 'relative_path'])
            ->orderBy('id')
            ->get()
            ->each(function (object $file): void {
                $current = trim(str_replace('\\', '/', (string) ($file->relative_path ?? '')), '/');
                $next = $this->legacyPathToCurrent($current);
                if ($next === $current) {
                    return;
                }

                DB::table('public_material_files')
                    ->where('id', $file->id)
                    ->update([
                        'relative_path' => $next,
                        'updated_at' => now(),
                    ]);
            });
    }

    private function legacyPathToCurrent(string $relativePath): string
    {
        $legacyBase = 'uploads/files/MSK-DG';
        $legacyBaseLower = strtolower($legacyBase);
        $relativePathLower = strtolower($relativePath);

        if ($relativePathLower === $legacyBaseLower) {
            return 'msk-dg';
        }
        if (str_starts_with($relativePathLower, $legacyBaseLower . '/')) {
            return 'msk-dg' . substr($relativePath, strlen($legacyBase));
        }

        return $relativePath;
    }

    private function dropLegacyFileColumns(): void
    {
        $this->dropIndexIfPossible('public_material_files', 'public_material_files_menu_sort_index');
        $this->dropIndexIfPossible('public_material_files', 'public_material_files_sort_order_index');
        $this->dropIndexIfPossible('public_material_files', 'public_material_files_branch_code_index');
        $this->dropForeignIfPossible('public_material_files', ['public_material_menu_id']);
        $this->dropForeignIfPossible('public_material_files', ['branch_id']);

        foreach (['public_material_menu_id', 'branch_id', 'branch_code', 'sort_order'] as $column) {
            if (! Schema::hasColumn('public_material_files', $column)) {
                continue;
            }

            Schema::table('public_material_files', function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    private function recreatePublicMaterialMenus(): void
    {
        if (Schema::hasTable('public_material_menus')) {
            return;
        }

        Schema::create('public_material_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('menu_key', 120)->unique();
            $table->string('label');
            $table->string('subtitle')->nullable();
            $table->string('folder_path', 500)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $sortOrder = 0;
        foreach (self::MENU_META as $menuKey => $menu) {
            DB::table('public_material_menus')->insert([
                'menu_key' => $menuKey,
                'label' => $menu['label'],
                'subtitle' => $menu['subtitle'],
                'folder_path' => $menu['folder_path'],
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sortOrder++;
        }
    }

    private function dropForeignIfPossible(string $tableName, array $columns): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                $table->dropForeign($columns);
            });
        } catch (Throwable) {
            // The legacy column may exist without a foreign key on older databases.
        }
    }

    private function dropIndexIfPossible(string $tableName, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        } catch (Throwable) {
            // The index name varies depending on which historical migration created the table.
        }
    }

    private function addIndexIfPossible(string $tableName, string $columnName, string $indexName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columnName, $indexName): void {
                $table->index($columnName, $indexName);
            });
        } catch (Throwable) {
            // Existing databases may already have the desired index.
        }
    }
};
