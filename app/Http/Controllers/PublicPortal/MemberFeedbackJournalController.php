<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberFeedbackJournals\StoreMemberFeedbackJournalRequest;
use App\Models\DiscipleshipFeedback;
use App\Services\MemberFeedbackJournals\MemberFeedbackFormData;
use App\Services\MemberFeedbackJournals\MemberFeedbackQuestionCatalog;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class MemberFeedbackJournalController extends Controller
{
    public function selectBranch(Request $request): View
    {
        RuntimeBootstrap::boot($request);

        $feedbackSessionParam = normalize_public_member_feedback_session(
            $request->query('feedback_session', $request->query('session', '')),
        );
        $branchOptions = public_dg_branch_options();

        return view('public.member-feedback.select-branch', [
            'settings' => ['church_name' => app_church_name()],
            'errorCode' => trim((string) $request->query('error', '')),
            'feedbackSessionParam' => $feedbackSessionParam,
            'branchOptions' => $branchOptions,
            'branchCount' => count($branchOptions),
        ]);
    }

    public function create(
        Request $request,
        MemberFeedbackFormData $formData,
        MemberFeedbackQuestionCatalog $questionCatalog,
    ): View|RedirectResponse {
        RuntimeBootstrap::boot($request);

        $old = is_array(session('public_member_feedback_old'))
            ? session('public_member_feedback_old')
            : [];
        $publicBranchRaw = trim((string) $request->query('cabang', ''));
        if ($publicBranchRaw === '') {
            $publicBranchRaw = trim((string) ($old['public_cabang'] ?? ''));
        }

        if ($publicBranchRaw === '') {
            if (is_logged_in()) {
                $publicBranch = current_user_branch();
                if (! is_known_public_branch_code($publicBranch)) {
                    return redirect()->route('public.member-feedback.branch', ['error' => 'invalid_branch']);
                }
            } else {
                return redirect()->route('public.member-feedback.branch');
            }
        } elseif (! is_known_public_branch_code($publicBranchRaw)) {
            return redirect()->route('public.member-feedback.branch', ['error' => 'invalid_branch']);
        } else {
            $publicBranch = normalize_public_branch_code($publicBranchRaw);
        }

        $old['public_cabang'] = $publicBranch;
        $branchFormData = $formData->forBranch($publicBranch);
        $groupOptions = $branchFormData['groups'];
        $groupMap = $branchFormData['group_map'];

        $errorMessage = trim((string) session('public_member_feedback_error', ''));
        session()->forget('public_member_feedback_error');

        return view('public.member-feedback.create', [
            'settings' => ['church_name' => app_church_name()],
            'old' => $old,
            'publicBranch' => $publicBranch,
            'publicBranchLabel' => public_branch_label($publicBranch),
            'groupOptions' => $groupOptions,
            'groupMap' => $groupMap,
            'questions' => $questionCatalog->sections(),
            'submitted' => $request->query->has('submitted'),
            'publicMemberFeedbackError' => $errorMessage,
            'requestedFeedbackSession' => normalize_public_member_feedback_session(
                $request->query('feedback_session', $request->query('session', '')),
            ),
        ]);
    }

    public function store(
        StoreMemberFeedbackJournalRequest $request,
        MemberFeedbackQuestionCatalog $questionCatalog,
    ): RedirectResponse {
        $publicBranch = $request->publicBranch();
        $feedbackSession = $request->feedbackSession();

        try {
            DB::transaction(function () use ($request, $questionCatalog): void {
                $groupRow = $request->groupRow();
                $groupId = $request->groupId();
                $leaderId = (int) ($groupRow['leader_id'] ?? 0);
                $respondentId = $request->respondentPersonId();

                $groupProgress = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
                if ($groupProgress === '') {
                    $groupProgress = 'DG 1';
                }

                $ratings = [];
                $notes = [];

                $journalData = [
                    'branch_id' => branch_id_from_slug($request->publicBranch()),
                    'feedback_session' => $request->feedbackSession(),
                    'discipleship_group_id' => $groupId,
                    'leader_person_id' => $leaderId,
                    'respondent_person_id' => $respondentId,
                    'respondent_name_snapshot' => $request->respondentName(),
                    'leader_name_snapshot' => trim((string) ($groupRow['leader_name'] ?? '')) ?: null,
                    'group_label_snapshot' => public_member_feedback_group_option_label($groupRow),
                    'group_progress_snapshot' => $groupProgress,
                    'source' => 'public_form',
                ];

                if (Schema::hasColumn('jurnal_umpan_balik', 'ratings')) {
                    $journalData['ratings'] = [];
                }
                if (Schema::hasColumn('jurnal_umpan_balik', 'notes')) {
                    $journalData['notes'] = [];
                }

                $journal = DiscipleshipFeedback::query()->create($journalData);

                $ratingMeta = [];
                foreach ($questionCatalog->ratingQuestions() as $question) {
                    $ratingMeta[$question['key']] = $question;
                }

                foreach ($request->ratingValues() as $questionKey => $score) {
                    $meta = $ratingMeta[$questionKey] ?? ['section_key' => null, 'scale' => 10];
                    $ratings[] = [
                        'section_key' => $meta['section_key'],
                        'question_key' => $questionKey,
                        'score' => $score,
                        'scale' => $meta['scale'],
                    ];
                }

                foreach ($request->noteValues() as $noteKey => $content) {
                    $noteRow = [
                        'section_key' => $request->noteSectionKeys()[$noteKey] ?? null,
                        'note_key' => $noteKey,
                        'content' => $content !== '' ? $content : null,
                    ];

                    $notes[] = $noteRow;
                }

                $updateData = [];
                if (Schema::hasColumn('jurnal_umpan_balik', 'ratings')) {
                    $updateData['ratings'] = $ratings ?? [];
                }
                if (Schema::hasColumn('jurnal_umpan_balik', 'notes')) {
                    $updateData['notes'] = $notes ?? [];
                }
                if ($updateData !== []) {
                    $journal->forceFill($updateData)->save();
                }
            });
        } catch (Throwable) {
            session()->put('public_member_feedback_error', 'Jurnal umpan balik gagal disimpan. Coba ulangi lagi.');

            return redirect()->route('public.member-feedback.form', [
                'cabang' => $publicBranch,
                'feedback_session' => $feedbackSession,
            ]);
        }

        session()->forget(['public_member_feedback_old', 'public_member_feedback_error']);

        return redirect()->route('public.member-feedback.form', [
            'submitted' => 1,
            'cabang' => $publicBranch,
            'feedback_session' => $feedbackSession,
        ]);
    }
}
