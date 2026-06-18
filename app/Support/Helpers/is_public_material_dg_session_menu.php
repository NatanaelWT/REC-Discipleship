<?php

function is_public_material_dg_session_menu(string $menu): bool {
    return \App\Enums\PublicMaterialMenuKey::fromKey($menu)?->isDgSessionMenu() ?? false;
}
