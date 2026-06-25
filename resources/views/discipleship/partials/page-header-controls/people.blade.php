@if (request()->filled('branch_id'))
  <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
@endif

<div class="discipleship-page-header__control-group">
  <div class="discipleship-page-header__filter">
    <select name="progress" class="search people-status-filter" aria-label="Filter status progress anggota DG" onchange="this.form.submit()">
      <option value="all" @selected($peopleProgressFilter === 'all')>Semua Peserta</option>
      <option value="active_dg1" @selected($peopleProgressFilter === 'active_dg1')>Sedang DG 1</option>
      <option value="complete_dg1" @selected($peopleProgressFilter === 'complete_dg1')>Selesai DG 1</option>
      <option value="active_dg2" @selected($peopleProgressFilter === 'active_dg2')>Sedang DG 2</option>
      <option value="complete_dg2" @selected($peopleProgressFilter === 'complete_dg2')>Selesai DG 2</option>
      <option value="active_dg3" @selected($peopleProgressFilter === 'active_dg3')>Sedang DG 3</option>
      <option value="complete_dg3" @selected($peopleProgressFilter === 'complete_dg3')>Selesai DG 3</option>
    </select>
  </div>
  <button class="btn tiny ghost people-export-button" type="submit" formaction="{{ route('discipleship.people-list.export') }}" formmethod="get" data-live-search-external-submit>
    <?php echo icon_svg('download'); ?>
    <span>Export</span>
  </button>
</div>

<div class="discipleship-page-header__search">
  <input type="search" name="q" value="{{ $peopleSearch }}" class="search people-table-search" placeholder="Cari nama peserta DG..." aria-label="Cari daftar peserta DG" autocomplete="off" data-discipleship-people-search-input>
</div>
