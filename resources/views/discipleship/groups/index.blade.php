@extends('layouts.rec_app', [
    'title' => 'Kelompok Pemuridan',
    'settings' => $settings,
    'currentPage' => 'groups_list',
    'showTitle' => false,
    'bodyClass' => 'page-discipleship-groups-list',
])

@section('content')
    @include('discipleship.partials.page-header', [
        'header' => [
            'kicker' => 'Kelompok DG',
            'title' => 'Daftar Kelompok DG',
            'description' => 'Lihat leader, progres, dan komposisi peserta aktif dalam setiap Kelompok DG secara ringkas.',
            'stats' => [
                ['label' => 'Kelompok DG', 'value' => (string) $totalGroupRows, 'value_attributes' => ['data-groups-stat' => 'total']],
                ['label' => 'DG 1', 'value' => (string) $groupsInDg1Count, 'value_attributes' => ['data-groups-stat' => 'dg1']],
                ['label' => 'DG 2', 'value' => (string) $groupsInDg2Count, 'value_attributes' => ['data-groups-stat' => 'dg2']],
                ['label' => 'DG 3', 'value' => (string) $groupsInDg3Count, 'value_attributes' => ['data-groups-stat' => 'dg3']],
            ],
            'tools' => [
                'element' => 'form',
                'method' => 'get',
                'action' => route('discipleship.groups'),
                'attributes' => ['data-discipleship-groups-search-form' => true],
                'partial' => 'discipleship.partials.page-header-controls.groups',
                'data' => compact('groupsStatusFilter', 'groupsSearch'),
            ],
        ],
    ])

    <section
      class="card discipleship-list-card table-card-plain"
      id="discipleship-groups-list"
      data-discipleship-groups-list
      data-rows-url="{{ route('discipleship.groups.rows') }}"
      data-page="{{ (int) ($groupsPage ?? 1) }}"
      data-per-page="{{ (int) ($groupsPerPage ?? 50) }}"
      data-has-more="{{ ! empty($hasMoreGroupRows) ? '1' : '0' }}"
      data-next-page="{{ $nextGroupPage ?? '' }}"
    >
      <div class="table-wrap" data-discipleship-groups-scroll>
        <table class="table groups-dashboard-table" id="groups-dashboard-table">
          <thead><tr><th>Leader & Pendamping</th><th>Status</th><th>Progress</th><th>Anggota</th></tr></thead>
          <tbody data-discipleship-groups-list-body>
            @include('discipleship.groups.partials.rows', ['groups' => $groups])
            <tr data-discipleship-groups-empty @if ((int) $filteredGroupRows !== 0) hidden @endif>
              <td colspan="4" aria-live="polite">{{ $groupsEmptyMessage ?? 'Kelompok tidak ditemukan.' }}</td>
            </tr>
            <tr data-discipleship-groups-loading hidden>
              <td colspan="4" aria-live="polite">Memuat kelompok...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
@endsection
