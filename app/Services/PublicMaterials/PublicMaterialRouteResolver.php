<?php

namespace App\Services\PublicMaterials;

use App\Enums\PublicMaterialMenuKey;
use App\Models\PublicMaterialFile;

class PublicMaterialRouteResolver
{
    public function __construct(private readonly PublicMaterialCatalog $catalog) {}

    /**
     * @return array{0: PublicMaterialMenuKey, 1: PublicMaterialFile}|null
     */
    public function resolve(string $menuKey, int $fileId): ?array
    {
        $menu = $this->catalog->menu($menuKey);
        if (! $menu instanceof PublicMaterialMenuKey) {
            return null;
        }

        $file = PublicMaterialFile::query()->find($fileId);
        if (! $file instanceof PublicMaterialFile) {
            return null;
        }

        if (! $this->catalog->fileBelongsToMenu($menu, $file)) {
            return null;
        }

        return [$menu, $file];
    }
}
