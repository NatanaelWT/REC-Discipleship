<?php

function current_auth_access_scope(): string {
    return normalize_auth_access_scope((string) ($_SESSION['access_scope'] ?? 'branch'));
}
