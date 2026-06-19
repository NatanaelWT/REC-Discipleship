@extends('layouts.rec_app', [
    'title' => 'Target DG & MSK',
    'settings' => $settings,
    'currentPage' => 'discipleship_targets',
    'showTitle' => false,
])

@section('content')
    @if ($saved && ! $centralReadOnly)
        <div class="alert success">Target DG & MSK berhasil disimpan.</div>
    @endif

    @if ($centralReadOnly)
        <section class="card">
          <div class="card-row">
            <h2>Target DG & MSK Semua Cabang</h2>
            <span class="badge muted">Mode lihat saja (Pusat)</span>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Cabang</th><th>Target Peserta Kamp GAP</th><th>Target Selesai MSK</th><th>Target Selesai DG 1</th><th>Target Selesai DG 2</th><th>Target Selesai DG 3</th></tr></thead>
              <tbody>
                @foreach ($branchTargetRows as $branchRow)
                  @php
                      $branchTargets = is_array($branchRow['targets'] ?? null) ? $branchRow['targets'] : [];
                      $branchTargetPeople = max(0, (int) ($branchTargets['dg_total_people'] ?? 50));
                      $branchTargetMsk = max(0, (int) ($branchTargets['msk_completed'] ?? 50));
                      $branchTargetDg1 = max(0, (int) ($branchTargets['dg1_people'] ?? 50));
                      $branchTargetDg2 = max(0, (int) ($branchTargets['dg2_people'] ?? 50));
                      $branchTargetDg3 = max(0, (int) ($branchTargets['dg3_people'] ?? 50));
                  @endphp
                  <tr><td>{{ (string) ($branchRow['branch_label'] ?? '') }}</td><td>{{ number_format($branchTargetPeople, 0, ',', '.') }}</td><td>{{ number_format($branchTargetMsk, 0, ',', '.') }}</td><td>{{ number_format($branchTargetDg1, 0, ',', '.') }}</td><td>{{ number_format($branchTargetDg2, 0, ',', '.') }}</td><td>{{ number_format($branchTargetDg3, 0, ',', '.') }}</td></tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </section>
    @else
        @php
            $targetDgTotalPeople = max(0, (int) ($targets['dg_total_people'] ?? 50));
            $targetMskCompleted = max(0, (int) ($targets['msk_completed'] ?? 50));
            $targetDg1People = max(0, (int) ($targets['dg1_people'] ?? 50));
            $targetDg2People = max(0, (int) ($targets['dg2_people'] ?? 50));
            $targetDg3People = max(0, (int) ($targets['dg3_people'] ?? 50));

            $targetCards = [
                [
                    'class' => 'is-msk',
                    'eyebrow' => 'MSK',
                    'label' => 'Target Total Selesai MSK',
                    'name' => 'target_msk_completed',
                    'value' => $targetMskCompleted,
                    'hint' => 'Jumlah peserta yang ditargetkan menuntaskan proses MSK.',
                ],
                [
                    'class' => 'is-dg1',
                    'eyebrow' => 'DG 1',
                    'label' => 'Target Selesai DG 1',
                    'name' => 'target_dg1_people',
                    'value' => $targetDg1People,
                    'hint' => 'Jumlah peserta yang ditargetkan menuntaskan DG 1.',
                ],
                [
                    'class' => 'is-total',
                    'eyebrow' => 'Kamp GAP',
                    'label' => 'Target Peserta Kamp GAP',
                    'name' => 'target_dg_total_people',
                    'value' => $targetDgTotalPeople,
                    'hint' => 'Jumlah peserta yang ditargetkan hadir di Kamp GAP.',
                ],
                [
                    'class' => 'is-dg2',
                    'eyebrow' => 'DG 2',
                    'label' => 'Target Selesai DG 2',
                    'name' => 'target_dg2_people',
                    'value' => $targetDg2People,
                    'hint' => 'Jumlah peserta yang ditargetkan menuntaskan DG 2.',
                ],
                [
                    'class' => 'is-dg3',
                    'eyebrow' => 'DG 3',
                    'label' => 'Target Selesai DG 3',
                    'name' => 'target_dg3_people',
                    'value' => $targetDg3People,
                    'hint' => 'Jumlah peserta yang ditargetkan menuntaskan DG 3.',
                ],
            ];
        @endphp

        <section class="card settings-target-card">
          <div class="settings-target-hero">
            <div class="settings-target-copy">
              <span class="settings-target-kicker">Target DG & MSK</span>
              <h2>Cabang {{ $activeBranchLabel }}</h2>
              <p>Tetapkan sasaran DG dan MSK untuk cabang ini agar pemantauan pertumbuhan lebih jelas, terukur, dan konsisten.</p>
            </div>
            <div class="settings-target-meta">
              <span class="settings-target-badge is-branch">Cabang {{ $activeBranchLabel }}</span>
              <span class="settings-target-badge">{{ app_church_name() }}</span>
            </div>
          </div>
          <form method="post" action="{{ route('discipleship.targets.update') }}" class="settings-target-form">
            @csrf
            <input type="hidden" name="action" value="save_discipleship_targets">
            <div class="settings-target-grid">
              @foreach ($targetCards as $targetCard)
                @php
                    $cardClass = trim((string) ($targetCard['class'] ?? ''));
                    $cardEyebrow = trim((string) ($targetCard['eyebrow'] ?? ''));
                    $cardLabel = trim((string) ($targetCard['label'] ?? 'Target'));
                    $cardName = trim((string) ($targetCard['name'] ?? ''));
                    $cardValue = max(0, (int) ($targetCard['value'] ?? 0));
                    $cardHint = trim((string) ($targetCard['hint'] ?? ''));
                @endphp
                <label class="settings-target-field {{ $cardClass }}">
                  <span class="settings-target-field-top">
                    <span class="settings-target-field-eyebrow">{{ $cardEyebrow }}</span>
                    <span class="settings-target-field-preview">{{ number_format($cardValue, 0, ',', '.') }}</span>
                  </span>
                  <span class="settings-target-field-title">{{ $cardLabel }}</span>
                  @if ($cardHint !== '')
                    <span class="settings-target-field-hint">{{ $cardHint }}</span>
                  @endif
                  <input type="number" name="{{ $cardName }}" min="0" max="1000000" value="{{ (string) $cardValue }}" required>
                </label>
              @endforeach
            </div>
            <div class="form-actions settings-target-actions">
              <button class="btn" type="submit">Simpan Target</button>
            </div>
          </form>
        </section>
    @endif
@endsection
