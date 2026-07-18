@php
    $participantId = trim((string) ($participant['id'] ?? ''));
    $participantStatus = normalize_msk_participant_status((string) ($participant['status'] ?? 'active'));
    $isInactive = $participantStatus === 'inactive';
    $toggleRouteName = $isInactive
        ? 'discipleship.msk-classes.reactivate'
        : 'discipleship.msk-classes.deactivate';
    $toggleAction = $isInactive ? 'reactivate_msk_participant' : 'delete_msk_participant';
    $toggleLabel = $isInactive ? 'Aktifkan' : 'Nonaktifkan';
    $toggleConfirm = $isInactive
        ? 'Aktifkan kembali data peserta MSK ini?'
        : 'Nonaktifkan data peserta MSK ini?';
    $toggleClass = $isInactive ? 'btn tiny secondary' : 'btn tiny danger';
    $toggleIcon = $isInactive ? icon_svg('check') : icon_svg('trash');
    $toggleRoute = route($toggleRouteName, ['participant' => $participantId] + $branchRouteParams);
@endphp

<a class="btn tiny secondary msk-view-action-button" href="{{ $editHref }}" data-msk-edit-from-view="{{ $participantId }}">
  {!! icon_svg('edit') !!}
  <span>Edit</span>
</a>
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
