@extends('layouts.rec_app', [
    'title' => 'Developer Config',
    'settings' => $settings,
    'currentPage' => 'developer_config',
    'bodyClass' => 'page-developer page-developer-config',
    'showTitle' => false,
])

@section('content')
    @include('developer._header', [
      'activePage' => 'developer_config',
      'title' => 'Konfigurasi Aplikasi',
      'description' => 'Atur identitas aplikasi, zona waktu, dan alat bantu khusus developer.',
      'eyebrow' => 'Application Settings',
    ])

    @if ($statusCode === 'saved')
      <div class="alert success">Config disimpan.</div>
    @elseif ($errorCode !== '')
      <div class="alert danger">{{ $errorMessages[$errorCode] ?? 'Config ditolak.' }}</div>
    @endif

    <section class="card developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon">@include('developer._icon', ['name' => 'config'])</span>
        <div><span class="developer-section-kicker">Pengaturan global</span><h2>Config Dasar</h2><p>Perubahan berlaku untuk seluruh user dan halaman aplikasi.</p></div>
      </div>

      <div class="developer-config-layout">
        <form method="post" action="{{ route('developer.config.update') }}" class="developer-form-grid developer-config-form">
          @csrf
          <label class="developer-config-field">
            <span>Nama Gereja</span>
            <input type="text" name="church_name" value="{{ $configValues['church_name'] ?? '' }}" required maxlength="120">
            <small>Ditampilkan sebagai identitas utama pada aplikasi.</small>
          </label>
          <label class="developer-config-field">
            <span>Timezone</span>
            <select name="app_timezone">
              @foreach ($timezoneOptions as $timezone)
                <option value="{{ $timezone }}" @selected($timezone === ($configValues['app_timezone'] ?? 'Asia/Jakarta'))>{{ $timezone }}</option>
              @endforeach
            </select>
            <small>Mengatur batas hari dan tampilan waktu sistem.</small>
          </label>
          <label class="developer-config-field">
            <span>Debug Banner Developer</span>
            <select name="developer_debug_banner">
              <option value="0" @selected(($configValues['developer_debug_banner'] ?? '0') !== '1')>Nonaktif</option>
              <option value="1" @selected(($configValues['developer_debug_banner'] ?? '0') === '1')>Aktif</option>
            </select>
            <small>Menampilkan penanda debug khusus pada akun developer.</small>
          </label>
          <div class="developer-form-actions">
            <button class="btn developer-primary-action" type="submit"><span>Simpan Config</span><span aria-hidden="true">→</span></button>
          </div>
        </form>

        <aside class="developer-config-guide">
          <span class="developer-config-guide-icon">@include('developer._icon', ['name' => 'config'])</span>
          <div><span>Catatan</span><strong>Konfigurasi global</strong><p>Pastikan timezone sesuai lokasi operasional. Perubahan nama gereja langsung digunakan pada header aplikasi.</p></div>
        </aside>
      </div>
    </section>
@endsection
