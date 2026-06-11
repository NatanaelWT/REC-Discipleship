<?php

function delete_sheet_format_sidecar(string $csvFullPath): void {
    $sidecarPath = sheet_format_sidecar_path($csvFullPath);
    if (is_file($sidecarPath)) {
        @unlink($sidecarPath);
    }
}
