<?php

function normalize_public_material_menu(string $menu): string {
    $menu = \App\Enums\PublicMaterialMenuKey::fromKey($menu);

    return $menu?->value ?? '';
}
