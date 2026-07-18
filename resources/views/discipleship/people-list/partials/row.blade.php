@php
    $rowFilterState = trim((string) ($row['row_filter_state'] ?? 'none'));
    $rowProgressKey = trim((string) ($row['row_progress_key'] ?? 'none'));
    $name = trim((string) ($row['name'] ?? '-'));
    if ($name === '') {
        $name = '-';
    }
    $roleLabel = trim((string) ($row['role_label'] ?? 'Anggota'));
    if ($roleLabel === '') {
        $roleLabel = 'Anggota';
    }
    $roleToneClass = trim((string) ($row['role_tone_class'] ?? 'is-member'));
    $progressSteps = is_array($row['progress_steps'] ?? null) ? $row['progress_steps'] : [];
@endphp
<tr data-people-filter="{{ $rowFilterState }}" data-people-progress="{{ $rowProgressKey }}" data-discipleship-people-search-row data-search-text="{{ $name }}">
  <td>
    <div class="people-name-cell">
      <div class="people-name-main">{{ $name }}</div>
    </div>
  </td>
  <td>
    <div class="people-role-cell">
      <span class="people-role-badge {{ $roleToneClass }}">{{ $roleLabel }}</span>
    </div>
  </td>
  <td>
    <div class="people-progress-cell">
      <div class="people-progress-track" aria-label="Progress DG {{ $name }}">
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
    </div>
  </td>
</tr>
