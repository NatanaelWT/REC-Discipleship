@php
    $groupHistoryReadOnly = (bool) ($centralReadOnly ?? is_effective_central_discipleship_readonly());
    $groupHistoryAllowDelete = (bool) ($allowGroupDelete ?? false);
    $groupHistoryFooterHtml = '';

    if (! $groupHistoryReadOnly) {
        $groupHistoryFooterHtml .= '<div class="tree-v2-profile-actions tree-v2-history-actions">';
        $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-edit is-hidden" type="button" data-tree-v2-action-do="edit_group" hidden disabled>'.icon_svg('edit').'<span>Edit Kelompok</span></button>';
        $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-add is-hidden" type="button" data-tree-v2-action-do="add_member" hidden disabled>'.icon_svg('plus').'<span>Tambah Anggota</span></button>';
        $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-complete is-hidden" type="button" data-tree-v2-action-do="complete_group" hidden disabled>'.icon_svg('check').'<span>Selesaikan DG</span></button>';
        $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-reactivate is-hidden" type="button" data-tree-v2-action-do="reactivate_group" hidden disabled>'.icon_svg('check').'<span>Aktifkan DG</span></button>';
        $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-upgrade is-hidden" type="button" data-tree-v2-action-do="upgrade_group" hidden disabled>'.icon_svg('plus').'<span>Upgrade DG</span></button>';
        if ($groupHistoryAllowDelete) {
            $groupHistoryFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-delete" type="button" data-tree-v2-action-do="delete_group">'.icon_svg('trash').'<span>Hapus Permanen</span></button>';
        }
        $groupHistoryFooterHtml .= '</div>';
    }
@endphp

@include('partials.modal', [
    'id' => $groupHistoryModalId ?? 'tree-v2-history-modal',
    'size' => 'wide',
    'modalAttrs' => ['data-tree-v2-history-modal' => true],
    'cardClass' => 'tree-group-history-modal-card discipleship-tree-panel',
    'title' => 'Riwayat Kelompok',
    'titleAttrs' => ['data-tree-v2-history-title' => true],
    'closeAttrs' => ['data-tree-v2-history-close' => true],
    'bodyAttrs' => ['data-tree-v2-history-body' => true],
    'bodyHtml' => '<div class="journey-history-empty">Riwayat kelompok belum tersedia.</div>',
    'footerHtml' => $groupHistoryFooterHtml,
])
