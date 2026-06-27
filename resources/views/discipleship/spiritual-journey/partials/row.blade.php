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
    $dg1Class = $hasCompletedDg1 ? 'is-dg1' : 'is-muted';
    $dg2Class = $hasCompletedDg2 ? 'is-dg2' : 'is-muted';
    $dg3Class = $hasCompletedDg3 ? 'is-dg3' : 'is-muted';
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
      <span class="journey-track-badge {{ $dg1Class }}">DG 1</span>
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
      <span class="journey-track-badge {{ $dg2Class }}">DG 2</span>
      <span class="journey-track-badge {{ $dg3Class }}">DG 3</span>
    </div>
  </td>
</tr>
