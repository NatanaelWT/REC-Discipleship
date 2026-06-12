<?php

namespace App\Services\PublicMaterials;

use App\Models\ChurchFile;
use App\Models\PublicMaterialMenu;

class PublicMaterialRouteResolver
{
    public function __construct(private readonly PublicMaterialCatalog $catalog)
    {
    }

    /**
     * @return array{0: PublicMaterialMenu, 1: ChurchFile}|null
     */
    public function resolve(string $menuKey, string $publicId): ?array
    {
        $menu = $this->catalog->menu($menuKey);
        if (! $menu instanceof PublicMaterialMenu) {
            return null;
        }

        $file = ChurchFile::query()->where('public_id', trim($publicId))->first();
        if (! $file instanceof ChurchFile) {
            return null;
        }

        if (! $this->catalog->fileBelongsToMenu($menu, $file)) {
            return null;
        }

        return [$menu, $file];
    }
}
