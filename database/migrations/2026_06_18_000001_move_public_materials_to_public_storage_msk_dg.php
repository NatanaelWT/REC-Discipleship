<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateMaterialMenuFolders(static function (string $folderPath): string {
            return self::stripMskDgPrefix($folderPath);
        });

        $this->updateMaterialFilePaths(static function (string $relativePath): string {
            return self::legacyPathToCurrent($relativePath);
        });
    }

    public function down(): void
    {
        $this->updateMaterialMenuFolders(static function (string $folderPath): string {
            $folderPath = trim(str_replace('\\', '/', $folderPath), '/');

            return $folderPath === '' ? 'MSK-DG' : 'MSK-DG/' . $folderPath;
        });

        $this->updateMaterialFilePaths(static function (string $relativePath): string {
            $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
            $base = 'msk-dg';
            if ($relativePath === $base) {
                return 'uploads/files/MSK-DG';
            }
            if (str_starts_with($relativePath, $base . '/')) {
                return 'uploads/files/MSK-DG' . substr($relativePath, strlen($base));
            }

            return $relativePath;
        });
    }

    /**
     * @param callable(string): string $mapper
     */
    private function updateMaterialMenuFolders(callable $mapper): void
    {
        if (! Schema::hasTable('public_material_menus')) {
            return;
        }

        DB::table('public_material_menus')
            ->orderBy('id')
            ->get(['id', 'folder_path'])
            ->each(function (object $menu) use ($mapper): void {
                $current = (string) ($menu->folder_path ?? '');
                $next = $mapper($current);
                if ($next !== $current) {
                    DB::table('public_material_menus')
                        ->where('id', $menu->id)
                        ->update([
                            'folder_path' => $next,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * @param callable(string): string $mapper
     */
    private function updateMaterialFilePaths(callable $mapper): void
    {
        if (! Schema::hasTable('public_material_files')) {
            return;
        }

        DB::table('public_material_files')
            ->orderBy('id')
            ->get(['id', 'relative_path'])
            ->each(function (object $file) use ($mapper): void {
                $current = (string) ($file->relative_path ?? '');
                $next = $mapper($current);
                if ($next !== $current) {
                    DB::table('public_material_files')
                        ->where('id', $file->id)
                        ->update([
                            'relative_path' => $next,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private static function stripMskDgPrefix(string $folderPath): string
    {
        $folderPath = trim(str_replace('\\', '/', $folderPath), '/');
        if ($folderPath === '') {
            return '';
        }

        $segments = explode('/', $folderPath);
        if (count($segments) > 0 && strtolower($segments[0]) === 'msk-dg') {
            array_shift($segments);
        }

        return implode('/', $segments);
    }

    private static function legacyPathToCurrent(string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
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
};
