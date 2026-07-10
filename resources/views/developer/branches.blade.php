@extends('layouts.rec_app', [
    'title' => 'Developer Cabang',
    'settings' => $settings,
    'currentPage' => 'developer_branches',
    'bodyClass' => 'page-developer page-developer-branches',
    'showTitle' => false,
])

@section('content')
    @include('developer._header', [
      'title' => 'Manajemen Cabang',
      'description' => 'Kelola cabang produksi dan mode eksperimen developer dari data cabang aplikasi.',
      'eyebrow' => 'Branch Management',
      'stats' => $stats,
    ])

    @if ($statusCode === 'created')
      <div class="alert success">Cabang dibuat dalam mode eksperimen.</div>
    @elseif ($statusCode === 'updated')
      <div class="alert success">Cabang diperbarui.</div>
    @elseif ($statusCode === 'deleted')
      <div class="alert success">Cabang kosong dihapus.</div>
    @elseif ($errorCode !== '')
      <div class="alert danger">{{ $errorMessages[$errorCode] ?? 'Perubahan cabang ditolak.' }}</div>
    @endif

    <section class="card developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon">@include('developer._icon', ['name' => 'config'])</span>
        <div><span class="developer-section-kicker">Cabang baru</span><h2>Tambah Cabang</h2><p>Cabang baru dibuat sebagai mode eksperimen sampai developer mengaktifkannya.</p></div>
      </div>
      <form method="post" action="{{ route('developer.branches.store') }}" class="developer-form-grid developer-config-form developer-branch-create-form">
        @csrf
        <label class="developer-config-field">
          <span>Nama Cabang</span>
          <input type="text" name="label" required maxlength="120" autocomplete="off">
          <small>Slug public otomatis mengikuti nama cabang.</small>
        </label>
        <label class="developer-config-field">
          <span>Status</span>
          <select name="is_active">
            <option value="0" selected>Mode Eksperimen Developer</option>
            <option value="1">Produksi Aktif</option>
          </select>
          <small>Produksi aktif muncul di public form dan pilihan user cabang.</small>
        </label>
        @foreach ($targetFields as $field => $label)
          <label class="developer-config-field">
            <span>{{ $label }}</span>
            <input type="number" name="{{ $field }}" value="50" min="0" max="1000000" step="1" required>
          </label>
        @endforeach
        <div class="developer-form-actions">
          <button class="btn developer-primary-action" type="submit">@include('developer._icon', ['name' => 'check'])<span>Buat Cabang</span></button>
        </div>
      </form>
    </section>

    <section class="card developer-panel developer-section-card">
      <div class="developer-section-head">
        <span class="developer-section-icon is-blue">@include('developer._icon', ['name' => 'statistics'])</span>
        <div><span class="developer-section-kicker">Data cabang</span><h2>Daftar Cabang</h2><p>Aktifkan cabang untuk produksi, atau nonaktifkan untuk eksperimen developer-only.</p></div>
        <span class="developer-count-pill">{{ number_format(count($branches), 0, ',', '.') }} cabang</span>
      </div>

      <div class="developer-user-list">
        @forelse ($branches as $branch)
          @php
            $active = (bool) ($branch['active'] ?? false);
            $isExpanded = $expandedBranchId === (int) ($branch['id'] ?? 0);
            $usage = is_array($branch['usage'] ?? null) ? $branch['usage'] : [];
            $targets = is_array($branch['targets'] ?? null) ? $branch['targets'] : [];
          @endphp
          <details class="developer-user-item"{{ $isExpanded ? ' open' : '' }}>
            <summary class="developer-user-toggle">
              <span class="developer-user-avatar">{{ strtoupper(substr((string) ($branch['label'] ?? 'C'), 0, 1)) }}</span>
              <span class="developer-user-identity">
                <strong>{{ $branch['label'] ?? 'Cabang' }}</strong>
                <small>{{ $branch['slug'] ?? '' }} &middot; {{ number_format((int) ($branch['usage_total'] ?? 0), 0, ',', '.') }} data terkait</small>
              </span>
              <span class="developer-user-status {{ $active ? 'is-active' : 'is-inactive' }}">{{ $active ? 'Produksi Aktif' : 'Mode Eksperimen Developer' }}</span>
              <span class="developer-user-chevron" aria-hidden="true"></span>
            </summary>
            <div class="developer-user-detail">
              <div class="developer-user-detail-head"><strong>Pengaturan cabang</strong><span>Perubahan status langsung memengaruhi public form, user management, dan toolbar developer.</span></div>
              <form method="post" action="{{ route('developer.branches.update', ['branch' => $branch['id']]) }}" class="developer-form-grid developer-config-form developer-branch-edit-form">
                @csrf
                <label class="developer-config-field">
                  <span>Nama Cabang</span>
                  <input type="text" name="label" value="{{ $branch['label'] ?? '' }}" required maxlength="120">
                  <small>Slug saat ini: <code>{{ $branch['slug'] ?? '' }}</code></small>
                </label>
                <label class="developer-config-field">
                  <span>Status</span>
                  <select name="is_active">
                    <option value="1" @selected($active)>Produksi Aktif</option>
                    <option value="0" @selected(! $active)>Mode Eksperimen Developer</option>
                  </select>
                </label>
                @foreach ($targetFields as $field => $label)
                  <label class="developer-config-field">
                    <span>{{ $label }}</span>
                    <input type="number" name="{{ $field }}" value="{{ (int) ($targets[$field] ?? 50) }}" min="0" max="1000000" step="1" required>
                  </label>
                @endforeach
                <div class="developer-form-actions">
                  <button class="btn secondary" type="submit">Simpan Cabang</button>
                </div>
              </form>

              <div class="developer-runtime-panel table-wrap">
                <table class="table developer-runtime-table">
                  <tbody>
                    @foreach ($usage as $usageLabel => $usageCount)
                      <tr><th>{{ $usageLabel }}</th><td>{{ number_format((int) $usageCount, 0, ',', '.') }}</td></tr>
                    @endforeach
                  </tbody>
                </table>
              </div>

              <form method="post" action="{{ route('developer.branches.delete', ['branch' => $branch['id']]) }}" class="developer-password-form">
                @csrf
                <div class="developer-password-copy"><strong>Hapus cabang</strong><small>Hanya tersedia jika cabang belum punya user atau data pemuridan.</small></div>
                <button class="btn ghost" type="submit" @if (! ($branch['can_delete'] ?? false)) disabled aria-disabled="true" @endif>Hapus</button>
              </form>
            </div>
          </details>
        @empty
          <div class="developer-dashboard-empty">Belum ada cabang operasional di database.</div>
        @endforelse
      </div>
    </section>
@endsection
