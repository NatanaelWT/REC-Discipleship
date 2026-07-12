@if (request()->filled('branch_id'))
  <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
@endif

@php
    $groupsStatusFilterCounts = is_array($groupsStatusFilterCounts ?? null) ? $groupsStatusFilterCounts : [];
    $groupsStatusOptions = [
        'all' => 'Semua Kelompok',
        'active' => 'Kelompok Aktif',
        'inactive' => 'Kelompok Tidak Aktif',
    ];
@endphp

<div class="discipleship-page-header__filter">
  <select name="status" class="search groups-status-filter" aria-label="Filter status kelompok DG" data-discipleship-groups-status-input>
    @foreach ($groupsStatusOptions as $statusValue => $statusLabel)
      <option value="{{ $statusValue }}" @selected($groupsStatusFilter === $statusValue)>{{ $statusLabel }} ({{ (string) ((int) ($groupsStatusFilterCounts[$statusValue] ?? 0)) }})</option>
    @endforeach
  </select>
</div>

<div class="discipleship-page-header__search">
  <input type="search" name="q" value="{{ $groupsSearch }}" class="search groups-table-search" placeholder="Cari leader, pendamping, progres, atau peserta..." aria-label="Cari Kelompok DG" autocomplete="off" data-discipleship-groups-search-input @if ($groupsSearch !== '') autofocus @endif>
</div>
