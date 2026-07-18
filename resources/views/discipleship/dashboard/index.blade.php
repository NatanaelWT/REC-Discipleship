<section
  class="discipleship-tab-panel discipleship-workspace__panel discipleship-dashboard-panel"
  id="discipleship-tabpanel-dashboard"
  role="tabpanel"
  aria-labelledby="discipleship-tab-dashboard"
  tabindex="0"
  data-discipleship-tab-panel
  data-discipleship-dashboard-panel
  data-tab-key="dashboard"
  data-page-title="{{ $pageTitle ?? 'Dashboard Pemuridan' }}"
  data-tree-group-detail-url-template="{{ route('discipleship.dashboard.groups.detail', ['group' => '__id__']) }}"
>
<?php

$branchParam = $allBranches ? 'all' : ($selectedBranchId ?? 'all');
$formatPercent = static function (float $value): string {
    $label = number_format(max(0, min(100, $value)), 1, ',', '.');

    return str_ends_with($label, ',0') ? substr($label, 0, -2) : $label;
};
?>

@if (request()->has('msk_session_saved'))
  <div class="alert success">Progress sesi MSK berhasil diperbarui.</div>
@endif
@if (request()->has('converted'))
  <div class="alert success">Peserta yang menyelesaikan 12 sesi otomatis ditambahkan ke data pemuridan.</div>
@endif
@if (request()->query('error') === 'invalid_msk_participant')
  <div class="alert danger">Data peserta kelas MSK tidak ditemukan.</div>
@endif
@if (! $centralReadOnly)
  <?php render_pemuridan_import_feedback(); ?>
@endif

@include('discipleship.partials.page-header', [
    'header' => [
        'kicker' => 'Dashboard Pemuridan',
        'title' => 'Monitor Pemuridan',
        'description' => 'Ringkasan capaian target, kesehatan kelompok, dan area yang perlu segera ditindaklanjuti.',
        'attributes' => [
            'class' => 'discipleship-dashboard-hero-card',
            'data-discipleship-dashboard-header' => true,
        ],
        'title_content' => [
            'partial' => 'discipleship.dashboard.partials.header-title',
            'data' => compact('selectedBranchLabel'),
        ],
        'after_copy' => [
            'partial' => 'discipleship.dashboard.partials.header-actions',
        ],
        'aside' => [
            'partial' => 'discipleship.dashboard.partials.header-summary',
            'data' => compact('overallProgress', 'formatPercent'),
        ],
    ],
])

<section class="card discipleship-dashboard-progress-card">
  <div class="card-row discipleship-dashboard-section-head"><h2>Achievement Target</h2><span class="badge muted">Ringkasan terkini</span></div>
  <div class="journey-progress-grid journey-progress-grid-standalone">
    @foreach ($journeyProgressRows as $row)
      <?php $percent = $row['target'] > 0 ? min(100, ($row['value'] / $row['target']) * 100) : 0; ?>
      <div class="journey-progress-chip">
        <div class="journey-progress-ring" style="--pct:{{ $percent }};--ring-color:{{ $row['color'] }};"><span>{{ $formatPercent($percent) }}%</span></div>
        <div class="journey-progress-copy"><div class="journey-progress-label">{{ $row['label'] }}</div><div class="journey-progress-value">{{ number_format($row['value'], 0, ',', '.') }} / {{ number_format($row['target'], 0, ',', '.') }}</div></div>
      </div>
    @endforeach
  </div>

  <div class="discipleship-dashboard-data-stats">
    @foreach ($summaryStats as $stat)
      <article class="discipleship-dashboard-data-stat {{ $stat['tone'] }}">
        <span class="discipleship-dashboard-data-stat-label">{{ $stat['label'] }}</span>
        <strong class="discipleship-dashboard-data-stat-value">{{ number_format($stat['value'], 0, ',', '.') }}</strong>
        <span class="discipleship-dashboard-data-stat-sub">{{ $stat['sub'] }}</span>
      </article>
    @endforeach
  </div>

  <div class="journey-progress-grid journey-progress-grid-standalone discipleship-dashboard-group-progress">
    @foreach ($groupProgressRows as $row)
      <?php $percent = $row['target'] > 0 ? min(100, ($row['value'] / $row['target']) * 100) : 0; ?>
      <div class="journey-progress-chip">
        <div class="journey-progress-ring" style="--pct:{{ $percent }};--ring-color:{{ $row['color'] }};"><span>{{ $formatPercent($percent) }}%</span></div>
        <div class="journey-progress-copy"><div class="journey-progress-label">{{ $row['label'] }}</div><div class="journey-progress-value">{{ number_format($row['value'], 0, ',', '.') }} / {{ number_format($row['target'], 0, ',', '.') }}</div></div>
      </div>
    @endforeach
  </div>
</section>

@if ($allBranches)
  <section class="card dashboard-lazy-shell" data-dashboard-section data-section-url="{{ route('discipleship.dashboard.section', ['section' => 'branch-breakdown', 'branch_id' => $branchParam]) }}">
    <div class="dashboard-section-loading" role="status"><span class="dashboard-loading-bar"></span><span>Memuat rincian tiap cabang...</span></div>
  </section>
@endif

<section class="member-pie-grid discipleship-progress-overdue-grid">
  <article class="card member-pie-card discipleship-overdue-card is-msk dashboard-lazy-shell" data-dashboard-section data-section-url="{{ route('discipleship.dashboard.section', ['section' => 'incomplete-msk', 'branch_id' => $branchParam]) }}" data-auto-edit-id="{{ request()->query('edit_msk_sessions', '') }}">
    <div class="discipleship-overdue-head"><div><span class="discipleship-overdue-kicker">Tindak Lanjut MSK</span><h2>Belum Selesai MSK</h2><p>Peserta yang masih perlu pemantauan lanjutan.</p></div></div>
    <div class="dashboard-section-loading" role="status"><span class="dashboard-loading-bar"></span><span>Memuat peserta...</span></div>
  </article>
  <article class="card member-pie-card discipleship-overdue-card is-report dashboard-lazy-shell" data-dashboard-section data-section-url="{{ route('discipleship.dashboard.section', ['section' => 'overdue-groups', 'branch_id' => $branchParam]) }}">
    <div class="discipleship-overdue-head"><div><span class="discipleship-overdue-kicker">Tindak Lanjut Jurnal Temu DG</span><h2>Belum Lapor DG 30 Hari Terakhir</h2><p>Kelompok yang belum mengirim laporan dalam 30 hari terakhir.</p></div></div>
    <div class="dashboard-section-loading" role="status"><span class="dashboard-loading-bar"></span><span>Memuat kelompok...</span></div>
  </article>
</section>

@if (! $centralReadOnly)
  @include('partials.modal', [
      'id' => 'discipleship-msk-edit-modal',
      'size' => 'standard',
      'modalAttrs' => ['data-msk-edit-modal' => true],
      'title' => 'Edit Sesi MSK',
      'titleAttrs' => ['data-msk-edit-title' => true],
      'closeAttrs' => ['data-msk-edit-close' => true],
      'bodyAttrs' => ['data-msk-edit-body' => true],
  ])
@endif

@include('discipleship.people-tree.partials.group-history-modal', [
    'centralReadOnly' => true,
    'groupHistoryModalId' => 'dashboard-group-detail-modal',
])

</section>
