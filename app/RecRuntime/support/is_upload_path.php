<?php

function is_upload_path(string $path): bool {
    return strpos($path, 'uploads/') === 0;
}
