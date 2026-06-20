@if ($paginator->hasPages())
  <nav class="rec-pagination" aria-label="Navigasi halaman">
    @if ($paginator->onFirstPage())
      <span class="btn tiny ghost is-disabled" aria-disabled="true">Sebelumnya</span>
    @else
      <a class="btn tiny ghost" href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>
    @endif
    <span class="rec-pagination-status">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())
      <a class="btn tiny ghost" href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>
    @else
      <span class="btn tiny ghost is-disabled" aria-disabled="true">Berikutnya</span>
    @endif
  </nav>
@endif
