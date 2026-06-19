@extends('layouts.rec_app', [
    'title' => 'Developer Config',
    'settings' => $settings,
    'currentPage' => 'developer_config',
    'bodyClass' => 'page-developer page-developer-config',
])

@section('content')
    @if ($statusCode === 'saved')
      <div class="alert success">Config disimpan.</div>
    @elseif ($errorCode !== '')
      <div class="alert danger">{{ $errorMessages[$errorCode] ?? 'Config ditolak.' }}</div>
    @endif

    <section class="card developer-panel">
      <div class="card-row">
        <h2>Config Dasar</h2>
      </div>

      <form method="post" action="{{ route('developer.config.update') }}" class="developer-form-grid developer-config-form">
        <label>
          <span>Nama Gereja</span>
          <input type="text" name="church_name" value="{{ $configValues['church_name'] ?? '' }}" required maxlength="120">
        </label>
        <label>
          <span>Timezone</span>
          <select name="app_timezone">
            @foreach ($timezoneOptions as $timezone)
              <option value="{{ $timezone }}" @selected($timezone === ($configValues['app_timezone'] ?? 'Asia/Jakarta'))>{{ $timezone }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Debug Banner Developer</span>
          <select name="developer_debug_banner">
            <option value="0" @selected(($configValues['developer_debug_banner'] ?? '0') !== '1')>Nonaktif</option>
            <option value="1" @selected(($configValues['developer_debug_banner'] ?? '0') === '1')>Aktif</option>
          </select>
        </label>
        <div class="developer-form-actions">
          <button class="btn" type="submit">Simpan Config</button>
        </div>
      </form>
    </section>
@endsection
