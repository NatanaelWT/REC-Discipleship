@extends('layouts.rec_app', [
    'title' => 'Statistik Website',
    'settings' => $settings,
    'currentPage' => 'developer_statistics',
    'bodyClass' => 'page-developer page-analytics',
    'showTitle' => false,
])

@section('content')
  @php
    $comparisonLabel = static function (?float $value): string {
        if ($value === null) return 'Baru';
        if ($value === 0.0) return 'Tetap';
        return ($value > 0 ? '+' : '').number_format($value, 1, ',', '.').'%';
    };
    $rangeLabels = ['today' => 'Hari ini', '7' => '7 hari', '30' => '30 hari', '90' => '90 hari', 'all' => 'Semua waktu', 'custom' => 'Khusus'];
    $chartWidth = 760;
    $chartHeight = 220;
    $chartPadX = 24;
    $chartPadY = 22;
    $chartMax = max(1, collect($trend)->max('count') ?? 1);
    $chartCount = max(1, count($trend));
    $chartPoints = collect($trend)->map(function (array $row, int $index) use ($chartWidth, $chartHeight, $chartPadX, $chartPadY, $chartMax, $chartCount): string {
        $x = $chartCount === 1 ? $chartWidth / 2 : $chartPadX + ($index * (($chartWidth - ($chartPadX * 2)) / ($chartCount - 1)));
        $y = $chartHeight - $chartPadY - (($row['count'] / $chartMax) * ($chartHeight - ($chartPadY * 2)));
        return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
    })->implode(' ');
    $visitorCollection = collect($visitors)->values();
    $visibleVisitors = $visitorCollection->take(10);
    $remainingVisitors = $visitorCollection->slice(10)->values();
  @endphp

  @include('developer._header', [
    'title' => 'Statistik Website',
    'description' => 'Analisis kunjungan anonim pada halaman publik, perangkat, bahasa, dan pola akses.',
    'eyebrow' => 'Public Analytics',
    'stats' => [
      ['label' => 'Periode Aktif', 'value' => $rangeLabels[$filters['range']] ?? 'Khusus'],
      ['label' => 'Page View', 'value' => number_format((int) ($summary['page_views'] ?? 0), 0, ',', '.')],
      ['label' => 'Pengunjung Unik', 'value' => number_format((int) ($summary['visitors'] ?? 0), 0, ',', '.')],
      ['label' => 'Sesi', 'value' => number_format((int) ($summary['sessions'] ?? 0), 0, ',', '.')],
    ],
  ])

  <section class="card analytics-filter-card developer-section-card">
    <div class="analytics-section-head">
      <div><span>Filter statistik</span><h2>Statistik Kunjungan Publik</h2></div>
      <a class="btn tiny ghost developer-link-button" href="{{ route('developer.statistics') }}">@include('developer._icon', ['name' => 'reset'])<span>Reset filter</span></a>
    </div>
    <p class="developer-muted">Hanya kunjungan anonim pada halaman publik, materi publik, dan halaman login yang dihitung. Aktivitas setelah login tersedia di menu Aktivitas.</p>
    @if (($filters['visitor'] ?? '') !== '')
      <div class="analytics-active-filter">Menampilkan satu pengunjung: <code>{{ substr($filters['visitor'], 0, 12) }}&hellip;</code></div>
    @endif
    <form method="get" action="{{ route('developer.statistics') }}" class="analytics-filter-grid">
      <label>Periode
        <select name="range" data-analytics-range>
          @foreach ($rangeLabels as $value => $label)<option value="{{ $value }}" @selected($filters['range'] === $value)>{{ $label }}</option>@endforeach
        </select>
      </label>
      <label>Dari<input type="date" name="from" value="{{ $filters['from'] }}"></label>
      <label>Sampai<input type="date" name="to" value="{{ $filters['to'] }}"></label>
      <label>Segmen<select name="segment"><option value="">Semua</option>@foreach (['publik' => 'Publik', 'login' => 'Login'] as $value => $label)<option value="{{ $value }}" @selected($filters['segment'] === $value)>{{ $label }}</option>@endforeach</select></label>
      <label>Bahasa<select name="language"><option value="">Semua</option>@foreach ($options['languages'] as $language)<option value="{{ $language->language_code }}" @selected($filters['language'] === $language->language_code)>{{ $language->language_name ?: $language->language_code }}</option>@endforeach</select></label>
      <label>Perangkat<select name="device"><option value="">Semua</option>@foreach (['desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet', 'tv' => 'TV', 'console' => 'Console', 'other' => 'Lainnya', 'unknown' => 'Tidak diketahui'] as $value => $label)<option value="{{ $value }}" @selected($filters['device'] === $value)>{{ $label }}</option>@endforeach</select></label>
      <label>Route<select name="route"><option value="">Semua</option>@foreach ($options['routes'] as $route)<option value="{{ $route }}" @selected($filters['route'] === $route)>{{ $route }}</option>@endforeach</select></label>
      @if ($filters['visitor'] !== '')<input type="hidden" name="visitor" value="{{ $filters['visitor'] }}">@endif
      <div class="analytics-filter-submit"><button class="btn" type="submit">@include('developer._icon', ['name' => 'filter'])<span>Terapkan</span></button></div>
    </form>
  </section>

  <section class="analytics-metric-grid" aria-label="Ringkasan statistik website">
    @foreach ([
      ['label' => 'Page view', 'value' => $summary['page_views'], 'compare' => $comparison['page_views'], 'has_compare' => true],
      ['label' => 'Pengunjung unik', 'value' => $summary['visitors'], 'compare' => $comparison['visitors'], 'has_compare' => true],
      ['label' => 'Sesi', 'value' => $summary['sessions'], 'compare' => $comparison['sessions'], 'has_compare' => true],
      ['label' => 'Halaman / sesi', 'value' => number_format($summary['pages_per_session'], 2, ',', '.'), 'compare' => null, 'has_compare' => false],
      ['label' => 'Aktif 5 menit', 'value' => $summary['active_now'], 'compare' => null, 'has_compare' => false],
    ] as $metric)
      <article class="analytics-metric">
        <span>{{ $metric['label'] }}</span><strong>{{ is_numeric($metric['value']) ? number_format((float) $metric['value'], is_float($metric['value']) ? 2 : 0, ',', '.') : $metric['value'] }}</strong>
        <small>@if ($metric['has_compare'] && $filters['range'] !== 'all'){{ $comparisonLabel($metric['compare']) }} dari periode sebelumnya @else{{ $rangeLabels[$filters['range']] ?? 'Periode aktif' }}@endif</small>
      </article>
    @endforeach
  </section>

  <section class="card analytics-trend-card">
    <div class="analytics-section-head"><div><span>Tren</span><h3>Page view harian</h3></div><small>{{ $filters['from'] }} &ndash; {{ $filters['to'] }}</small></div>
    @if (collect($trend)->sum('count') > 0)
      <div class="analytics-chart-scroll">
        <svg class="analytics-trend-svg" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="Grafik page view harian">
          @for ($line = 0; $line <= 4; $line++)
            @php $lineY = $chartPadY + ($line * (($chartHeight - ($chartPadY * 2)) / 4)); @endphp
            <line x1="{{ $chartPadX }}" y1="{{ $lineY }}" x2="{{ $chartWidth - $chartPadX }}" y2="{{ $lineY }}" class="analytics-chart-grid-line"/>
          @endfor
          <polyline points="{{ $chartPoints }}" class="analytics-chart-line"/>
          @foreach ($trend as $index => $row)
            @php
              $point = explode(',', explode(' ', $chartPoints)[$index] ?? '0,0');
            @endphp
            <circle cx="{{ $point[0] }}" cy="{{ $point[1] }}" r="4" class="analytics-chart-dot"><title>{{ $row['label'] }}: {{ $row['count'] }} page view</title></circle>
          @endforeach
        </svg>
      </div>
    @else
      <div class="analytics-empty">Belum ada page view manusia pada periode ini.</div>
    @endif
    <div class="analytics-response-note">Rata-rata respons server: <strong>{{ number_format($summary['average_response_ms'], 1, ',', '.') }} ms</strong>. Angka ini bukan durasi membaca halaman.</div>
  </section>

  <div class="analytics-breakdown-grid">
    @include('developer.statistics._bars', ['title' => 'Halaman terpopuler', 'kicker' => 'Konten', 'rows' => $topPages, 'wide' => true])
    @include('developer.statistics._bars', ['title' => 'Bahasa browser', 'kicker' => 'Preferensi', 'rows' => $languages, 'note' => 'Dari header browser'])
    @include('developer.statistics._bars', ['title' => 'Jam akses', 'kicker' => 'Waktu', 'rows' => $accessHours, 'note' => 'Timezone Asia/Jakarta'])
    @include('developer.statistics._bars', ['title' => 'Jenis perangkat', 'kicker' => 'Perangkat', 'rows' => $devices])
    @include('developer.statistics._bars', ['title' => 'Browser', 'kicker' => 'Perangkat', 'rows' => $browsers])
    @include('developer.statistics._bars', ['title' => 'Sistem operasi', 'kicker' => 'Perangkat', 'rows' => $operatingSystems])
    @include('developer.statistics._bars', ['title' => 'Sumber referer', 'kicker' => 'Akuisisi', 'rows' => $referrers])
  </div>

  <section class="card table-card-plain analytics-visitors-card">
    <div class="analytics-section-head"><div><span>Pengunjung</span><h3>Paling aktif</h3></div><small>10 teratas dari maksimal 50</small></div>
    <div class="table-wrap analytics-visitors-table-wrap"><table class="table analytics-visitors-table"><thead><tr><th>Pengunjung</th><th>Bahasa / perangkat</th><th>Page view</th><th>Sesi</th><th>Terakhir</th><th>Aksi</th></tr></thead><tbody>
      @if ($visibleVisitors->isNotEmpty())
        @include('developer.statistics._visitor-rows', ['rows' => $visibleVisitors, 'rowClass' => 'analytics-primary-visitor-row'])
      @else
        <tr class="analytics-visitors-empty"><td colspan="6">Belum ada pengunjung pada periode ini.</td></tr>
      @endif
    </tbody></table></div>
    @if ($remainingVisitors->isNotEmpty())
      <details class="analytics-table-more">
        <summary><span>Lihat {{ $remainingVisitors->count() }} pengunjung lainnya</span><span class="analytics-disclosure-icon" aria-hidden="true"></span></summary>
        <div class="table-wrap analytics-visitors-table-wrap"><table class="table analytics-visitors-table"><thead><tr><th>Pengunjung</th><th>Bahasa / perangkat</th><th>Page view</th><th>Sesi</th><th>Terakhir</th><th>Aksi</th></tr></thead><tbody>
          @include('developer.statistics._visitor-rows', ['rows' => $remainingVisitors])
        </tbody></table></div>
      </details>
    @endif
  </section>
@endsection
