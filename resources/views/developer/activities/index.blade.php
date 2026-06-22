@extends('layouts.rec_app', [
    'title' => 'Aktivitas',
    'settings' => $settings,
    'currentPage' => 'developer_activities',
    'bodyClass' => 'page-developer page-activities',
    'showTitle' => false,
])

@section('content')
  @php
    $advancedFilterKeys = ['username', 'role', 'branch_id', 'category', 'action', 'route', 'method', 'status', 'subject_type', 'subject_id', 'ip'];
    $activeAdvancedCount = collect($advancedFilterKeys)->filter(static fn (string $key): bool => trim((string) ($filters[$key] ?? '')) !== '')->count();
  @endphp

  @include('developer._header', [
    'title' => 'Riwayat Aktivitas',
    'description' => 'Telusuri request, perubahan data, error, dan akses user dengan audit yang terpusat.',
    'eyebrow' => 'System Audit',
    'icon' => 'activities',
    'metaLabel' => 'Kapasitas halaman',
    'metaValue' => '100 request',
    'metaHint' => 'Urutan terbaru lebih dahulu',
  ])

  <section class="card developer-panel activity-filter-card developer-section-card">
    <div class="card-row">
      <div>
        <h2>Riwayat Aktivitas</h2>
        <p class="developer-muted">Seluruh waktu ditampilkan dalam {{ app_timezone()->getName() }}. Data terbaru berada di atas.</p>
      </div>
      <a class="btn tiny ghost developer-link-button" href="{{ route('developer.activities') }}">@include('developer._icon', ['name' => 'reset'])<span>Reset filter</span></a>
    </div>

    <form method="get" action="{{ route('developer.activities') }}" class="activity-filter-form">
      <div class="activity-filter-primary">
        <label class="activity-filter-search">Pencarian<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="User, route, subject, IP"></label>
        <label>Dari<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>Sampai<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label>Actor
          <select name="actor">
            <option value="">Semua</option>
            <option value="user" @selected(($filters['actor'] ?? '') === 'user')>User</option>
            <option value="anonymous" @selected(($filters['actor'] ?? '') === 'anonymous')>Anonim</option>
          </select>
        </label>
        <label>Hasil
          <select name="outcome"><option value="">Semua</option>
            @foreach (['succeeded' => 'Berhasil', 'failed' => 'Gagal', 'denied' => 'Ditolak', 'pending' => 'Belum selesai'] as $value => $label)
              <option value="{{ $value }}" @selected(($filters['outcome'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <div class="activity-filter-submit"><button class="btn" type="submit">@include('developer._icon', ['name' => 'filter'])<span>Terapkan</span></button></div>
      </div>

      <label class="activity-developer-toggle">
        <input type="checkbox" name="include_developer" value="1" @checked(($filters['include_developer'] ?? '') === '1')>
        <span><strong>Tampilkan aktivitas developer</strong><small>Secara default aktivitas dengan role developer disembunyikan dari daftar.</small></span>
      </label>

      <details class="activity-filter-advanced" data-activity-advanced-filters data-advanced-open="{{ $activeAdvancedCount > 0 ? 'true' : 'false' }}" @if ($activeAdvancedCount > 0) open @endif>
        <summary><span>Filter lanjutan</span><small>{{ $activeAdvancedCount > 0 ? $activeAdvancedCount.' aktif' : 'Username, role, route, HTTP, dan lainnya' }}</small><span class="activity-disclosure-icon" aria-hidden="true"></span></summary>
        <div class="activity-filter-grid">
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
          <label>Status HTTP<input type="number" name="status" min="100" max="599" value="{{ $filters['status'] ?? '' }}"></label>
          <label>Subject type<input name="subject_type" value="{{ $filters['subject_type'] ?? '' }}"></label>
          <label>Subject ID<input name="subject_id" value="{{ $filters['subject_id'] ?? '' }}"></label>
          <label>IP<input name="ip" value="{{ $filters['ip'] ?? '' }}"></label>
        </div>
      </details>
    </form>
  </section>

  <section class="card table-card-plain developer-panel">
    <div class="activity-list-head"><div><span>Request terbaru</span><strong>Aktivitas</strong></div><small>Maksimal 100 per halaman</small></div>
    @include('developer._cursor-pagination', ['paginator' => $activities, 'itemLabel' => 'aktivitas'])
    <div class="table-wrap activity-table-wrap">
      <table class="table activity-table">
        <thead><tr><th>Waktu</th><th>Actor</th><th>Aktivitas</th><th>Hasil</th><th>Detail</th></tr></thead>
        <tbody>
          @forelse ($activities as $item)
            @php
              $time = $item->started_at?->setTimezone(app_timezone());
              $back = http_build_query(request()->query());
            @endphp
            <tr data-activity-row>
              <td data-label="Waktu"><strong>{{ $time?->format('d-m-Y H:i:s') ?? '-' }}</strong><small>{{ $item->duration_ms !== null ? $item->duration_ms.' ms' : '-' }}</small></td>
              <td data-label="Actor"><strong>{{ $item->username ?: 'Anonim' }}</strong><small>{{ $item->role ?: $item->actor_type }}@if ($item->branch_label) &middot; {{ $item->branch_label }}@endif</small></td>
              <td data-label="Aktivitas"><span class="badge">{{ $item->category }}</span><strong>{{ $item->action }}</strong><small class="activity-path" title="{{ $item->method }} {{ $item->path }}">{{ $item->method }} {{ $item->path }}</small></td>
              <td data-label="Hasil"><span class="activity-outcome is-{{ $item->outcome }}">{{ $item->outcome }}</span><small>HTTP {{ $item->http_status ?? '-' }} &middot; {{ $item->events_count }} event</small></td>
              <td data-label="Detail"><a class="btn tiny ghost developer-detail-link" href="{{ route('developer.activities.show', ['activityRequest' => $item->id, 'back' => $back]) }}">@include('developer._icon', ['name' => 'eye'])<span>Buka</span></a></td>
            </tr>
          @empty
            <tr class="activity-empty-row"><td colspan="5">Belum ada aktivitas yang sesuai dengan filter.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
