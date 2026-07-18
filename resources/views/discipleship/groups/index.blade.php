<section
  class="discipleship-tab-panel discipleship-workspace__panel discipleship-list-panel"
  id="discipleship-tabpanel-groups"
  role="tabpanel"
  aria-labelledby="discipleship-tab-groups"
  tabindex="0"
  data-discipleship-tab-panel
  data-tab-key="groups"
  data-page-title="{{ $pageTitle ?? 'Kelompok DG' }}"
  data-tree-group-detail-url-template="{{ route('discipleship.groups.detail', ['group' => '__id__']) }}"
>
  @include('discipleship.partials.page-header', [
      'header' => [
          'kicker' => 'Kelompok DG',
          'title' => 'Daftar Kelompok DG',
          'description' => 'Lihat leader, progres, dan komposisi peserta aktif dalam setiap Kelompok DG secara ringkas.',
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
    data-limit="{{ (int) ($groupsLimit ?? 50) }}"
    data-has-more="{{ ! empty($hasMoreGroupRows) ? '1' : '0' }}"
    data-next-cursor="{{ $nextGroupCursor ?? '' }}"
  >
    <div class="table-wrap" data-discipleship-groups-scroll data-table-horizontal-scroll>
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

  @include('partials.modal', [
      'id' => 'groups-detail-modal',
      'size' => 'wide',
      'modalAttrs' => ['data-tree-v2-history-modal' => true],
      'title' => 'Detail Kelompok',
      'titleAttrs' => ['data-tree-v2-history-title' => true],
      'closeAttrs' => ['data-tree-v2-history-close' => true],
      'bodyAttrs' => ['data-tree-v2-history-body' => true],
      'bodyHtml' => '<div class="journey-history-empty">Klik nama pemimpin untuk melihat detail kelompok.</div>',
  ])
</section>
