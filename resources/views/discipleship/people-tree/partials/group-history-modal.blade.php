@php
    $groupHistoryReadOnly = (bool) ($centralReadOnly ?? is_effective_central_discipleship_readonly());
    $groupHistoryFooterHtml = '';

    if (! $groupHistoryReadOnly) {
        $groupHistoryFooterHtml .= '<div class="tree-v2-profile-actions tree-v2-history-actions">';
        $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-add" type="button" data-tree-v2-action-do="add_member">'.icon_svg('plus').'<span>Tambah Anggota</span></button>';
        $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-complete" type="button" data-tree-v2-action-do="complete_group">'.icon_svg('check').'<span>Selesaikan DG</span></button>';
        $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-reactivate" type="button" data-tree-v2-action-do="reactivate_group">'.icon_svg('check').'<span>Aktifkan DG</span></button>';
        $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-upgrade" type="button" data-tree-v2-action-do="upgrade_group">'.icon_svg('plus').'<span>Upgrade DG</span></button>';
        $groupHistoryFooterHtml .= '</div>';
    }
@endphp

@include('partials.modal', [
    'id' => $groupHistoryModalId ?? 'tree-v2-history-modal',
    'size' => 'wide',
    'modalAttrs' => ['data-tree-v2-history-modal' => true],
    'title' => 'Riwayat Kelompok',
    'titleAttrs' => ['data-tree-v2-history-title' => true],
    'closeAttrs' => ['data-tree-v2-history-close' => true],
    'bodyAttrs' => ['data-tree-v2-history-body' => true],
    'bodyHtml' => '<div class="journey-history-empty">Riwayat kelompok belum tersedia.</div>',
    'footerHtml' => $groupHistoryFooterHtml,
])
