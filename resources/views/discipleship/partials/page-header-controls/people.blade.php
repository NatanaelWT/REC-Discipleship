@if (request()->filled('branch_id'))
  <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
@endif

@php
    $peopleProgressFilterCounts = is_array($peopleProgressFilterCounts ?? null) ? $peopleProgressFilterCounts : [];
    $peopleProgressOptions = [
        'all' => 'Semua Peserta',
        'active_dg1' => 'Sedang DG 1',
        'complete_dg1' => 'Selesai DG 1',
        'active_dg2' => 'Sedang DG 2',
        'complete_dg2' => 'Selesai DG 2',
        'active_dg3' => 'Sedang DG 3',
        'complete_dg3' => 'Selesai DG 3',
    ];
@endphp

<div class="discipleship-page-header__control-group">
  <div class="discipleship-page-header__filter">
    <select name="progress" class="search people-status-filter" aria-label="Filter status progress anggota DG" data-discipleship-people-progress-input>
      @foreach ($peopleProgressOptions as $progressValue => $progressLabel)
        <option value="{{ $progressValue }}" @selected($peopleProgressFilter === $progressValue)>{{ $progressLabel }} ({{ (string) ((int) ($peopleProgressFilterCounts[$progressValue] ?? 0)) }})</option>
      @endforeach
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
