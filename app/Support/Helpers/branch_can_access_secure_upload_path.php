<?php

use App\Services\Auth\CurrentUserContext;

function branch_can_access_secure_upload_path(string $branch, string $path): bool
{
    return app(CurrentUserContext::class)->canAccessSecureUploadPath($path);
}
