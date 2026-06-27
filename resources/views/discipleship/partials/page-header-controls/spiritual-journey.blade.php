@if (request()->filled('branch_id'))
  <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
@endif

<div class="discipleship-page-header__filter">
  <select name="journey_filter" class="search journey-status-filter" aria-label="Filter spiritual journey" data-spiritual-journey-filter-input>
    @foreach ($journeyFilterOptions as $filterValue => $filterLabel)
      <option value="{{ $filterValue }}" @selected($journeyFilter === $filterValue)>{{ $filterLabel }}</option>
    @endforeach
  </select>
</div>

<div class="discipleship-page-header__search">
  <input type="search" name="q" value="{{ $spiritualJourneySearch }}" class="search journey-table-search" placeholder="Cari peserta spiritual journey..." aria-label="Cari peserta spiritual journey" autocomplete="off" data-spiritual-journey-search-input>
</div>
