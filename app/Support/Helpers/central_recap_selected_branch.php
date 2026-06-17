<?php

function central_recap_selected_branch(): string {
    if (!is_effective_central_discipleship_readonly()) {
        return 'all';
    }

    $fromQuery = trim((string) ($_GET['rekap_cabang'] ?? ''));
    if ($fromQuery !== '') {
        $selected = normalize_central_recap_branch($fromQuery);
        $_SESSION['central_rekap_cabang'] = $selected;
        return $selected;
    }

    $fromSession = trim((string) ($_SESSION['central_rekap_cabang'] ?? ''));
    if ($fromSession !== '') {
        return normalize_central_recap_branch($fromSession);
    }

    $_SESSION['central_rekap_cabang'] = 'all';
    return 'all';
}
