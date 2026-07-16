@php
    $participantId = trim((string) ($participant['id'] ?? ''));
    $fullName = trim((string) ($participant['full_name'] ?? ''));
    if ($fullName === '') {
        $fullName = '-';
    }
    $tableMskMonth = import_normalize_month_strict((string) ($participant['msk_month'] ?? ''));
    $mskMonthLabel = $tableMskMonth !== '' ? format_indo_month($tableMskMonth) : '-';
    $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
    $sessionCount = count($sessionNumbers);
    $progressLabel = (string) $sessionCount.'/12 sesi';
    $progressPercent = max(0, min(100, (int) round(($sessionCount / 12) * 100)));
    $progressMeta = $sessionCount > 0 ? ('Sesi '.implode(', ', array_map('strval', $sessionNumbers))) : 'Belum ada sesi yang ditandai.';
    $participantStatus = normalize_msk_participant_status((string) ($participant['status'] ?? 'active'));
    $statusBadge = '<span class="msk-status-badge is-pending">Belum</span>';
    if ($participantStatus === 'inactive') {
        $statusBadge = '<span class="msk-status-badge is-inactive">Nonaktif</span>';
    } elseif ($sessionCount === 12) {
        $statusBadge = '<span class="msk-status-badge is-complete">Selesai</span>';
    } elseif ($sessionCount > 0) {
        $statusBadge = '<span class="msk-status-badge is-progress">Proses</span>';
    }
    $whatsapp = trim((string) ($participant['whatsapp'] ?? ''));
    $waDisplay = $whatsapp !== '' ? $whatsapp : '-';
    $waDigits = preg_replace('/\D+/', '', $whatsapp) ?? '';
    if ($waDigits !== '' && strpos($waDigits, '0') === 0) {
        $waDigits = '62'.substr($waDigits, 1);
    }
    $waHtml = h($waDisplay);
    if ($waDigits !== '') {
        $waHtml = '<a class="note-link msk-wa-link" href="'.h('https://wa.me/'.$waDigits).'" target="_blank" rel="noopener">Hubungi <span>'.h($waDisplay).'</span></a>';
    } else {
        $waHtml = '<span class="msk-wa-empty">Tidak ada nomor</span>';
    }
    $searchText = trim($fullName.' '.$whatsapp.' '.(string) ($participant['email'] ?? ''));
    $viewRouteParams = ['view' => $participantId, 'batch_month' => $batchMonthFilterParam];
    $editRouteParams = ['edit' => $participantId, 'batch_month' => $batchMonthFilterParam];
    if (request()->filled('branch_id')) {
        $viewRouteParams['branch_id'] = request()->query('branch_id');
        $editRouteParams['branch_id'] = request()->query('branch_id');
    }
    if (request()->filled('q')) {
        $viewRouteParams['q'] = request()->query('q');
        $editRouteParams['q'] = request()->query('q');
    }
    $viewHref = route('discipleship.msk-classes', $viewRouteParams);
    $editHref = route('discipleship.msk-classes', $editRouteParams);
    $nameSubLabel = $participantStatus === 'inactive' ? 'Peserta kelas MSK - Nonaktif' : 'Peserta kelas MSK';
@endphp
<tr data-msk-search-row data-search-text="{{ $searchText }}">
  <td><div class="msk-name-cell"><span class="msk-name-main">{{ $fullName }}</span><span class="msk-name-sub">{{ $nameSubLabel }}</span></div></td>
  <td><div class="msk-month-cell"><span class="msk-month-main">{{ $mskMonthLabel }}</span><span class="msk-month-sub">Batch pembinaan</span></div></td>
  <td><div class="msk-progress-cell"><div class="msk-progress-top"><span class="msk-progress-value">{{ $progressLabel }}</span><span class="msk-progress-percent">{{ (string) $progressPercent }}%</span></div><div class="msk-progress-bar" aria-hidden="true"><span style="width:{{ (string) $progressPercent }}%"></span></div><div class="msk-progress-meta">{{ $progressMeta }}</div></div></td>
  <td>{!! $statusBadge !!}</td>
  <td>{!! $waHtml !!}</td>
  <td class="actions">
    <button class="btn tiny secondary icon-btn" type="button" data-msk-view-open="{{ $participantId }}" data-msk-view-href="{{ $viewHref }}" aria-label="Lihat" title="Lihat">{!! icon_svg('eye') !!}</button>
    @if (! $centralReadOnly)
      @php
          $toggleAction = $participantStatus === 'inactive' ? 'reactivate_msk_participant' : 'delete_msk_participant';
          $toggleRouteName = $participantStatus === 'inactive' ? 'discipleship.msk-classes.reactivate' : 'discipleship.msk-classes.deactivate';
          $toggleRouteParams = ['participant' => $participantId];
          if (current_user_branch_id() !== null) {
              $toggleRouteParams['branch_id'] = current_user_branch_id();
          }
          $toggleRoute = route($toggleRouteName, $toggleRouteParams);
          $toggleLabel = $participantStatus === 'inactive' ? 'Aktifkan' : 'Nonaktifkan';
          $toggleConfirm = $participantStatus === 'inactive'
              ? 'Aktifkan kembali data peserta MSK ini?'
              : 'Nonaktifkan data peserta MSK ini?';
          $toggleClass = $participantStatus === 'inactive' ? 'btn tiny secondary icon-btn' : 'btn tiny danger icon-btn';
          $toggleIcon = $participantStatus === 'inactive' ? icon_svg('check') : icon_svg('trash');
      @endphp
      <button class="btn tiny icon-btn" type="button" data-msk-edit-open="{{ $participantId }}" data-msk-edit-href="{{ $editHref }}" aria-label="Edit" title="Edit">{!! icon_svg('edit') !!}</button>
      <form method="post" action="{{ $toggleRoute }}" class="inline" onsubmit="return confirm('{{ $toggleConfirm }}');">
        @csrf
        <input type="hidden" name="action" value="{{ $toggleAction }}">
        <input type="hidden" name="id" value="{{ $participantId }}">
        <input type="hidden" name="batch_month" value="{{ $batchMonthFilterParam }}">
        <button class="{{ $toggleClass }}" type="submit" aria-label="{{ $toggleLabel }}" title="{{ $toggleLabel }}">{!! $toggleIcon !!}</button>
      </form>
    @endif
  </td>
</tr>
