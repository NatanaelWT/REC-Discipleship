<?php

function public_material_option(string $menu): array {
    $normalized = normalize_public_material_menu($menu);
    $options = public_material_menu_options();
    if ($normalized === '' || !isset($options[$normalized]) || !is_array($options[$normalized])) {
        return [];
    }
    return $options[$normalized];
}
