@php
    $journeyParticipantId = trim((string) ($row['id'] ?? ''));
    $journeyName = trim((string) ($row['name'] ?? '-'));
    if ($journeyName === '') {
        $journeyName = '-';
    }
    $journeyViewKey = trim((string) ($row['journey_view_key'] ?? ''));
    $mskProgressLabel = trim((string) ($row['msk_progress'] ?? '-'));
    $mskPercent = max(0, min(100, (int) ($row['msk_percent'] ?? 0)));
    $journeyBridgeStatus = normalize_journey_bridge_status((string) ($row['journey_bridge_status'] ?? 'belum'));
    $hasCompletedDg1 = ! empty($row['completed_dg1']);
    $hasCompletedDg2 = ! empty($row['completed_dg2']);
    $hasCompletedDg3 = ! empty($row['completed_dg3']);
    $fallbackDgSteps = [
        [
            'label' => 'DG 1',
            'state' => $hasCompletedDg1 ? 'is-complete' : 'is-pending',
            'state_label' => $hasCompletedDg1 ? 'Selesai' : 'Belum',
        ],
        [
            'label' => 'DG 2',
            'state' => $hasCompletedDg2 ? 'is-complete' : 'is-pending',
            'state_label' => $hasCompletedDg2 ? 'Selesai' : 'Belum',
        ],
        [
            'label' => 'DG 3',
            'state' => $hasCompletedDg3 ? 'is-complete' : 'is-pending',
            'state_label' => $hasCompletedDg3 ? 'Selesai' : 'Belum',
        ],
    ];
    $dgSteps = is_array($row['progress_steps'] ?? null) && count($row['progress_steps']) === 3
        ? array_values($row['progress_steps'])
        : $fallbackDgSteps;
    $allowedDgStepStates = ['is-complete', 'is-current', 'is-stopped', 'is-pending'];
    $bridgeOptions = [
        'belum' => 'Belum',
        'sudah_rg' => 'Sudah RG',
        'sudah_kgap' => 'Sudah KGAP',
        'ikut_keduanya' => 'Ikut Keduanya',
    ];
    $bridgeStateClass = 'is-bridge-none';
    if ($journeyBridgeStatus === 'sudah_rg') {
        $bridgeStateClass = 'is-bridge-rg';
    } elseif (in_array($journeyBridgeStatus, ['sudah_kgap', 'ikut_keduanya'], true)) {
        $bridgeStateClass = 'is-bridge-kgap';
    }
    $bridgeFormAction = $journeyParticipantId !== ''
        ? route('discipleship.spiritual-journey.bridge-status', ['participant' => $journeyParticipantId])
        : route('discipleship.spiritual-journey.bridge-status-form');
    $mskDone = $mskPercent >= 100;
    $mskBadgeClass = $mskDone ? 'journey-track-badge is-msk is-msk-done' : 'journey-track-badge is-msk is-msk-progress';
@endphp

<tr data-spiritual-journey-search-row data-search-text="{{ trim((string) ($row['search_text'] ?? $journeyName)) }}">
  <td>
    <div class="journey-name-cell">
      <div class="journey-name-main">{{ $journeyName }}</div>
      <div class="journey-name-sub">Peserta kelas MSK</div>
      <button class="note-link member-inline-trigger journey-history-trigger" type="button" data-spiritual-journey-view-open="{{ $journeyViewKey }}" aria-label="{{ 'Lihat profil '.$journeyName }}">Lihat profil</button>
    </div>
  </td>
  <td>
    <div class="journey-inline-track" title="Tahap DG dan MSK peserta">
      <span class="{{ $mskBadgeClass }}">MSK {{ $mskProgressLabel }}</span>
      @foreach ($dgSteps as $dgStepIndex => $dgStep)
        @php
            $dgStepLabel = trim((string) ($dgStep['label'] ?? 'DG '.($dgStepIndex + 1)));
            $dgStepState = trim((string) ($dgStep['state'] ?? 'is-pending'));
            if (! in_array($dgStepState, $allowedDgStepStates, true)) {
                $dgStepState = 'is-pending';
            }
            $dgStepStateLabel = trim((string) ($dgStep['state_label'] ?? ''));
            if ($dgStepStateLabel === '') {
                $dgStepStateLabel = match ($dgStepState) {
                    'is-complete' => 'Selesai',
                    'is-current' => 'Sedang',
                    'is-stopped' => 'Terhenti',
                    default => 'Belum',
                };
            }
        @endphp
        <span class="people-progress-step journey-dg-step {{ $dgStepState }}" aria-label="{{ $dgStepLabel.': '.$dgStepStateLabel }}">
          <span class="people-progress-step-marker" aria-hidden="true"></span>
          <span class="people-progress-step-copy">
            <strong>{{ $dgStepLabel }}</strong>
            <small>{{ $dgStepStateLabel }}</small>
          </span>
        </span>
        @if ($dgStepIndex === 0)
          <span class="journey-track-bridge">
            <form method="post" action="{{ $bridgeFormAction }}" class="journey-bridge-form">
              @csrf
              <input type="hidden" name="action" value="save_journey_bridge_status">
              <input type="hidden" name="id" value="{{ $journeyParticipantId }}">
              <select name="journey_bridge_status" class="journey-bridge-select {{ $bridgeStateClass }}" aria-label="{{ 'Status RG atau KGAP untuk '.$journeyName }}" @disabled(is_effective_central_discipleship_readonly()) @if (! is_effective_central_discipleship_readonly()) onchange="this.form.submit()" @endif>
                @foreach ($bridgeOptions as $bridgeValue => $bridgeLabel)
                  <option value="{{ $bridgeValue }}" @selected($journeyBridgeStatus === $bridgeValue)>{{ $bridgeLabel }}</option>
                @endforeach
              </select>
            </form>
          </span>
        @endif
      @endforeach
    </div>
  </td>
</tr>
