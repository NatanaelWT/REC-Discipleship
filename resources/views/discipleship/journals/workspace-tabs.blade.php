@php
    $tabParams = $tabBranchId === null ? [] : ['branch_id' => $tabBranchId];
    $journalTabs = [
        [
            'key' => 'meeting',
            'label' => 'Jurnal Temu DG',
            'route' => 'discipleship.reports-recap',
            'body_class' => 'page-dg_reports_recap',
        ],
        [
            'key' => 'feedback',
            'label' => 'Jurnal Umpan Balik',
            'route' => 'discipleship.member-feedback-recap',
            'body_class' => 'page-member-feedback-recap',
        ],
    ];
    if (can_manage_difficult_questions()) {
        $journalTabs[] = [
            'key' => 'questions',
            'label' => 'Pertanyaan Sulit',
            'route' => 'discipleship.difficult-questions',
            'body_class' => 'page-difficult-questions-admin',
        ];
    }
@endphp

<div class="discipleship-workspace__tabbar">
  <nav
    class="discipleship-workspace__tabs"
    aria-label="Jurnal pemuridan"
    role="tablist"
    data-discipleship-tabs
  >
    @foreach ($journalTabs as $tab)
      @php($isActive = $activeTab === $tab['key'])
      <a
        class="discipleship-workspace__tab{{ $isActive ? ' is-active' : '' }}"
        id="discipleship-tab-{{ $tab['key'] }}"
        href="{{ route($tab['route'], $tabParams) }}"
        role="tab"
        aria-selected="{{ $isActive ? 'true' : 'false' }}"
        aria-controls="discipleship-tabpanel-{{ $tab['key'] }}"
        tabindex="{{ $isActive ? '0' : '-1' }}"
        data-discipleship-tab
        data-tab-key="{{ $tab['key'] }}"
        data-body-class="{{ $tab['body_class'] }}"
        @if ($isActive) aria-current="page" @endif
      >
        <span class="discipleship-workspace__tab-indicator" aria-hidden="true"></span>
        <span>{{ $tab['label'] }}</span>
      </a>
    @endforeach
  </nav>
  <button
    class="discipleship-workspace__refresh"
    type="button"
    aria-label="Refresh data tab aktif"
    title="Refresh data tab aktif"
    data-discipleship-tab-refresh
  >
    <span aria-hidden="true">↻</span>
  </button>
</div>
