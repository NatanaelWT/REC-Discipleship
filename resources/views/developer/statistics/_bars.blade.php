<section class="card analytics-breakdown-card {{ $wide ?? false ? 'is-wide' : '' }}">
  <div class="analytics-section-head">
    <div><span>{{ $kicker ?? 'Distribusi' }}</span><h3>{{ $title }}</h3></div>
    @if (! empty($note))<small>{{ $note }}</small>@endif
  </div>
  @php
    $rowCollection = collect($rows)->values();
    $visibleLimit = max(1, (int) ($initialLimit ?? 5));
    $visibleRows = $rowCollection->take($visibleLimit);
    $remainingRows = $rowCollection->slice($visibleLimit)->values();
    $rowMax = max(1, $rowCollection->max('count') ?? 1);
  @endphp
  <div class="analytics-bars" data-visible-rows="{{ $visibleRows->count() }}">
    @forelse ($visibleRows as $row)
      @include('developer.statistics._bar-row', ['row' => $row, 'rowMax' => $rowMax])
    @empty
      <div class="analytics-empty">Belum ada data pada periode ini.</div>
    @endforelse
  </div>
  @if ($remainingRows->isNotEmpty())
    <details class="analytics-more">
      <summary><span>Lihat {{ $remainingRows->count() }} lainnya</span><span class="analytics-disclosure-icon" aria-hidden="true"></span></summary>
      <div class="analytics-bars analytics-bars-extra">
        @foreach ($remainingRows as $row)
          @include('developer.statistics._bar-row', ['row' => $row, 'rowMax' => $rowMax])
        @endforeach
      </div>
    </details>
  @endif
</section>
