@if (request()->filled('branch_id'))
  <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
@endif

@php
    $spiritualJourneyFilterCounts = is_array($spiritualJourneyFilterCounts ?? null) ? $spiritualJourneyFilterCounts : [];
@endphp

<div class="discipleship-page-header__control-group">
  <div class="discipleship-page-header__filter">
    <select name="journey_filter" class="search journey-status-filter" aria-label="Filter spiritual journey" data-spiritual-journey-filter-input>
      @foreach ($journeyFilterOptions as $filterValue => $filterLabel)
        <option value="{{ $filterValue }}" @selected($journeyFilter === $filterValue)>{{ $filterLabel }} ({{ (string) ((int) ($spiritualJourneyFilterCounts[$filterValue] ?? 0)) }})</option>
      @endforeach
    </select>
  </div>
  <button class="btn tiny ghost people-export-button journey-export-button" type="submit" formaction="{{ route('discipleship.spiritual-journey.export') }}" formmethod="get" data-live-search-external-submit>
    <?php echo icon_svg('download'); ?>
    <span>Export</span>
  </button>
</div>

<div class="discipleship-page-header__search">
  <input type="search" name="q" value="{{ $spiritualJourneySearch }}" class="search journey-table-search" placeholder="Cari peserta spiritual journey..." aria-label="Cari peserta spiritual journey" autocomplete="off" data-spiritual-journey-search-input>
</div>
