@php
    $pageTitle = $pageTitle ?? match ($activeTab ?? '') {
        'meeting' => 'Jurnal Temu DG',
        'feedback' => 'Jurnal Umpan Balik',
        'questions' => 'Pertanyaan Sulit',
        default => 'Jurnal Pemuridan',
    };
    $currentPage = $currentPage ?? match ($activeTab ?? '') {
        'meeting' => 'dg_reports_recap',
        'feedback' => 'member_feedback_recap',
        'questions' => 'difficult_questions_admin',
        default => 'dg_reports_recap',
    };
    $bodyClassByTab = [
        'meeting' => 'page-dg_reports_recap',
        'feedback' => 'page-member-feedback-recap',
        'questions' => 'page-difficult-questions-admin',
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
    'bodyClass' => trim('page-discipleship-workspace page-discipleship-journal-workspace '.$workspaceBodyClass),
])

@section('content')
  <div
    class="discipleship-workspace discipleship-journal-workspace"
    data-discipleship-workspace
    data-active-tab="{{ $activeTab }}"
    data-selected-branch="{{ $workspaceBranchKey }}"
  >
    @include('discipleship.journals.workspace-tabs')

    <div class="discipleship-workspace__panels" data-discipleship-panels>
      @include($panelView)
    </div>
  </div>
@endsection
