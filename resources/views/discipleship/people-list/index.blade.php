@extends('layouts.rec_app', [
    'title' => 'Daftar Anggota DG',
    'settings' => $settings,
    'currentPage' => 'people_list',
    'showTitle' => false,
    'bodyClass' => 'page-discipleship-people-list',
])

@section('content')
    <section class="card people-hero-card">
      <div class="people-hero-head">
        <div class="people-hero-copy">
          <div class="people-hero-kicker">ANGGOTA DG</div>
          <h1>Daftar Anggota DG</h1>
          <p>Pantau relasi pembinaan, progres DG, dan kontak anggota yang sedang berjalan di alur DG.</p>
        </div>
        <div class="people-hero-stats">
          <div class="people-hero-stat"><span class="people-hero-stat-label">Peserta DG</span><strong class="people-hero-stat-value" data-people-stat="total">{{ (string) $totalPeopleRows }}</strong></div>
          <div class="people-hero-stat"><span class="people-hero-stat-label">DG1</span><strong class="people-hero-stat-value" data-people-stat="dg1">{{ (string) $peopleInDg1Count }}</strong></div>
          <div class="people-hero-stat"><span class="people-hero-stat-label">DG2</span><strong class="people-hero-stat-value" data-people-stat="dg2">{{ (string) $peopleInDg2Count }}</strong></div>
          <div class="people-hero-stat"><span class="people-hero-stat-label">DG3</span><strong class="people-hero-stat-value" data-people-stat="dg3">{{ (string) $peopleInDg3Count }}</strong></div>
        </div>
      </div>
      <div class="actions people-hero-tools">
        <div class="people-hero-filter-wrap">
          <select class="search people-status-filter" data-filter="people-dashboard-table" data-filter-role="people-status" aria-label="Filter status progress anggota DG">
            <option value="all">Semua Peserta</option>
            <option value="active_dg1">Sedang DG 1</option>
            <option value="complete_dg1">Selesai DG 1</option>
            <option value="active_dg2">Sedang DG 2</option>
            <option value="complete_dg2">Selesai DG 2</option>
            <option value="active_dg3">Sedang DG 3</option>
            <option value="complete_dg3">Selesai DG 3</option>
            <option value="kgap_complete">Selesai Camp GAP</option>
            <option value="rg_complete">Selesai RG</option>
          </select>
        </div>
        <div class="people-hero-search-wrap">
          <?php render_table_search_input('people-dashboard-table', 'Cari peserta, pembina, progres, atau kontak...', 'search people-table-search', 'Cari daftar peserta DG', '      '); ?>
        </div>
      </div>
    </section>

    <section class="card discipleship-list-card table-card-plain" id="discipleship-people-list">
      <div class="table-wrap">
        <table class="table people-dashboard-table" id="people-dashboard-table">
          <thead><tr><th>Nama & Relasi</th><th>Peran</th><th>Progress DG</th><th>Kontak</th><th>Jumlah Binaan</th></tr></thead>
          <tbody>
            @foreach ($people as $row)
              @php
                  $rowFilterState = trim((string) ($row['row_filter_state'] ?? 'none'));
                  $rowProgressKey = trim((string) ($row['row_progress_key'] ?? 'none'));
                  $name = trim((string) ($row['name'] ?? '-'));
                  if ($name === '') {
                      $name = '-';
                  }
                  $parentSummary = trim((string) ($row['parent_summary'] ?? 'Belum terhubung ke pembina'));
                  if ($parentSummary === '') {
                      $parentSummary = 'Belum terhubung ke pembina';
                  }
                  $roleLabel = trim((string) ($row['role_label'] ?? 'Anggota'));
                  if ($roleLabel === '') {
                      $roleLabel = 'Anggota';
                  }
                  $roleToneClass = trim((string) ($row['role_tone_class'] ?? 'is-member'));
                  $roleSubtitle = trim((string) ($row['role_subtitle'] ?? 'Belum punya binaan langsung'));
                  if ($roleSubtitle === '') {
                      $roleSubtitle = 'Belum punya binaan langsung';
                  }
                  $progressBadges = is_array($row['progress_badges'] ?? null) ? $row['progress_badges'] : [];
                  $phoneLabel = trim((string) ($row['phone_label'] ?? 'Belum ada nomor'));
                  if ($phoneLabel === '') {
                      $phoneLabel = 'Belum ada nomor';
                  }
                  $phoneDigits = trim((string) ($row['phone_digits'] ?? ''));
                  $childCount = max(0, (int) ($row['child_count'] ?? 0));
              @endphp
              <tr data-people-filter="{{ $rowFilterState }}" data-people-progress="{{ $rowProgressKey }}">
                <td>
                  <div class="people-name-cell">
                    <div class="people-name-main">{{ $name }}</div>
                    <div class="people-name-sub">{{ $parentSummary }}</div>
                  </div>
                </td>
                <td>
                  <div class="people-role-cell">
                    <span class="people-role-badge {{ $roleToneClass }}">{{ $roleLabel }}</span>
                    <div class="people-role-sub">{{ $roleSubtitle }}</div>
                  </div>
                </td>
                <td>
                  <div class="people-progress-cell">
                    @if (count($progressBadges) > 0)
                      @foreach ($progressBadges as $badge)
                        @php
                            $badgeClass = trim((string) ($badge['class'] ?? 'is-neutral'));
                            $badgeLabel = trim((string) ($badge['label'] ?? '-'));
                            if ($badgeLabel === '') {
                                $badgeLabel = '-';
                            }
                        @endphp
                        <span class="people-progress-badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                      @endforeach
                    @else
                      <span class="people-progress-badge is-neutral">Belum masuk progres</span>
                    @endif
                  </div>
                </td>
                <td>
                  <div class="people-contact-cell">
                    @if ($phoneDigits !== '')
                      <a class="people-contact-link" href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener">{{ $phoneLabel }}</a>
                    @else
                      <span class="people-contact-empty">{{ $phoneLabel }}</span>
                    @endif
                  </div>
                </td>
                <td>
                  <div class="people-child-count"><strong>{{ (string) $childCount }}</strong><span>peserta</span></div>
                </td>
              </tr>
            @endforeach
            @if ($totalPeopleRows === 0)
              <tr><td colspan="5">Belum ada data orang.</td></tr>
            @endif
          </tbody>
        </table>
      </div>
    </section>
@endsection
