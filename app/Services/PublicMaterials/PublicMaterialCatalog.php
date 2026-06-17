<?php

namespace App\Services\PublicMaterials;

use App\Models\ChurchFile;
use App\Models\PublicMaterialMenu;
use App\Support\RuntimeBootstrap;
use Illuminate\Support\Facades\Schema;

class PublicMaterialCatalog
{
    public function menu(string $menuKey): ?PublicMaterialMenu
    {
        RuntimeBootstrap::load();

        $menuKey = $this->normalizeMenuKey($menuKey);
        if ($menuKey === '') {
            return null;
        }

        return PublicMaterialMenu::query()
            ->where('menu_key', $menuKey)
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filesForMenu(PublicMaterialMenu $menu): array
    {
        RuntimeBootstrap::load();

        return $this->fileQueryForMenu($menu)
            ->get()
            ->map(fn (ChurchFile $file): array => $this->fileRow($file))
            ->all();
    }

    public function fileBelongsToMenu(PublicMaterialMenu $menu, ChurchFile $file): bool
    {
        RuntimeBootstrap::load();

        if (Schema::hasTable('public_material_files')) {
            return (int) ($file->public_material_menu_id ?? 0) === (int) $menu->id;
        }

        return $menu->churchFiles()
            ->where('church_files.id', $file->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function fileRow(ChurchFile $file): array
    {
        return [
            'id' => (string) $file->public_id,
            'title' => (string) ($file->title ?? ''),
            'category' => (string) ($file->category_name ?? ''),
            'description' => (string) ($file->description ?? ''),
            'path' => (string) ($file->relative_path ?? ''),
            'file_name' => (string) ($file->original_file_name ?? ''),
            'size' => max(0, (int) ($file->size_bytes ?? 0)),
            'mime' => (string) ($file->mime_type ?? ''),
            'uploaded_at' => optional($file->created_at)->toIso8601String(),
            'updated_at' => optional($file->updated_at)->toIso8601String(),
        ];
    }

    private function fileQueryForMenu(PublicMaterialMenu $menu)
    {
        if (Schema::hasTable('public_material_files')) {
            return ChurchFile::query()
                ->where('public_material_menu_id', $menu->id)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->orderBy('original_file_name');
        }

        return $menu->churchFiles()
            ->orderBy('public_material_menu_files.sort_order')
            ->orderBy('church_files.title')
            ->orderBy('church_files.original_file_name');
    }

    private function normalizeMenuKey(string $menuKey): string
    {
        $menuKey = trim($menuKey);
        if ($menuKey === '') {
            return '';
        }

        if (function_exists('normalize_public_material_menu')) {
            return normalize_public_material_menu($menuKey);
        }

        return preg_match('/^[a-z0-9_\\-]+$/', $menuKey) === 1 ? $menuKey : '';
    }
}
