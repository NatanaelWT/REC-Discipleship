<?php

namespace App\Services\Developer;

use App\Enums\PublicMaterialMenuKey;
use App\Models\Branch;
use App\Models\PublicMaterialFile;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DeveloperDiagnosticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'counts' => $this->counts(),
            'storage' => $this->storage(),
            'materials' => $this->materials(),
            'runtime' => $this->runtime(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'users' => $this->safeCount(User::class),
            'active_users' => $this->safeUserCount(true),
            'active_developers' => $this->safeActiveDeveloperCount(),
            'branches' => $this->safeActiveDiscipleshipBranchCount(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        $publicStorage = public_path('storage');
        $target = storage_path('app/public');

        return [
            'public_storage_path' => $publicStorage,
            'target_path' => $target,
            'link_exists' => is_link($publicStorage) || is_dir($publicStorage),
            'is_symlink' => is_link($publicStorage),
            'target_exists' => is_dir($target),
            'public_storage_writable' => is_dir($target) && is_writable($target),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function materials(): array
    {
        $summary = [
            'records' => 0,
            'invalid_paths' => 0,
            'missing_files' => 0,
            'unregistered_files' => 0,
        ];

        try {
            if (! Schema::hasTable('public_material_files')) {
                return $summary;
            }

            $files = PublicMaterialFile::query()->get(['menu', 'relative_path']);
            $summary['records'] = $files->count();
            $registeredPaths = [];

            foreach ($files as $file) {
                $menu = PublicMaterialMenuKey::fromKey((string) ($file->menu ?? ''));
                $path = sanitize_relative_upload_path((string) ($file->relative_path ?? ''));
                if (! $menu instanceof PublicMaterialMenuKey || $path === '' || ! $this->materialPathBelongsToMenu($menu, $path)) {
                    $summary['invalid_paths']++;

                    continue;
                }

                $registeredPaths[$path] = true;
                if (public_material_resolve_path($path) === null) {
                    $summary['missing_files']++;
                }
            }

            foreach (PublicMaterialMenuKey::cases() as $menu) {
                foreach ($this->materialPhysicalFiles($menu) as $fullPath) {
                    $relativePath = public_material_file_relative_path($menu->folder(), basename($fullPath));
                    if ($relativePath !== '' && ! isset($registeredPaths[$relativePath])) {
                        $summary['unregistered_files']++;
                    }
                }
            }
        } catch (Throwable) {
            return $summary;
        }

        return $summary;
    }

    /**
     * @return array<string, string>
     */
    private function runtime(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => (string) config('app.env', ''),
            'app_debug' => config('app.debug') ? 'true' : 'false',
            'app_timezone' => app_config_value('app_timezone', (string) config('app.timezone', 'Asia/Jakarta')),
            'db_connection' => (string) config('database.default', ''),
        ];
    }

    /**
     * @param  class-string  $model
     */
    private function safeCount(string $model): int
    {
        try {
            return $model::query()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeUserCount(bool $active): int
    {
        try {
            return User::query()->where('is_active', $active)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeActiveDeveloperCount(): int
    {
        try {
            return User::query()
                ->where('access_scope', 'developer')
                ->where('is_active', true)
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeActiveDiscipleshipBranchCount(): int
    {
        try {
            return Branch::query()
                ->where('is_active', true)
                ->where('label', '!=', 'Pusat')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function materialPathBelongsToMenu(PublicMaterialMenuKey $menu, string $path): bool
    {
        $path = public_material_current_relative_path($path);
        if ($path === '') {
            return false;
        }

        $menuFolder = public_material_folder_relative_path($menu->folder());

        return $path !== $menuFolder && str_starts_with($path, $menuFolder.'/');
    }

    /**
     * @return array<int, string>
     */
    private function materialPhysicalFiles(PublicMaterialMenuKey $menu): array
    {
        $folder = rtrim(str_replace('\\', '/', public_material_folder_full_path($menu->folder())), '/');
        if ($folder === '' || ! is_dir($folder)) {
            return [];
        }

        $files = glob($folder.'/*') ?: [];
        $files = array_values(array_filter($files, 'is_file'));
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }
}
