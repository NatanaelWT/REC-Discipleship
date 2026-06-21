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

        $allBranchTargetSections = [
            [
                'key' => 'msk_completed',
                'class' => 'is-msk',
                'eyebrow' => 'MSK',
                'label' => 'Target Total Selesai MSK',
                'hint' => 'Target peserta yang menuntaskan proses MSK pada setiap cabang.',
            ],
            [
                'key' => 'dg1_people',
                'class' => 'is-dg1',
                'eyebrow' => 'DG 1',
                'label' => 'Target Selesai DG 1',
                'hint' => 'Target peserta yang menuntaskan DG 1 pada setiap cabang.',
            ],
            [
                'key' => 'dg_total_people',
                'class' => 'is-total',
                'eyebrow' => 'Kamp GAP',
                'label' => 'Target Peserta Kamp GAP',
                'hint' => 'Target peserta Kamp GAP pada setiap cabang.',
            ],
            [
                'key' => 'dg2_people',
                'class' => 'is-dg2',
                'eyebrow' => 'DG 2',
                'label' => 'Target Selesai DG 2',
                'hint' => 'Target peserta yang menuntaskan DG 2 pada setiap cabang.',
            ],
            [
                'key' => 'dg3_people',
                'class' => 'is-dg3',
                'eyebrow' => 'DG 3',
                'label' => 'Target Selesai DG 3',
                'hint' => 'Target peserta yang menuntaskan DG 3 pada setiap cabang.',
            ],
        ];
    @endphp

    @if ($showAllBranches)
      <section class="card settings-target-card settings-target-overview">
        <div class="settings-target-hero">
          <div class="settings-target-copy">
            <span class="settings-target-kicker">Target DG & MSK</span>
            <h2>Semua Cabang</h2>
            <p>Bandingkan target setiap cabang berdasarkan tahap pemuridan dalam mode lihat saja.</p>
          </div>
          <div class="settings-target-meta">
            <span class="settings-target-badge is-branch">Semua Cabang</span>
            <span class="settings-target-badge is-readonly">Hanya Lihat</span>
          </div>
        </div>

        <div class="settings-target-metric-list">
          @foreach ($allBranchTargetSections as $targetSection)
            @php
                $sectionKey = trim((string) ($targetSection['key'] ?? ''));
                $sectionClass = trim((string) ($targetSection['class'] ?? ''));
            @endphp
            <section class="settings-target-metric-section {{ $sectionClass }}" data-target-section="{{ $sectionKey }}">
              <div class="settings-target-metric-head">
                <span class="settings-target-field-eyebrow">{{ (string) ($targetSection['eyebrow'] ?? '') }}</span>
                <div>
                  <h3>{{ (string) ($targetSection['label'] ?? 'Target') }}</h3>
                  <p>{{ (string) ($targetSection['hint'] ?? '') }}</p>
                </div>
              </div>
              <div class="settings-target-branch-grid">
                @foreach ($branchTargetRows as $branchRow)
                  @php
                      $branchTargets = is_array($branchRow['targets'] ?? null) ? $branchRow['targets'] : [];
                      $branchValue = max(0, (int) ($branchTargets[$sectionKey] ?? 50));
                  @endphp
                  <article class="settings-target-branch-card" data-branch-code="{{ (string) ($branchRow['branch_code'] ?? '') }}">
                    <span>{{ (string) ($branchRow['branch_label'] ?? '') }}</span>
                    <strong>{{ number_format($branchValue, 0, ',', '.') }}</strong>
                  </article>
                @endforeach
              </div>
            </section>
          @endforeach
        </div>
      </section>
    @else
    <section class="card settings-target-card">
      <div class="settings-target-hero">
        <div class="settings-target-copy">
          <span class="settings-target-kicker">Target DG & MSK</span>
          <h2>Cabang {{ $activeBranchLabel }}</h2>
          <p>
            @if ($centralReadOnly)
              Target DG dan MSK cabang terpilih ditampilkan dalam mode lihat saja.
            @else
              Tetapkan sasaran DG dan MSK untuk cabang ini agar pemantauan pertumbuhan lebih jelas, terukur, dan konsisten.
            @endif
          </p>
        </div>
        <div class="settings-target-meta">
          <span class="settings-target-badge is-branch">Cabang {{ $activeBranchLabel }}</span>
          @if ($centralReadOnly)
            <span class="settings-target-badge is-readonly">Hanya Lihat</span>
          @else
            <span class="settings-target-badge">{{ app_church_name() }}</span>
          @endif
        </div>
      </div>

      @if ($centralReadOnly)
        <div class="settings-target-form" aria-label="Target DG dan MSK Cabang {{ $activeBranchLabel }}">
      @else
        <form method="post" action="{{ route('discipleship.targets.update') }}" class="settings-target-form">
          @csrf
          <input type="hidden" name="action" value="save_discipleship_targets">
      @endif
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
              @if ($centralReadOnly)
                <article class="settings-target-field {{ $cardClass }} is-readonly">
              @else
                <label class="settings-target-field {{ $cardClass }}">
              @endif
                  <span class="settings-target-field-top">
                    <span class="settings-target-field-eyebrow">{{ $cardEyebrow }}</span>
                    <span class="settings-target-field-preview">{{ number_format($cardValue, 0, ',', '.') }}</span>
                  </span>
                  <span class="settings-target-field-title">{{ $cardLabel }}</span>
                  @if ($cardHint !== '')
                    <span class="settings-target-field-hint">{{ $cardHint }}</span>
                  @endif
                  @if ($centralReadOnly)
                    <span class="settings-target-field-value">{{ number_format($cardValue, 0, ',', '.') }}</span>
                  @else
                    <input type="number" name="{{ $cardName }}" min="0" max="1000000" value="{{ (string) $cardValue }}" required>
                  @endif
              @if ($centralReadOnly)
                </article>
              @else
                </label>
              @endif
            @endforeach
          </div>
          @unless ($centralReadOnly)
            <div class="form-actions settings-target-actions">
              <button class="btn" type="submit">Simpan Target</button>
            </div>
          @endunless
      @if ($centralReadOnly)
        </div>
      @else
        </form>
      @endif
    </section>
    @endif
@endsection
