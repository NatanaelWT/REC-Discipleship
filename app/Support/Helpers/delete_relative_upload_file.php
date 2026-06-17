<?php

function delete_relative_upload_file(string $relativePath): void {
    $safePath = sanitize_relative_upload_path($relativePath);
    if ($safePath === '') {
        return;
    }
    $fullPath = rec_runtime_path($safePath);
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
