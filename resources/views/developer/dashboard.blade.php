@extends('layouts.rec_app', [
    'title' => 'Developer',
    'settings' => $settings,
    'currentPage' => 'developer_dashboard',
    'bodyClass' => 'page-developer',
    'showTitle' => false,
])

@section('content')
  @php
    $overview = is_array($overview ?? null) ? $overview : [];
    $accessSnapshot = is_array($overview['access_snapshot'] ?? null) ? $overview['access_snapshot'] : [];
  @endphp

  @include('developer._header', [
    'title' => 'Pusat Kendali Developer',
    'description' => 'Kelola akun, cabang, dan konfigurasi utama aplikasi.',
    'eyebrow' => 'System Overview',
    'stats' => $overview['header_stats'] ?? [],
  ])

  <section class="card developer-panel developer-section-card">
    <div class="developer-section-head">
      <span class="developer-section-icon is-slate">@include('developer._icon', ['name' => 'users'])</span>
      <div><span class="developer-section-kicker">Data Domain</span><h2>Akun dan Cabang</h2><p>Ringkasan akun pengguna dan cabang. Developer dapat mengelola data pemuridan pada setiap cabang.</p></div>
    </div>

    <div class="developer-metric-grid developer-access-metric-grid">
      @foreach (($accessSnapshot['metrics'] ?? []) as $metric)
        <article class="developer-metric {{ $metric['tone'] ?? 'is-blue' }}">
          <span class="developer-metric-icon">@include('developer._icon', ['name' => $metric['icon'] ?? 'users'])</span>
          <span class="developer-metric-label">{{ $metric['label'] ?? '-' }}</span>
          <strong>{{ $metric['value'] ?? '0' }}</strong>
          <small>{{ $metric['note'] ?? '' }}</small>
        </article>
      @endforeach
    </div>
  </section>

@endsection
