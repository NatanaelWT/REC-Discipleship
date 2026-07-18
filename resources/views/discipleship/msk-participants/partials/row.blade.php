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
    if (request()->filled('branch_id')) {
        $viewRouteParams['branch_id'] = request()->query('branch_id');
    }
    if (request()->filled('q')) {
        $viewRouteParams['q'] = request()->query('q');
    }
    $viewHref = route('discipleship.msk-classes', $viewRouteParams);
    $nameSubLabel = $participantStatus === 'inactive' ? 'Peserta kelas MSK - Nonaktif' : 'Peserta kelas MSK';
@endphp
<tr data-msk-search-row data-search-text="{{ $searchText }}">
  <td><div class="msk-name-cell"><a class="msk-name-main msk-name-link" href="{{ $viewHref }}" data-msk-view-open="{{ $participantId }}" data-msk-view-href="{{ $viewHref }}" aria-label="Lihat detail {{ $fullName }}">{{ $fullName }}</a><span class="msk-name-sub">{{ $nameSubLabel }}</span></div></td>
  <td><div class="msk-month-cell"><span class="msk-month-main">{{ $mskMonthLabel }}</span><span class="msk-month-sub">Batch pembinaan</span></div></td>
  <td><div class="msk-progress-cell"><div class="msk-progress-top"><span class="msk-progress-value">{{ $progressLabel }}</span><span class="msk-progress-percent">{{ (string) $progressPercent }}%</span></div><div class="msk-progress-bar" aria-hidden="true"><span style="width:{{ (string) $progressPercent }}%"></span></div><div class="msk-progress-meta">{{ $progressMeta }}</div></div></td>
  <td>{!! $statusBadge !!}</td>
  <td>{!! $waHtml !!}</td>
</tr>
