<?php

function cleanup_uploaded_entries(array $uploaded, array $pathKeys = ['path']): void {
    foreach ($uploaded as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        foreach ($pathKeys as $pathKey) {
            $uploadedPath = sanitize_relative_upload_path((string) ($entry[$pathKey] ?? ''));
            if ($uploadedPath !== '') {
                delete_relative_upload_file($uploadedPath);
            }
        }
    }
}
