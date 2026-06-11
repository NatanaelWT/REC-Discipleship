<?php

function is_public_material_previewable_path(string $path): bool {
    $ext = secure_file_extension($path);
    if ($ext === '') {
        return false;
    }
    $previewable = public_material_previewable_extensions();
    return isset($previewable[$ext]);
}
