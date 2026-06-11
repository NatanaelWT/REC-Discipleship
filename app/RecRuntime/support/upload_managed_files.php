<?php

function upload_managed_files(array $fileInput, string &$errorCode, callable $singleUploader, array $cleanupPathKeys = ['path']): array {
    $errorCode = '';
    $uploaded = [];
    $items = normalize_uploaded_file_items($fileInput);
    foreach ($items as $item) {
        $singleError = '';
        $single = $singleUploader($item, $singleError);
        if ($singleError !== '') {
            cleanup_uploaded_entries($uploaded, $cleanupPathKeys);
            $errorCode = $singleError;
            return [];
        }
        if ($single !== null) {
            $uploaded[] = $single;
        }
    }
    return $uploaded;
}
