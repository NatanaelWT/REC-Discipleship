<div class="discipleship-page-header__filter">
  <select class="search groups-status-filter dg-recap-progress-filter" aria-label="Filter progres rekap laporan DG" data-filter="dg-recap-summary-table" data-filter-role="recap-progress">
    <option value="all">Semua Progress</option>
    <option value="dg1">DG 1</option>
    <option value="dg2">DG 2</option>
    <option value="dg3">DG 3</option>
  </select>
</div>

<div class="discipleship-page-header__search">
  <?php render_table_search_input('dg-recap-summary-table', 'Cari pemimpin, peserta, progres, atau laporan...', 'search groups-table-search dg-recap-summary-search', 'Cari rekap laporan DG', '  '); ?>
</div>
