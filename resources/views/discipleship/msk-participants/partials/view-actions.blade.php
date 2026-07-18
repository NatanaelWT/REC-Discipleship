@php
    $participantId = trim((string) ($participant['id'] ?? ''));
    $participantStatus = normalize_msk_participant_status((string) ($participant['status'] ?? 'active'));
    $isInactive = $participantStatus === 'inactive';
    $canDeactivate = (bool) ($canDeactivate ?? false);
    $showToggleAction = $isInactive || $canDeactivate;
    $toggleRouteName = $isInactive
        ? 'discipleship.msk-classes.reactivate'
        : 'discipleship.msk-classes.deactivate';
    $toggleAction = $isInactive ? 'reactivate_msk_participant' : 'delete_msk_participant';
    $toggleLabel = $isInactive ? 'Aktifkan' : 'Nonaktifkan';
    $toggleConfirm = $isInactive
        ? 'Aktifkan kembali data peserta MSK ini?'
        : 'Nonaktifkan data peserta MSK ini?';
    $toggleClass = $isInactive ? 'btn tiny msk-view-reactivate-button' : 'btn tiny msk-view-deactivate-button';
    $toggleIcon = $isInactive ? icon_svg('check') : icon_svg('exit');
    $toggleRoute = $showToggleAction
        ? route($toggleRouteName, ['participant' => $participantId] + $branchRouteParams)
        : '';
    $permanentDeleteRoute = $isInactive
        ? route('discipleship.msk-classes.destroy', ['participant' => $participantId] + $branchRouteParams)
        : '';
@endphp

<a class="btn tiny msk-view-action-button msk-view-edit-button" href="{{ $editHref }}" data-msk-edit-from-view="{{ $participantId }}">
  {!! icon_svg('edit') !!}
  <span>Edit</span>
</a>
@if ($showToggleAction)
  <form method="post" action="{{ $toggleRoute }}" class="inline msk-view-action-form" onsubmit="return confirm('{{ $toggleConfirm }}');">
    @csrf
    <input type="hidden" name="action" value="{{ $toggleAction }}">
    <input type="hidden" name="id" value="{{ $participantId }}">
    <input type="hidden" name="batch_month" value="{{ $batchMonthFilterParam }}">
    <button class="{{ $toggleClass }} msk-view-action-button" type="submit">
      {!! $toggleIcon !!}
      <span>{{ $toggleLabel }}</span>
    </button>
  </form>
@else
  <button
    class="btn tiny msk-view-action-button msk-view-deactivate-button is-blocked"
    type="button"
    data-msk-deactivate-blocked
    data-msk-deactivate-message="Peserta masih menjadi pemimpin, pendamping, atau anggota DG aktif sehingga tidak dapat dinonaktifkan."
  >
    {!! icon_svg('exit') !!}
    <span>Nonaktifkan</span>
  </button>
@endif
@if ($isInactive)
  <form method="post" action="{{ $permanentDeleteRoute }}" class="inline msk-view-action-form msk-view-permanent-delete-form" onsubmit="return confirm('Hapus permanen data peserta MSK ini? Tindakan ini tidak dapat dibatalkan.');">
    @csrf
    @method('DELETE')
    <input type="hidden" name="action" value="permanently_delete_msk_participant">
    <input type="hidden" name="id" value="{{ $participantId }}">
    <input type="hidden" name="batch_month" value="{{ $batchMonthFilterParam }}">
    <button class="btn tiny danger msk-view-action-button msk-view-permanent-delete-button" type="submit">
      {!! icon_svg('trash') !!}
      <span>Hapus Permanen</span>
    </button>
  </form>
@endif
