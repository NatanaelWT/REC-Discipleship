@extends('layouts.rec_app', [
    'title' => 'Developer',
    'settings' => $settings,
    'currentPage' => 'developer_dashboard',
    'bodyClass' => 'page-developer',
    'showTitle' => false,
])

@section('content')
    @include('developer._header', [
      'title' => 'Pusat Kendali Developer',
      'description' => 'Pantau kondisi aplikasi dan buka alat administrasi utama melalui panel yang terpusat.',
      'eyebrow' => 'System Overview',
      'icon' => 'dashboard',
      'metaLabel' => 'Status sistem',
      'metaValue' => 'Aktif',
      'metaHint' => 'Developer / '.app_timezone()->getName(),
    ])

    <section class="card developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon">@include('developer._icon', ['name' => 'dashboard'])</span>
        <div><span class="developer-section-kicker">Ringkasan akses</span><h2>Diagnostics</h2><p>Role Developer aktif. Pemuridan lintas cabang hanya lihat.</p></div>
        <span class="developer-status-pill is-online"><i></i>Sistem aktif</span>
      </div>

      <div class="developer-metric-grid">
        @foreach ([
          ['label' => 'Total User', 'value' => $diagnostics['counts']['users'] ?? 0, 'note' => 'Semua akun terdaftar', 'tone' => 'is-teal', 'icon' => 'users'],
          ['label' => 'User Aktif', 'value' => $diagnostics['counts']['active_users'] ?? 0, 'note' => 'Akun yang dapat masuk', 'tone' => 'is-blue', 'icon' => 'users'],
          ['label' => 'Developer Aktif', 'value' => $diagnostics['counts']['active_developers'] ?? 0, 'note' => 'Pengelola sistem', 'tone' => 'is-amber', 'icon' => 'config'],
          ['label' => 'Cabang Pemuridan', 'value' => $diagnostics['counts']['branches'] ?? 0, 'note' => 'Cabang aktif terpantau', 'tone' => 'is-violet', 'icon' => 'statistics'],
        ] as $metric)
          <article class="developer-metric {{ $metric['tone'] }}">
            <span class="developer-metric-icon">@include('developer._icon', ['name' => $metric['icon']])</span>
            <span class="developer-metric-label">{{ $metric['label'] }}</span>
            <strong>{{ number_format((int) $metric['value'], 0, ',', '.') }}</strong>
            <small>{{ $metric['note'] }}</small>
          </article>
        @endforeach
      </div>
    </section>

    <section class="card table-card-plain developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon is-slate">@include('developer._icon', ['name' => 'config'])</span>
        <div><span class="developer-section-kicker">Environment</span><h2>Runtime</h2><p>Informasi lingkungan aplikasi yang sedang digunakan.</p></div>
      </div>
      <div class="table-wrap">
        <table class="table developer-runtime-table">
          <tbody>
            @foreach (($diagnostics['runtime'] ?? []) as $key => $value)
              <tr>
                <th><span class="developer-runtime-key">{{ str_replace('_', ' ', $key) }}</span></th>
                <td><code>{{ $value }}</code></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
@endsection
