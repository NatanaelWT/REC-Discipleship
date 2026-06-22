@php
  $cursorItemLabel = $itemLabel ?? 'data';
  $cursorCount = $paginator->count();
@endphp

<nav class="activity-pagination" data-developer-cursor-pagination aria-label="Navigasi {{ $cursorItemLabel }}">
  @if ($paginator->previousPageUrl())
    <a class="btn tiny ghost developer-cursor-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">
      @include('developer._icon', ['name' => 'arrow-left'])
      <span>Sebelumnya</span>
    </a>
  @else
    <span class="btn tiny ghost developer-cursor-button is-disabled" aria-disabled="true">
      @include('developer._icon', ['name' => 'arrow-left'])
      <span>Sebelumnya</span>
    </span>
  @endif

  <span class="developer-cursor-status" aria-label="{{ $cursorCount }} {{ $cursorItemLabel }} pada halaman ini"><strong>{{ number_format($cursorCount, 0, ',', '.') }}</strong> {{ $cursorItemLabel }} pada halaman ini</span>

  @if ($paginator->nextPageUrl())
    <a class="btn tiny ghost developer-cursor-button" href="{{ $paginator->nextPageUrl() }}" rel="next">
      <span>Berikutnya</span>
      @include('developer._icon', ['name' => 'arrow-right'])
    </a>
  @else
    <span class="btn tiny ghost developer-cursor-button is-disabled" aria-disabled="true">
      <span>Berikutnya</span>
      @include('developer._icon', ['name' => 'arrow-right'])
    </span>
  @endif
</nav>
