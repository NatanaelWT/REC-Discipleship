<?php

function central_recap_selected_branch(): string
{
    if (! is_effective_central_discipleship_readonly()) {
        return 'all';
    }

    $fromQuery = trim((string) request()->query('rekap_cabang', ''));
    if ($fromQuery !== '') {
        $selected = normalize_central_recap_branch($fromQuery);
        session()->put('central_rekap_cabang', $selected);

        return $selected;
    }

    $fromSession = trim((string) session('central_rekap_cabang', ''));
    if ($fromSession !== '') {
        return normalize_central_recap_branch($fromSession);
    }

    session()->put('central_rekap_cabang', 'all');

    return 'all';
}
