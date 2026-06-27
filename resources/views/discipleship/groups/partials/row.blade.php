@php
    $rowClass = trim((string) ($groupRow['row_class'] ?? ''));
    $rowStatus = trim((string) ($groupRow['row_status'] ?? 'active'));
    $rowProgress = trim((string) ($groupRow['row_progress'] ?? 'none'));
    $leaderName = trim((string) ($groupRow['leader_name'] ?? '-'));
    if ($leaderName === '') {
        $leaderName = '-';
    }
    $leaderSummary = trim((string) ($groupRow['leader_summary'] ?? 'Tanpa pendamping'));
    if ($leaderSummary === '') {
        $leaderSummary = 'Tanpa pendamping';
    }
    $groupStatusClass = trim((string) ($groupRow['group_status_class'] ?? 'is-active'));
    $progressToneClass = trim((string) ($groupRow['progress_tone_class'] ?? 'is-neutral'));
    $progressLabel = trim((string) ($groupRow['progress_label'] ?? '-'));
    if ($progressLabel === '') {
        $progressLabel = '-';
    }
    $progressHelper = trim((string) ($groupRow['progress_helper_text'] ?? ''));
    $memberSummary = trim((string) ($groupRow['member_summary'] ?? 'Belum ada peserta'));
    if ($memberSummary === '') {
        $memberSummary = 'Belum ada peserta';
    }
    $memberHelper = trim((string) ($groupRow['member_helper_text'] ?? ''));
@endphp
<tr @if ($rowClass !== '') class="{{ $rowClass }}" @endif data-group-status="{{ $rowStatus }}" data-group-progress="{{ $rowProgress }}" data-discipleship-groups-row>
  <td>
    <div class="group-name-cell">
      <div class="group-name-main">{{ $leaderName }}</div>
      <div class="group-name-sub">{{ $leaderSummary }}</div>
    </div>
  </td>
  <td>
    <div class="group-status-cell">
      <span class="group-status-badge {{ $groupStatusClass }}">{{ $rowStatus === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
    </div>
  </td>
  <td>
    <div class="group-progress-cell">
      <span class="group-progress-badge {{ $progressToneClass }}">{{ $progressLabel }}</span>
      <div class="group-progress-sub">{{ $progressHelper }}</div>
    </div>
  </td>
  <td>
    <div class="group-members-cell">
      <div class="group-members-main">{{ $memberSummary }}</div>
      <div class="group-members-sub">{{ $memberHelper }}</div>
    </div>
  </td>
</tr>
