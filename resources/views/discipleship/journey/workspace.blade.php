@php
    $pageTitle = $pageTitle ?? match ($activeTab ?? '') {
        'spiritual' => 'Spiritual Journey',
        'msk' => 'Kelas MSK',
        default => 'Journey & MSK',
    };
    $currentPage = $currentPage ?? match ($activeTab ?? '') {
        'spiritual' => 'spiritual_journey',
        'msk' => 'msk_classes',
        default => 'spiritual_journey',
    };
    $bodyClassByTab = [
        'spiritual' => 'page-spiritual_journey',
        'msk' => 'page-msk_classes',
    ];
    $workspaceBodyClass = $bodyClassByTab[$activeTab ?? ''] ?? '';
    $currentDiscipleshipScope = app(\App\Services\Discipleship\CurrentDiscipleshipScope::class);
    $workspaceBranchKey = $currentDiscipleshipScope->includesAllBranches()
        ? 'all'
        : ($currentDiscipleshipScope->selectedBranchId() ?? 'none');
    $tabBranchId = $tabBranchId ?? (
        (request()->query->has('branch_id') || request()->query->has('rekap_cabang'))
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
    'bodyClass' => trim('page-discipleship-workspace page-discipleship-journey-workspace '.$workspaceBodyClass),
])

@section('content')
  <div
    class="discipleship-workspace discipleship-journey-workspace"
    data-discipleship-workspace
    data-active-tab="{{ $activeTab }}"
    data-selected-branch="{{ $workspaceBranchKey }}"
  >
    @include('discipleship.journey.workspace-tabs')

    <div class="discipleship-workspace__panels" data-discipleship-panels>
      @include($panelView)
    </div>
  </div>
@endsection
