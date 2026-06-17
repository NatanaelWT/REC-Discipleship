<?php

function asset_version(string $path): string {
    $full = rec_public_path(ltrim($path, '/'));
    $mtime = @filemtime($full);
    return $mtime ? ('?v=' . $mtime) : '';
}
