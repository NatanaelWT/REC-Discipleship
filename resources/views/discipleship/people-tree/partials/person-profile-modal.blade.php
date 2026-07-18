@php
    $personProfileCentralReadOnly = (bool) ($centralReadOnly ?? is_effective_central_discipleship_readonly());
    $personProfileShowActions = (bool) ($showActions ?? ! $personProfileCentralReadOnly);
    $personProfileFooterHtml = '';

    if ($personProfileShowActions && ! $personProfileCentralReadOnly) {
        $personProfileFooterHtml .= '<div class="tree-v2-profile-actions">';
        $personProfileFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-add" type="button" data-tree-v2-profile-action="add_group">'.icon_svg('plus').'<span>Tambah Kelompok</span></button>';
        $personProfileFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-edit" type="button" data-tree-v2-profile-action="edit_person">'.icon_svg('edit').'<span>Edit Orang</span></button>';
        $personProfileFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-leave" type="button" data-tree-v2-profile-action="leave_group">'.icon_svg('exit').'<span>Keluar DG</span></button>';
        $personProfileFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-delete" type="button" data-tree-v2-profile-action="delete_person">'.icon_svg('trash').'<span>Hapus Anggota</span></button>';
        $personProfileFooterHtml .= '</div>';
    }
@endphp

@include('partials.modal', [
    'id' => $personProfileModalId ?? 'tree-v2-person-profile-modal',
    'size' => 'standard',
    'modalAttrs' => ['data-tree-v2-person-profile-modal' => true],
    'cardClass' => 'member-view-modal-card msk-view-modal-card',
    'title' => 'Profil Orang',
    'titleAttrs' => ['data-tree-v2-person-profile-title' => true],
    'closeAttrs' => ['data-tree-v2-person-profile-close' => true],
    'bodyAttrs' => ['data-tree-v2-person-profile-body' => true],
    'bodyHtml' => $personProfileEmptyMessage ?? '<div class="panel-note">Klik orang pada pohon untuk melihat profil.</div>',
    'footerHtml' => $personProfileFooterHtml,
])
