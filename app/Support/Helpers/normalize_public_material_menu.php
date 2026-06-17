<?php

function normalize_public_material_menu(string $menu): string {
    $menu = trim($menu);
    $options = public_material_menu_options();
    if (!isset($options[$menu])) {
        return '';
    }
    return $menu;
}
