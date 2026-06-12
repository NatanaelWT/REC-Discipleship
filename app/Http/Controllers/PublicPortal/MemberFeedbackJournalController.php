<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberFeedbackJournals\StoreMemberFeedbackJournalRequest;
use App\Models\DiscipleshipMemberFeedbackJournal;
use App\Services\MemberFeedbackJournals\MemberFeedbackFormData;
use App\Services\MemberFeedbackJournals\MemberFeedbackQuestionCatalog;
use App\Support\LegacyRuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class MemberFeedbackJournalController extends Controller
{
    public function selectBranch(Request $request): View
    {
        LegacyRuntimeBootstrap::boot($request);

        $feedbackSessionParam = normalize_public_member_feedback_session(
            $request->query('feedback_session', $request->query('session', '')),
        );
        $branchOptions = public_dg_branch_options();

        return view('public.member-feedback.select-branch', [
            'settings' => ['church_name' => CHURCH_NAME],
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
        LegacyRuntimeBootstrap::boot($request);

        $old = is_array($_SESSION['public_member_feedback_old'] ?? null)
            ? $_SESSION['public_member_feedback_old']
            : [];
        $publicBranchRaw = trim((string) $request->query('cabang', ''));
        if ($publicBranchRaw === '') {
            $publicBranchRaw = trim((string) ($old['public_cabang'] ?? ''));
        }

        if ($publicBranchRaw === '') {
            if (is_logged_in()) {
                $publicBranch = current_user_branch();
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

        $errorMessage = trim((string) ($_SESSION['public_member_feedback_error'] ?? ''));
        unset($_SESSION['public_member_feedback_error']);

        return view('public.member-feedback.create', [
            'settings' => ['church_name' => CHURCH_NAME],
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
                $groupPublicId = trim((string) $request->input('group_id', ''));
                $leaderPublicId = trim((string) ($groupRow['leader_id'] ?? ''));
                $respondentPublicId = trim((string) $request->input('respondent_person_id', ''));
                $groupName = trim((string) ($groupRow['name'] ?? ''));
                if ($groupName === '') {
                    $groupName = 'Kelompok';
                }

                $groupProgress = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
                if ($groupProgress === '') {
                    $groupProgress = 'DG 1';
                }

                $journal = DiscipleshipMemberFeedbackJournal::query()->create([
                    'public_id' => $this->generatePublicId(),
                    'branch_code' => $request->publicBranch(),
                    'feedback_session' => $request->feedbackSession(),
                    'discipleship_group_id' => $this->discipleshipGroupRecordId($groupPublicId),
                    'leader_person_id' => $this->peopleRecordId($leaderPublicId),
                    'respondent_person_id' => $this->peopleRecordId($respondentPublicId),
                    'respondent_name_snapshot' => $request->respondentName(),
                    'leader_name_snapshot' => trim((string) ($groupRow['leader_name'] ?? '')) ?: null,
                    'group_name_snapshot' => $groupName,
                    'group_label_snapshot' => public_member_feedback_group_option_label($groupRow),
                    'group_progress_snapshot' => $groupProgress,
                    'source' => 'public_form',
                ]);

                $ratingMeta = [];
                foreach ($questionCatalog->ratingQuestions() as $question) {
                    $ratingMeta[$question['key']] = $question;
                }

                foreach ($request->ratingValues() as $questionKey => $score) {
                    $meta = $ratingMeta[$questionKey] ?? ['section_key' => null, 'scale' => 10];
                    $journal->ratings()->create([
                        'section_key' => $meta['section_key'],
                        'question_key' => $questionKey,
                        'score' => $score,
                        'scale' => $meta['scale'],
                    ]);
                }

                foreach ($request->noteValues() as $noteKey => $content) {
                    $journal->notes()->create([
                        'section_key' => $request->noteSectionKeys()[$noteKey] ?? null,
                        'note_key' => $noteKey,
                        'content' => $content !== '' ? $content : null,
                    ]);
                }
            });
        } catch (Throwable) {
            $_SESSION['public_member_feedback_error'] = 'Jurnal umpan balik gagal disimpan. Coba ulangi lagi.';

            return redirect()->route('public.member-feedback.form', [
                'cabang' => $publicBranch,
                'feedback_session' => $feedbackSession,
            ]);
        }

        unset($_SESSION['public_member_feedback_old'], $_SESSION['public_member_feedback_error']);

        return redirect()->route('public.member-feedback.form', [
            'submitted' => 1,
            'cabang' => $publicBranch,
            'feedback_session' => $feedbackSession,
        ]);
    }

    private function generatePublicId(): string
    {
        do {
            $id = function_exists('generate_id')
                ? generate_id('dg_member_feedback')
                : 'dg_member_feedback_' . bin2hex(random_bytes(4));
        } while (DiscipleshipMemberFeedbackJournal::query()->where('public_id', $id)->exists());

        return $id;
    }

    private function discipleshipGroupRecordId(string $publicId): ?int
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }

        $id = DB::table('rec_discipleship_groups')->where('record_uid', $publicId)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function peopleRecordId(string $publicId): ?int
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }

        $id = DB::table('rec_people_registry')
            ->where('record_uid', $publicId)
            ->orWhere('dg_person_id', $publicId)
            ->orWhere('legacy_dg_person_id', $publicId)
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
