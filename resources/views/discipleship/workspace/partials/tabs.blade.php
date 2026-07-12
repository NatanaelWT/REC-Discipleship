@php
    $tabParams = $tabBranchId === null ? [] : ['branch_id' => $tabBranchId];
    $workspaceTabs = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'discipleship.dashboard'],
        ['key' => 'people', 'label' => 'Anggota DG', 'route' => 'discipleship.people-list'],
        ['key' => 'groups', 'label' => 'Kelompok DG', 'route' => 'discipleship.groups'],
        ['key' => 'tree', 'label' => 'Pohon Pemuridan', 'route' => 'discipleship.tree'],
    ];
@endphp

<nav
  class="discipleship-workspace__tabs"
  aria-label="Tampilan pemuridan"
  role="tablist"
  data-discipleship-tabs
>
  @foreach ($workspaceTabs as $tab)
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
      @if ($isActive) aria-current="page" @endif
    >
      <span class="discipleship-workspace__tab-indicator" aria-hidden="true"></span>
      <span>{{ $tab['label'] }}</span>
    </a>
  @endforeach
</nav>
