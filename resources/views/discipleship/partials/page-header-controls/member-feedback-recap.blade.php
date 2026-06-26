<div class="discipleship-page-header__control-group member-feedback-recap-header-controls">
  <div class="discipleship-page-header__filter member-feedback-recap-header-filter">
    <select class="search member-feedback-recap-filter" aria-label="Filter sesi feedback anggota" data-filter="member-feedback-recap-group-table" data-filter-role="member-feedback-session">
      @foreach (($filters['sessions'] ?? []) as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
      @endforeach
    </select>
  </div>

  <div class="discipleship-page-header__filter member-feedback-recap-header-filter">
    <select class="search member-feedback-recap-filter" aria-label="Filter progress feedback anggota" data-filter="member-feedback-recap-group-table" data-filter-role="member-feedback-progress">
      @foreach (($filters['progress'] ?? []) as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
      @endforeach
    </select>
  </div>
</div>

<div class="discipleship-page-header__search member-feedback-recap-header-search">
  <?php render_table_search_input('member-feedback-recap-group-table', 'Cari pemimpin, kelompok, cabang, pengisi, atau catatan...', 'search groups-table-search member-feedback-recap-search', 'Cari rekap feedback anggota', '  '); ?>
</div>
