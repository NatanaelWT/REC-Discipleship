<?php

function requested_public_dg_branch(): string {
    if (!is_public_dg_flow_request()) {
        return 'kutisari';
    }
    $rawBranch = trim((string) ($_GET['cabang'] ?? ''));
    if ($rawBranch === '') {
        $rawBranch = trim((string) ($_POST['public_cabang'] ?? ''));
    }
    return normalize_public_branch_code($rawBranch);
}
