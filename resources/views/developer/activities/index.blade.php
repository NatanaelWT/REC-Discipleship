@extends('layouts.rec_app', [
    'title' => 'Aktivitas',
    'settings' => $settings,
    'currentPage' => 'developer_activities',
    'bodyClass' => 'page-developer page-activities',
])

@section('content')
  <section class="card developer-panel activity-filter-card">
    <div class="card-row">
      <div>
        <h2>Riwayat Aktivitas</h2>
        <p class="developer-muted">Seluruh waktu ditampilkan dalam {{ app_timezone()->getName() }}. Data terbaru berada di atas.</p>
      </div>
      <a class="button secondary" href="{{ route('developer.activities') }}">Reset filter</a>
    </div>

    <form method="get" action="{{ route('developer.activities') }}" class="activity-filter-grid">
      <label>Pencarian<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="User, route, subject, IP"></label>
      <label>Dari<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
      <label>Sampai<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
      <label>Actor
        <select name="actor">
          <option value="">Semua</option>
          <option value="user" @selected(($filters['actor'] ?? '') === 'user')>User</option>
          <option value="anonymous" @selected(($filters['actor'] ?? '') === 'anonymous')>Anonim</option>
        </select>
      </label>
      <label>Username<input name="username" value="{{ $filters['username'] ?? '' }}"></label>
      <label>Role
        <select name="role"><option value="">Semua</option>
          @foreach ($roleOptions as $value => $label)
            <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label>Cabang
        <select name="branch_id"><option value="">Semua</option>
          @foreach ($branchOptions as $branch)
            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->label }}</option>
          @endforeach
        </select>
      </label>
      <label>Kategori
        <select name="category"><option value="">Semua</option>
          @foreach ($categoryOptions as $category)
            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
          @endforeach
        </select>
      </label>
      <label>Action<input name="action" value="{{ $filters['action'] ?? '' }}"></label>
      <label>Route<input name="route" value="{{ $filters['route'] ?? '' }}"></label>
      <label>Method
        <select name="method"><option value="">Semua</option>
          @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
            <option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ $method }}</option>
          @endforeach
        </select>
      </label>
      <label>Hasil
        <select name="outcome"><option value="">Semua</option>
          @foreach (['succeeded' => 'Berhasil', 'failed' => 'Gagal', 'denied' => 'Ditolak', 'pending' => 'Belum selesai'] as $value => $label)
            <option value="{{ $value }}" @selected(($filters['outcome'] ?? '') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label>Status HTTP<input type="number" name="status" min="100" max="599" value="{{ $filters['status'] ?? '' }}"></label>
      <label>Subject type<input name="subject_type" value="{{ $filters['subject_type'] ?? '' }}"></label>
      <label>Subject ID<input name="subject_id" value="{{ $filters['subject_id'] ?? '' }}"></label>
      <label>IP<input name="ip" value="{{ $filters['ip'] ?? '' }}"></label>
      <div class="activity-filter-submit"><button class="button" type="submit">Terapkan filter</button></div>
    </form>
  </section>

  <section class="card table-card-plain developer-panel">
    <div class="table-wrap">
      <table class="table activity-table">
        <thead><tr><th>Waktu</th><th>Actor</th><th>Aktivitas</th><th>Hasil</th><th>Detail</th></tr></thead>
        <tbody>
          @forelse ($activities as $item)
            @php
              $time = $item->started_at?->setTimezone(app_timezone());
              $back = http_build_query(request()->query());
            @endphp
            <tr>
              <td><strong>{{ $time?->format('d-m-Y H:i:s') ?? '-' }}</strong><small>{{ $item->duration_ms !== null ? $item->duration_ms.' ms' : '-' }}</small></td>
              <td><strong>{{ $item->username ?: 'Anonim' }}</strong><small>{{ $item->role ?: $item->actor_type }}{{ $item->branch_label ? ' · '.$item->branch_label : '' }}</small></td>
              <td><span class="badge">{{ $item->category }}</span><strong>{{ $item->action }}</strong><small>{{ $item->method }} {{ $item->path }}</small></td>
              <td><span class="activity-outcome is-{{ $item->outcome }}">{{ $item->outcome }}</span><small>HTTP {{ $item->http_status ?? '-' }} · {{ $item->events_count }} event</small></td>
              <td><a class="button secondary small" href="{{ route('developer.activities.show', ['activityRequest' => $item->id, 'back' => $back]) }}">Buka</a></td>
            </tr>
          @empty
            <tr><td colspan="5">Belum ada aktivitas yang sesuai dengan filter.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="activity-pagination">
      @if ($activities->previousPageUrl())<a class="button secondary" href="{{ $activities->previousPageUrl() }}">Sebelumnya</a>@endif
      @if ($activities->nextPageUrl())<a class="button secondary" href="{{ $activities->nextPageUrl() }}">Berikutnya</a>@endif
    </div>
  </section>
@endsection
