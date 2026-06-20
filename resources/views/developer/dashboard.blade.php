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
