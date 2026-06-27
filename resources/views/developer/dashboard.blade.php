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
    $health = is_array($overview['health_metrics'] ?? null) ? $overview['health_metrics'] : [];
    $publicAnalytics = is_array($overview['public_analytics'] ?? null) ? $overview['public_analytics'] : [];
    $accessSnapshot = is_array($overview['access_snapshot'] ?? null) ? $overview['access_snapshot'] : [];
    $recentActivities = collect(is_array($overview['recent_activities'] ?? null) ? $overview['recent_activities'] : []);
    $attentionItems = collect(is_array($overview['attention_items'] ?? null) ? $overview['attention_items'] : []);
  @endphp

  @include('developer._header', [
    'title' => 'Pusat Kendali Developer',
    'description' => 'Pantau kondisi aplikasi, trafik publik, aktivitas terbaru, dan akses alat administrasi utama.',
    'eyebrow' => 'System Overview',
    'stats' => $overview['header_stats'] ?? [['label' => 'Status Sistem', 'value' => 'Stabil']],
  ])

  <section class="card developer-panel developer-section-card">
    <div class="developer-section-head">
      <span class="developer-section-icon">@include('developer._icon', ['name' => 'dashboard'])</span>
      <div><span class="developer-section-kicker">Kondisi 24 Jam</span><h2>Kondisi Aplikasi</h2><p>Ringkasan request, error, response time, dan event audit terbaru.</p></div>
      <span class="developer-status-pill {{ $health['status_tone'] ?? 'is-online' }}"><i></i>{{ $health['status_label'] ?? 'Stabil' }}</span>
    </div>

    <div class="developer-metric-grid">
      @foreach (($health['metrics'] ?? []) as $metric)
        <article class="developer-metric {{ $metric['tone'] ?? 'is-blue' }}">
          <span class="developer-metric-icon">@include('developer._icon', ['name' => $metric['icon'] ?? 'dashboard'])</span>
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
        <span class="developer-section-icon is-blue">@include('developer._icon', ['name' => 'statistics'])</span>
        <div><span class="developer-section-kicker">7 Hari Terakhir</span><h2>Kunjungan Publik</h2><p>Hanya traffic manusia dari halaman publik dan login.</p></div>
        <a class="btn tiny ghost developer-link-button" href="{{ route('developer.statistics', ['range' => '7']) }}">@include('developer._icon', ['name' => 'arrow-right'])<span>Detail</span></a>
      </div>

      <div class="analytics-metric-grid developer-dashboard-public-metrics">
        @foreach (($publicAnalytics['metrics'] ?? []) as $metric)
          <article class="analytics-metric">
            <span>{{ $metric['label'] ?? '-' }}</span>
            <strong>{{ $metric['value'] ?? '0' }}</strong>
            <small>{{ $metric['note'] ?? '' }}</small>
          </article>
        @endforeach
      </div>

      <div class="developer-dashboard-list">
        <div class="developer-dashboard-list-head"><strong>Halaman Teratas</strong><span>maksimal 5</span></div>
        @forelse (($publicAnalytics['top_pages'] ?? []) as $pageRow)
          <div class="developer-dashboard-list-row">
            <span><strong>{{ $pageRow['label'] ?? 'Tidak diketahui' }}</strong><small>{{ $pageRow['path'] ?? '' }}</small></span>
            <b>{{ number_format((int) ($pageRow['count'] ?? 0), 0, ',', '.') }}</b>
          </div>
        @empty
          <div class="developer-dashboard-empty">Belum ada kunjungan publik manusia pada periode ini.</div>
        @endforelse
      </div>
    </section>

    <section class="card developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon is-slate">@include('developer._icon', ['name' => 'config'])</span>
        <div><span class="developer-section-kicker">Akses Sistem</span><h2>Akun dan Cabang</h2><p>Akses developer aktif. Pemuridan lintas cabang hanya lihat.</p></div>
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
  </div>

  <div class="developer-dashboard-grid">
    <section class="card table-card-plain developer-panel developer-section-card">
      <div class="developer-section-head developer-dashboard-table-head">
        <span class="developer-section-icon">@include('developer._icon', ['name' => 'activities'])</span>
        <div><span class="developer-section-kicker">Audit Request</span><h2>Aktivitas Terbaru</h2><p>Aktivitas non-developer terbaru agar dashboard tidak penuh oleh aktivitas admin sendiri.</p></div>
        <a class="btn tiny ghost developer-link-button" href="{{ route('developer.activities') }}">@include('developer._icon', ['name' => 'arrow-right'])<span>Semua</span></a>
      </div>
      <div class="table-wrap">
        <table class="table developer-dashboard-table">
          <thead><tr><th>Waktu</th><th>Aktor</th><th>Request</th><th>Status</th><th>Event</th><th>Aksi</th></tr></thead>
          <tbody>
            @forelse ($recentActivities as $activity)
              <tr>
                <td>{{ $activity['started_at'] ?? '-' }}</td>
                <td>{{ $activity['actor'] ?? '-' }}</td>
                <td><span class="developer-request-cell"><strong>{{ $activity['method'] ?? 'GET' }}</strong><small>{{ $activity['path'] ?? '-' }}</small></span></td>
                <td>{{ $activity['status'] ?? '-' }}</td>
                <td>{{ number_format((int) ($activity['events_count'] ?? 0), 0, ',', '.') }}</td>
                <td><a class="btn tiny ghost developer-detail-link" href="{{ $activity['detail_url'] ?? route('developer.activities') }}">@include('developer._icon', ['name' => 'eye'])<span>Buka</span></a></td>
              </tr>
            @empty
              <tr><td colspan="6">Belum ada aktivitas non-developer yang tercatat.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="card table-card-plain developer-panel developer-section-card">
      <div class="developer-section-head developer-dashboard-table-head">
        <span class="developer-section-icon is-slate">@include('developer._icon', ['name' => 'config'])</span>
        <div><span class="developer-section-kicker">Error Terbaru</span><h2>Perlu Dicek</h2><p>Request dengan status 5xx atau outcome error.</p></div>
        <a class="btn tiny ghost developer-link-button" href="{{ route('developer.activities', ['outcome' => 'error', 'include_developer' => '1']) }}">@include('developer._icon', ['name' => 'filter'])<span>Filter</span></a>
      </div>
      <div class="table-wrap">
        <table class="table developer-dashboard-table">
          <thead><tr><th>Waktu</th><th>Aktor</th><th>Request</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            @forelse ($attentionItems as $activity)
              <tr>
                <td>{{ $activity['started_at'] ?? '-' }}</td>
                <td>{{ $activity['actor'] ?? '-' }}</td>
                <td><span class="developer-request-cell"><strong>{{ $activity['method'] ?? 'GET' }}</strong><small>{{ $activity['path'] ?? '-' }}</small></span></td>
                <td>{{ $activity['status'] ?? '-' }}</td>
                <td><a class="btn tiny ghost developer-detail-link" href="{{ $activity['detail_url'] ?? route('developer.activities') }}">@include('developer._icon', ['name' => 'eye'])<span>Buka</span></a></td>
              </tr>
            @empty
              <tr><td colspan="5">Tidak ada request error yang perlu dicek.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>

@endsection
