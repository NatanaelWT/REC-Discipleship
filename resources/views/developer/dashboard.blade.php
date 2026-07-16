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

  <div class="developer-dashboard-grid">
    <section class="card developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon is-blue">@include('developer._icon', ['name' => 'users'])</span>
        <div><span class="developer-section-kicker">Administrasi</span><h2>Kelola Akun</h2><p>Tambah pengguna, ubah akses, atur status akun, dan reset password.</p></div>
        <a class="btn tiny ghost developer-link-button" href="{{ route('developer.users') }}">@include('developer._icon', ['name' => 'arrow-right'])<span>Buka</span></a>
      </div>
    </section>

    <section class="card developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon">@include('developer._icon', ['name' => 'dashboard'])</span>
        <div><span class="developer-section-kicker">Pemuridan</span><h2>Kelola Cabang</h2><p>Tambah cabang serta perbarui nama, kode, dan status cabang.</p></div>
        <a class="btn tiny ghost developer-link-button" href="{{ route('developer.branches') }}">@include('developer._icon', ['name' => 'arrow-right'])<span>Buka</span></a>
      </div>
    </section>
  </div>
@endsection
