<?php

function upload_managed_files(array $fileInput, string &$errorCode, callable $singleUploader, array $cleanupPathKeys = ['path']): array {
    $errorCode = '';
    $uploaded = [];
    $items = normalize_uploaded_file_items($fileInput);
    foreach ($items as $item) {
        $singleError = '';
        $single = $singleUploader($item, $singleError);
        if ($singleError !== '') {
            cleanup_uploaded_entries(array_values(array_filter(
                $uploaded,
                static fn (array $entry): bool => empty($entry['storage_reused']),
            )), $cleanupPathKeys);
            $errorCode = $singleError;
            return [];
        }
        if ($single !== null) {
            $uploaded[] = $single;
        }
    }
    return $uploaded;
}
