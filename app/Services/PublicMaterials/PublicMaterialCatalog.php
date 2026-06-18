<?php

namespace App\Services\PublicMaterials;

use App\Enums\PublicMaterialMenuKey;
use App\Models\PublicMaterialFile;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Eloquent\Builder;

class PublicMaterialCatalog
{
    public function menu(string $menuKey): ?PublicMaterialMenuKey
    {
        RuntimeBootstrap::load();

        return PublicMaterialMenuKey::fromKey($menuKey);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filesForMenu(PublicMaterialMenuKey $menu): array
    {
        RuntimeBootstrap::load();

        return $this->fileQueryForMenu($menu)
            ->get()
            ->sortBy(fn (PublicMaterialFile $file): string => $this->sortName($file), SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (PublicMaterialFile $file): array => $this->fileRow($file))
            ->all();
    }

    public function fileBelongsToMenu(PublicMaterialMenuKey $menu, PublicMaterialFile $file): bool
    {
        RuntimeBootstrap::load();

        return (string) ($file->menu ?? '') === $menu->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function fileRow(PublicMaterialFile $file): array
    {
        $path = (string) ($file->relative_path ?? '');

        return [
            'id' => (string) $file->public_id,
            'title' => (string) ($file->title ?? ''),
            'category' => (string) ($file->category_name ?? ''),
            'description' => (string) ($file->description ?? ''),
            'path' => $path,
            'public_url' => $this->publicUrlForPath($path),
            'file_name' => (string) ($file->original_file_name ?? ''),
            'size' => max(0, (int) ($file->size_bytes ?? 0)),
            'mime' => (string) ($file->mime_type ?? ''),
            'uploaded_at' => optional($file->created_at)->toIso8601String(),
            'updated_at' => optional($file->updated_at)->toIso8601String(),
        ];
    }

    /**
     * @return Builder<PublicMaterialFile>
     */
    private function fileQueryForMenu(PublicMaterialMenuKey $menu): Builder
    {
        return PublicMaterialFile::query()
            ->where('menu', $menu->value)
            ->orderBy('id');
    }

    private function sortName(PublicMaterialFile $file): string
    {
        $name = trim((string) ($file->title ?? ''));
        if ($name === '') {
            $name = trim((string) ($file->original_file_name ?? ''));
        }
        if ($name === '') {
            $name = basename((string) ($file->relative_path ?? ''));
        }

        return $name;
    }

    private function publicUrlForPath(string $path): string
    {
        RuntimeBootstrap::load();

        return public_material_public_url($path);
    }
}
