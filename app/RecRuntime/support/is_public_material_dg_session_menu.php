<?php

function is_public_material_dg_session_menu(string $menu): bool {
    return in_array($menu, ['materi_dg_1', 'materi_dg_2', 'materi_dg_3'], true);
}
