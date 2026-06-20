@if ($paginator->hasPages())
  <nav class="rec-pagination dashboard-section-pagination" aria-label="Navigasi halaman">
    @if ($paginator->onFirstPage())<span class="btn tiny ghost is-disabled">Sebelumnya</span>@else<a class="btn tiny ghost" href="{{ $paginator->previousPageUrl() }}" data-dashboard-section-link>Sebelumnya</a>@endif
    <span>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())<a class="btn tiny ghost" href="{{ $paginator->nextPageUrl() }}" data-dashboard-section-link>Berikutnya</a>@else<span class="btn tiny ghost is-disabled">Berikutnya</span>@endif
  </nav>
@endif
