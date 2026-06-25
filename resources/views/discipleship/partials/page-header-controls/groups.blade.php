@if (request()->filled('branch_id'))
  <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
@endif

<div class="discipleship-page-header__filter">
  <select name="status" class="search groups-status-filter" aria-label="Filter status kelompok DG" onchange="this.form.submit()">
    <option value="all" @selected($groupsStatusFilter === 'all')>Semua Kelompok</option>
    <option value="active" @selected($groupsStatusFilter === 'active')>Kelompok Aktif</option>
    <option value="inactive" @selected($groupsStatusFilter === 'inactive')>Kelompok Tidak Aktif</option>
  </select>
</div>

<div class="discipleship-page-header__search">
  <input type="search" name="q" value="{{ $groupsSearch }}" class="search groups-table-search" placeholder="Cari leader, pendamping, progres, atau peserta..." aria-label="Cari Kelompok DG" autocomplete="off" data-auto-submit-search-input @if ($groupsSearch !== '') autofocus @endif>
</div>
