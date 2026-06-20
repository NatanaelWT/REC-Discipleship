@extends('layouts.rec_app', [
    'title' => 'Kelompok Pemuridan',
    'settings' => $settings,
    'currentPage' => 'groups_list',
    'showTitle' => false,
    'bodyClass' => 'page-discipleship-groups-list',
])

@section('content')
    <section class="card groups-hero-card">
      <div class="groups-hero-head">
        <div class="groups-hero-copy">
          <div class="groups-hero-kicker">Kelompok DG</div>
          <h1>Daftar Kelompok DG</h1>
          <p>Lihat leader, progres, dan komposisi peserta aktif dalam setiap Kelompok DG secara ringkas.</p>
        </div>
        <div class="groups-hero-stats">
          <div class="groups-hero-stat"><span class="groups-hero-stat-label">Kelompok DG</span><strong class="groups-hero-stat-value" data-groups-stat="total">{{ (string) $totalGroupRows }}</strong></div>
          <div class="groups-hero-stat"><span class="groups-hero-stat-label">DG 1</span><strong class="groups-hero-stat-value" data-groups-stat="dg1">{{ (string) $groupsInDg1Count }}</strong></div>
          <div class="groups-hero-stat"><span class="groups-hero-stat-label">DG 2</span><strong class="groups-hero-stat-value" data-groups-stat="dg2">{{ (string) $groupsInDg2Count }}</strong></div>
          <div class="groups-hero-stat"><span class="groups-hero-stat-label">DG 3</span><strong class="groups-hero-stat-value" data-groups-stat="dg3">{{ (string) $groupsInDg3Count }}</strong></div>
        </div>
      </div>
      <form method="get" action="{{ route('discipleship.groups') }}" class="actions groups-hero-tools">
        @if (request()->filled('branch_id'))
          <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
        @endif
        <div class="groups-hero-filter-wrap">
          <select name="status" class="search groups-status-filter" aria-label="Filter status kelompok DG" onchange="this.form.submit()">
            <option value="all" @selected($groupsStatusFilter === 'all')>Semua Kelompok</option>
            <option value="active" @selected($groupsStatusFilter === 'active')>Kelompok Aktif</option>
            <option value="inactive" @selected($groupsStatusFilter === 'inactive')>Kelompok Tidak Aktif</option>
          </select>
        </div>
        <div class="groups-hero-search-wrap">
          <input type="search" name="q" value="{{ $groupsSearch }}" class="search groups-table-search" placeholder="Cari leader, pendamping, progres, atau peserta..." aria-label="Cari Kelompok DG">
        </div>
        <button class="btn tiny secondary" type="submit">Cari</button>
      </form>
    </section>

    <section class="card discipleship-list-card table-card-plain" id="discipleship-groups-list">
      <div class="table-wrap">
        <table class="table groups-dashboard-table" id="groups-dashboard-table">
          <thead><tr><th>Leader & Pendamping</th><th>Status</th><th>Progress</th><th>Anggota</th></tr></thead>
          <tbody>
            @foreach ($groups as $groupRow)
              @php
                  $rowClass = trim((string) ($groupRow['row_class'] ?? ''));
                  $rowStatus = trim((string) ($groupRow['row_status'] ?? 'active'));
                  $rowProgress = trim((string) ($groupRow['row_progress'] ?? 'none'));
                  $leaderName = trim((string) ($groupRow['leader_name'] ?? '-'));
                  if ($leaderName === '') {
                      $leaderName = '-';
                  }
                  $leaderSummary = trim((string) ($groupRow['leader_summary'] ?? 'Tanpa pendamping'));
                  if ($leaderSummary === '') {
                      $leaderSummary = 'Tanpa pendamping';
                  }
                  $groupStatusClass = trim((string) ($groupRow['group_status_class'] ?? 'is-active'));
                  $progressToneClass = trim((string) ($groupRow['progress_tone_class'] ?? 'is-neutral'));
                  $progressLabel = trim((string) ($groupRow['progress_label'] ?? '-'));
                  if ($progressLabel === '') {
                      $progressLabel = '-';
                  }
                  $progressHelper = trim((string) ($groupRow['progress_helper_text'] ?? ''));
                  $memberSummary = trim((string) ($groupRow['member_summary'] ?? 'Belum ada peserta'));
                  if ($memberSummary === '') {
                      $memberSummary = 'Belum ada peserta';
                  }
                  $memberHelper = trim((string) ($groupRow['member_helper_text'] ?? ''));
                  $memberCount = (int) ($groupRow['member_count'] ?? 0);
              @endphp
              <tr @if ($rowClass !== '') class="{{ $rowClass }}" @endif data-group-status="{{ $rowStatus }}" data-group-progress="{{ $rowProgress }}">
                <td>
                  <div class="group-name-cell">
                    <div class="group-name-main">{{ $leaderName }}</div>
                    <div class="group-name-sub">{{ $leaderSummary }}</div>
                  </div>
                </td>
                <td>
                  <div class="group-status-cell">
                    <span class="group-status-badge {{ $groupStatusClass }}">{{ $rowStatus === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
                  </div>
                </td>
                <td>
                  <div class="group-progress-cell">
                    <span class="group-progress-badge {{ $progressToneClass }}">{{ $progressLabel }}</span>
                    <div class="group-progress-sub">{{ $progressHelper }}</div>
                  </div>
                </td>
                <td>
                  <div class="group-members-cell">
                    <div class="group-members-main">{{ $memberSummary }}</div>
                    <div class="group-members-sub">{{ $memberHelper }}</div>
                  </div>
                </td>
              </tr>
            @endforeach
            @if ($filteredGroupRows === 0)
              <tr><td colspan="4">Belum ada kelompok.</td></tr>
            @endif
          </tbody>
        </table>
      </div>
      @include('partials.compact-pagination', ['paginator' => $groupsPagination])
    </section>
@endsection
