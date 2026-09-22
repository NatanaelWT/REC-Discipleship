@php
    $recapProgressFilterCounts = is_array($recapProgressFilterCounts ?? null) ? $recapProgressFilterCounts : [];
    $recapProgressOptions = [
        'all' => 'Semua Progress',
        'dg1' => 'DG 1',
        'dg2' => 'DG 2',
        'dg3' => 'DG 3',
    ];
@endphp

<div class="discipleship-page-header__control-group dg-recap-header-controls">
  <div class="discipleship-page-header__filter dg-recap-header-filter">
    <select class="search groups-status-filter dg-recap-progress-filter" aria-label="Filter progres rekap laporan DG" data-filter="dg-recap-summary-table" data-filter-role="recap-progress">
      @foreach ($recapProgressOptions as $progressValue => $progressLabel)
        <option value="{{ $progressValue }}">{{ $progressLabel }} ({{ (string) ((int) ($recapProgressFilterCounts[$progressValue] ?? 0)) }})</option>
      @endforeach
    </select>
  </div>
  <a class="btn tiny ghost dg-meeting-report-export-button dg-recap-export-button" href="{{ route('discipleship.reports-recap.export', request()->only(['branch_id', 'rekap_cabang'])) }}">
    <?php echo icon_svg('download'); ?>
    <span>Export Excel</span>
  </a>
</div>

<div class="discipleship-page-header__search dg-recap-header-search">
  <?php render_table_search_input('dg-recap-summary-table', 'Cari pemimpin, peserta, progres, atau laporan...', 'search groups-table-search dg-recap-summary-search', 'Cari rekap laporan DG', '  '); ?>
</div>
