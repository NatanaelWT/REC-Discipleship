@php
    $pageTitle = $pageTitle ?? match ($activeTab ?? '') {
        'dashboard' => 'Dashboard Pemuridan',
        'tree' => 'Pohon Pemuridan',
        'people' => 'Daftar Anggota DG',
        'groups' => 'Kelompok DG',
        default => 'Pemuridan',
    };
    $currentPage = $currentPage ?? match ($activeTab ?? '') {
        'dashboard' => 'discipleship_dashboard',
        'tree' => 'people_tree',
        'people' => 'people_list',
        'groups' => 'groups_list',
        default => '',
    };
    $workspaceBodyClass = match ($activeTab ?? '') {
        'dashboard' => 'page-discipleship-dashboard',
        'tree' => 'page-tree-v2',
        'people' => 'page-discipleship-people-list',
        'groups' => 'page-discipleship-groups-list',
        default => '',
    };
    $currentDiscipleshipScope = app(\App\Services\Discipleship\CurrentDiscipleshipScope::class);
    $workspaceBranchKey = $currentDiscipleshipScope->includesAllBranches()
        ? 'all'
        : ($currentDiscipleshipScope->selectedBranchId() ?? 'none');
    $tabBranchId = $tabBranchId ?? (
        request()->query->has('branch_id')
            ? ($currentDiscipleshipScope->includesAllBranches()
                ? 'all'
                : $currentDiscipleshipScope->selectedBranchId())
            : null
    );
@endphp

@extends('layouts.rec_app', [
    'title' => $pageTitle,
    'settings' => $settings,
    'currentPage' => $currentPage,
    'showTitle' => false,
    'bodyClass' => trim('page-discipleship-workspace '.$workspaceBodyClass),
])

@section('content')
  <div
    class="discipleship-workspace"
    data-discipleship-workspace
    data-active-tab="{{ $activeTab }}"
    data-selected-branch="{{ $workspaceBranchKey }}"
  >
    @include('discipleship.workspace.partials.tabs')

    <div class="discipleship-workspace__panels" data-discipleship-panels>
      @include($panelView)
    </div>
  </div>
@endsection
