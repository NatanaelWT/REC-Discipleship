@extends('layouts.rec_app', [
    'title' => 'Daftar Anggota DG',
    'settings' => $settings,
    'currentPage' => 'people_list',
    'showTitle' => false,
    'bodyClass' => 'page-discipleship-people-list',
])

@section('content')
    @if (request()->query('error') === 'export_zip_unavailable')
      <div class="alert danger">Fitur export Excel belum tersedia karena ekstensi ZipArchive belum aktif.</div>
    @elseif (request()->query('error') === 'export_failed')
      <div class="alert danger">Export data Anggota DG gagal. Silakan coba kembali.</div>
    @endif

    @include('discipleship.partials.page-header', [
        'header' => [
            'kicker' => 'Anggota DG',
            'title' => 'Daftar Anggota DG',
            'description' => 'Pantau relasi pembinaan dan progres seluruh peserta yang pernah mengikuti DG, baik yang masih aktif maupun sudah selesai.',
            'stats' => [
                ['label' => 'Semua Peserta', 'value' => (string) $totalPeopleRows, 'value_attributes' => ['data-people-stat' => 'total']],
                ['label' => 'Terakhir DG 1', 'value' => (string) $peopleInDg1Count, 'value_attributes' => ['data-people-stat' => 'dg1']],
                ['label' => 'Terakhir DG 2', 'value' => (string) $peopleInDg2Count, 'value_attributes' => ['data-people-stat' => 'dg2']],
                ['label' => 'Terakhir DG 3', 'value' => (string) $peopleInDg3Count, 'value_attributes' => ['data-people-stat' => 'dg3']],
            ],
            'tools' => [
                'element' => 'form',
                'method' => 'get',
                'action' => route('discipleship.people-list'),
                'attributes' => ['data-discipleship-people-search-form' => true],
                'partial' => 'discipleship.partials.page-header-controls.people',
                'data' => compact('peopleProgressFilter', 'peopleSearch'),
            ],
        ],
    ])

    <section class="card discipleship-list-card table-card-plain" id="discipleship-people-list">
      <div class="table-wrap">
        <table class="table people-dashboard-table" id="people-dashboard-table">
          <thead><tr><th>Nama & Relasi</th><th>Peran</th><th>Progress DG</th></tr></thead>
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
                  $roleSubtitle = trim((string) ($row['role_subtitle'] ?? 'Belum memiliki binaan langsung'));
                  if ($roleSubtitle === '') {
                      $roleSubtitle = 'Belum memiliki binaan langsung';
                  }
                  $progressSteps = is_array($row['progress_steps'] ?? null) ? $row['progress_steps'] : [];
                  $progressSummary = trim((string) ($row['progress_summary'] ?? 'Belum memulai DG'));
              @endphp
              <tr data-people-filter="{{ $rowFilterState }}" data-people-progress="{{ $rowProgressKey }}" data-discipleship-people-search-row data-search-text="{{ $name }}">
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
              </tr>
            @endforeach
            @if ($filteredPeopleRows === 0)
              <tr><td colspan="3">Belum ada data orang.</td></tr>
            @else
              <tr data-discipleship-people-search-empty hidden><td colspan="3" aria-live="polite">Peserta tidak ditemukan.</td></tr>
            @endif
          </tbody>
        </table>
      </div>
    </section>
@endsection
