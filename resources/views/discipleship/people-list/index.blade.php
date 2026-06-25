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
      <form method="get" action="{{ route('discipleship.people-list') }}" class="actions people-hero-tools">
        @if (request()->filled('branch_id'))
          <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
        @endif
        <div class="people-hero-filter-wrap">
          <select name="progress" class="search people-status-filter" aria-label="Filter status progress anggota DG" onchange="this.form.submit()">
            <option value="all" @selected($peopleProgressFilter === 'all')>Semua Peserta</option>
            <option value="active_dg1" @selected($peopleProgressFilter === 'active_dg1')>Sedang DG 1</option>
            <option value="complete_dg1" @selected($peopleProgressFilter === 'complete_dg1')>Selesai DG 1</option>
            <option value="active_dg2" @selected($peopleProgressFilter === 'active_dg2')>Sedang DG 2</option>
            <option value="complete_dg2" @selected($peopleProgressFilter === 'complete_dg2')>Selesai DG 2</option>
            <option value="active_dg3" @selected($peopleProgressFilter === 'active_dg3')>Sedang DG 3</option>
            <option value="complete_dg3" @selected($peopleProgressFilter === 'complete_dg3')>Selesai DG 3</option>
          </select>
        </div>
        <div class="people-hero-search-wrap">
          <input type="search" name="q" value="{{ $peopleSearch }}" class="search people-table-search" placeholder="Cari peserta, pembina, progres, atau kontak..." aria-label="Cari daftar peserta DG">
        </div>
        <button class="btn tiny secondary" type="submit">Cari</button>
      </form>
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
                  $progressSteps = is_array($row['progress_steps'] ?? null) ? $row['progress_steps'] : [];
                  $progressSummary = trim((string) ($row['progress_summary'] ?? 'Belum memulai DG'));
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
                    <div class="people-progress-track" aria-label="{{ $progressSummary }}">
                      @foreach ($progressSteps as $step)
                        @php
                            $stepState = trim((string) ($step['state'] ?? 'is-pending'));
                            $stepLabel = trim((string) ($step['label'] ?? '-')) ?: '-';
                            $stepStateLabel = trim((string) ($step['state_label'] ?? 'Belum')) ?: 'Belum';
                        @endphp
                        <span class="people-progress-step {{ $stepState }}">
                          <span class="people-progress-step-marker" aria-hidden="true"></span>
                          <span class="people-progress-step-copy">
                            <strong>{{ $stepLabel }}</strong>
                            <small>{{ $stepStateLabel }}</small>
                          </span>
                        </span>
                      @endforeach
                    </div>
                    <span class="people-progress-summary">{{ $progressSummary }}</span>
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
            @if ($filteredPeopleRows === 0)
              <tr><td colspan="5">Belum ada data orang.</td></tr>
            @endif
          </tbody>
        </table>
      </div>
      @include('partials.compact-pagination', ['paginator' => $peoplePagination])
    </section>
@endsection
