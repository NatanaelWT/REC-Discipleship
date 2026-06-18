<?php

function public_material_menu_options(): array {
    $options = [];
    foreach (\App\Enums\PublicMaterialMenuKey::cases() as $menu) {
        $options[$menu->value] = [
            'label' => $menu->label(),
            'folder' => $menu->folder(),
            'subtitle' => $menu->subtitle(),
        ];
    }

    return $options;
}
