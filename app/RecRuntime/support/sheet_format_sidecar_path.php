<?php

function sheet_format_sidecar_path(string $csvFullPath): string {
    return $csvFullPath . '.format.json';
}
