@php
  $cursorItemLabel = $itemLabel ?? 'data';
  $cursorCount = isset($pagination) ? (int) ($pagination['count'] ?? 0) : $paginator->count();
  $newerUrl = isset($pagination) ? ($pagination['newer_url'] ?? null) : $paginator->previousPageUrl();
  $olderUrl = isset($pagination) ? ($pagination['older_url'] ?? null) : $paginator->nextPageUrl();
@endphp

<nav class="activity-pagination" data-developer-cursor-pagination aria-label="Navigasi {{ $cursorItemLabel }}">
  @if ($newerUrl)
    <a class="btn tiny ghost developer-cursor-button" href="{{ $newerUrl }}" rel="prev">
      @include('developer._icon', ['name' => 'arrow-left'])
      <span>Lebih baru</span>
    </a>
  @else
    <span class="btn tiny ghost developer-cursor-button is-disabled" aria-disabled="true">
      @include('developer._icon', ['name' => 'arrow-left'])
      <span>Lebih baru</span>
    </span>
  @endif

  <span class="developer-cursor-status" aria-label="{{ $cursorCount }} {{ $cursorItemLabel }} pada halaman ini"><strong>{{ number_format($cursorCount, 0, ',', '.') }}</strong> {{ $cursorItemLabel }} pada halaman ini</span>

  @if ($olderUrl)
    <a class="btn tiny ghost developer-cursor-button" href="{{ $olderUrl }}" rel="next">
      <span>Lebih lama</span>
      @include('developer._icon', ['name' => 'arrow-right'])
    </a>
  @else
    <span class="btn tiny ghost developer-cursor-button is-disabled" aria-disabled="true">
      <span>Lebih lama</span>
      @include('developer._icon', ['name' => 'arrow-right'])
    </span>
  @endif
</nav>
