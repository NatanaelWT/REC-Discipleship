@extends('layouts.rec_app', [
    'title' => 'Developer',
    'settings' => $settings,
    'currentPage' => 'developer_dashboard',
    'bodyClass' => 'page-developer',
])

@section('content')
    <section class="card developer-panel">
      <div class="card-row">
        <div>
          <h2>Diagnostics</h2>
          <p class="developer-muted">Role: Developer. Pemuridan lintas cabang hanya lihat.</p>
        </div>
      </div>

      <div class="developer-metric-grid">
        <div class="developer-metric">
          <span>User</span>
          <strong>{{ $diagnostics['counts']['users'] ?? 0 }}</strong>
        </div>
        <div class="developer-metric">
          <span>User Aktif</span>
          <strong>{{ $diagnostics['counts']['active_users'] ?? 0 }}</strong>
        </div>
        <div class="developer-metric">
          <span>Developer Aktif</span>
          <strong>{{ $diagnostics['counts']['active_developers'] ?? 0 }}</strong>
        </div>
        <div class="developer-metric">
          <span>Cabang Pemuridan</span>
          <strong>{{ $diagnostics['counts']['branches'] ?? 0 }}</strong>
        </div>
      </div>
    </section>

    <section class="card table-card-plain developer-panel">
      <div class="card-row">
        <h2>Storage</h2>
      </div>
      <div class="table-wrap">
        <table class="table">
          <tbody>
            <tr><th>Public storage</th><td>{{ ($diagnostics['storage']['link_exists'] ?? false) ? 'Ada' : 'Tidak ada' }}</td></tr>
            <tr><th>Symlink</th><td>{{ ($diagnostics['storage']['is_symlink'] ?? false) ? 'Ya' : 'Tidak' }}</td></tr>
            <tr><th>Target storage</th><td>{{ ($diagnostics['storage']['target_exists'] ?? false) ? 'Ada' : 'Tidak ada' }}</td></tr>
            <tr><th>Writable</th><td>{{ ($diagnostics['storage']['public_storage_writable'] ?? false) ? 'Ya' : 'Tidak' }}</td></tr>
            <tr><th>Path public</th><td>{{ $diagnostics['storage']['public_storage_path'] ?? '' }}</td></tr>
            <tr><th>Path target</th><td>{{ $diagnostics['storage']['target_path'] ?? '' }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card table-card-plain developer-panel">
      <div class="card-row">
        <h2>Material Audit</h2>
      </div>
      <div class="table-wrap">
        <table class="table">
          <tbody>
            <tr><th>Record</th><td>{{ $diagnostics['materials']['records'] ?? 0 }}</td></tr>
            <tr><th>Path invalid</th><td>{{ $diagnostics['materials']['invalid_paths'] ?? 0 }}</td></tr>
            <tr><th>File hilang</th><td>{{ $diagnostics['materials']['missing_files'] ?? 0 }}</td></tr>
            <tr><th>File belum terdaftar</th><td>{{ $diagnostics['materials']['unregistered_files'] ?? 0 }}</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card table-card-plain developer-panel">
      <div class="card-row">
        <h2>Runtime</h2>
      </div>
      <div class="table-wrap">
        <table class="table">
          <tbody>
            @foreach (($diagnostics['runtime'] ?? []) as $key => $value)
              <tr>
                <th>{{ str_replace('_', ' ', $key) }}</th>
                <td>{{ $value }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
@endsection
