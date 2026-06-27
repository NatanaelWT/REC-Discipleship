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

    <section
      class="card discipleship-list-card table-card-plain"
      id="discipleship-people-list"
      data-discipleship-people-list
      data-rows-url="{{ route('discipleship.people-list.rows') }}"
      data-page="{{ (int) ($peoplePage ?? 1) }}"
      data-per-page="{{ (int) ($peoplePerPage ?? 50) }}"
      data-has-more="{{ ! empty($hasMorePeopleRows) ? '1' : '0' }}"
      data-next-page="{{ $nextPeoplePage ?? '' }}"
    >
      <div class="table-wrap" data-discipleship-people-scroll>
        <table class="table people-dashboard-table" id="people-dashboard-table">
          <thead><tr><th>Nama & Relasi</th><th>Peran</th><th>Progress DG</th></tr></thead>
          <tbody data-discipleship-people-list-body>
            @include('discipleship.people-list.partials.rows', ['people' => $people])
            <tr data-discipleship-people-search-empty @if ((int) $filteredPeopleRows !== 0) hidden @endif>
              <td colspan="3" aria-live="polite">{{ $peopleEmptyMessage ?? 'Peserta tidak ditemukan.' }}</td>
            </tr>
            <tr data-discipleship-people-loading hidden>
              <td colspan="3" aria-live="polite">Memuat peserta...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
@endsection
