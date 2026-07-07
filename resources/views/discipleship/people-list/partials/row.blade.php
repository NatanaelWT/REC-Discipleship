@php
    $rowFilterState = trim((string) ($row['row_filter_state'] ?? 'none'));
    $rowProgressKey = trim((string) ($row['row_progress_key'] ?? 'none'));
    $name = trim((string) ($row['name'] ?? '-'));
    if ($name === '') {
        $name = '-';
    }
    $parentSummary = trim((string) ($row['parent_summary'] ?? 'Belum terhubung ke pembina'));
    if ($parentSummary === '') {
        $parentSummary = 'Belum terhubung ke pembina';
    }
    $roleLabel = trim((string) ($row['role_label'] ?? 'Anggota'));
    if ($roleLabel === '') {
        $roleLabel = 'Anggota';
    }
    $roleToneClass = trim((string) ($row['role_tone_class'] ?? 'is-member'));
    $roleSubtitle = trim((string) ($row['role_subtitle'] ?? 'Belum ada kelompok aktif'));
    if ($roleSubtitle === '') {
        $roleSubtitle = 'Belum ada kelompok aktif';
    }
    $progressSteps = is_array($row['progress_steps'] ?? null) ? $row['progress_steps'] : [];
    $progressSummary = trim((string) ($row['progress_summary'] ?? 'Belum memulai DG'));
@endphp
<tr data-people-filter="{{ $rowFilterState }}" data-people-progress="{{ $rowProgressKey }}" data-discipleship-people-search-row data-search-text="{{ $name }}">
  <td>
    <div class="people-name-cell">
      <div class="people-name-main">{{ $name }}</div>
      <div class="people-name-sub">{{ $parentSummary }}</div>
    </div>
  </td>
  <td>
    <div class="people-role-cell">
      <span class="people-role-badge {{ $roleToneClass }}">{{ $roleLabel }}</span>
      <div class="people-role-sub">{{ $roleSubtitle }}</div>
    </div>
  </td>
  <td>
    <div class="people-progress-cell">
      <div class="people-progress-track" aria-label="{{ $progressSummary }}">
        @foreach ($progressSteps as $step)
          @php
              $stepState = trim((string) ($step['state'] ?? 'is-pending'));
              $stepLabel = trim((string) ($step['label'] ?? '-')) ?: '-';
              $stepStateLabel = trim((string) ($step['state_label'] ?? 'Belum')) ?: 'Belum';
          @endphp
          <span class="people-progress-step {{ $stepState }}">
            <span class="people-progress-step-marker" aria-hidden="true"></span>
            <span class="people-progress-step-copy">
              <strong>{{ $stepLabel }}</strong>
              <small>{{ $stepStateLabel }}</small>
            </span>
          </span>
        @endforeach
      </div>
      <span class="people-progress-summary">{{ $progressSummary }}</span>
    </div>
  </td>
</tr>
