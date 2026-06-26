@extends('layouts.rec_app', [
    'title' => 'Developer Config',
    'settings' => $settings,
    'currentPage' => 'developer_config',
    'bodyClass' => 'page-developer page-developer-config',
    'showTitle' => false,
])

@section('content')
    @include('developer._header', [
      'title' => 'Konfigurasi Aplikasi',
      'description' => 'Atur identitas aplikasi, zona waktu, dan alat bantu khusus developer.',
      'eyebrow' => 'Application Settings',
      'stats' => [
        ['label' => 'Timezone Aktif', 'value' => $configValues['app_timezone'] ?? 'Asia/Jakarta'],
        ['label' => 'Nama Aplikasi', 'value' => $configValues['church_name'] ?? app_church_name()],
        ['label' => 'Debug Banner', 'value' => ($configValues['developer_debug_banner'] ?? '0') === '1' ? 'Aktif' : 'Nonaktif'],
        ['label' => 'Pilihan Timezone', 'value' => number_format(count($timezoneOptions), 0, ',', '.')],
      ],
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
          <button class="btn developer-primary-action" type="submit">@include('developer._icon', ['name' => 'check'])<span>Simpan Config</span></button>
        </div>
      </form>
    </section>

    <section class="card table-card-plain developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon is-slate">@include('developer._icon', ['name' => 'config'])</span>
        <div><span class="developer-section-kicker">Environment</span><h2>Runtime</h2><p>Informasi lingkungan aplikasi yang sedang digunakan.</p></div>
      </div>
      <div class="table-wrap">
        <table class="table developer-runtime-table">
          <tbody>
            @foreach (($runtime ?? []) as $key => $value)
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
