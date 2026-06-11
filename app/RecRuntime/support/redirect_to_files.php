<?php

function redirect_to_files(array $params = [], string $folderPath = ''): void {
    $folderPath = normalize_church_folder_path($folderPath);
    if ($folderPath !== '') {
        $params['folder'] = $folderPath;
    }
    redirect_to('files', $params);
}
