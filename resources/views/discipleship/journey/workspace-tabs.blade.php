@php
    $tabParams = $tabBranchId === null ? [] : ['branch_id' => $tabBranchId];
    $journeyTabs = [
        [
            'key' => 'spiritual',
            'label' => 'Spiritual Journey',
            'route' => 'discipleship.spiritual-journey',
            'body_class' => 'page-spiritual_journey',
        ],
        [
            'key' => 'msk',
            'label' => 'Kelas MSK',
            'route' => 'discipleship.msk-classes',
            'body_class' => 'page-msk_classes',
        ],
    ];
@endphp

<div class="discipleship-workspace__tabbar">
  <nav
    class="discipleship-workspace__tabs"
    aria-label="Spiritual journey dan kelas MSK"
    role="tablist"
    data-discipleship-tabs
  >
    @foreach ($journeyTabs as $tab)
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
