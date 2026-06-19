<?php

namespace App\Http\Requests\MemberFeedbackJournals;

use App\Services\MemberFeedbackJournals\MemberFeedbackFormData;
use App\Services\MemberFeedbackJournals\MemberFeedbackQuestionCatalog;
use App\Services\MemberFeedbackJournals\MemberFeedbackTextNormalizer;
use App\Support\RuntimeBootstrap;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreMemberFeedbackJournalRequest extends FormRequest
{
    private string $publicBranch = '';

    private int $feedbackSession = 0;

    /**
     * @var array<string, mixed>
     */
    private array $groupRow = [];

    private string $respondentName = '';

    /**
     * @var array<string, int>
     */
    private array $ratingValues = [];

    /**
     * @var array<string, string>
     */
    private array $noteValues = [];

    /**
     * @var array<string, string>
     */
    private array $ratingSectionKeys = [];

    /**
     * @var array<string, string>
     */
    private array $noteSectionKeys = [];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        RuntimeBootstrap::boot($this);
        session()->put('public_member_feedback_old', $this->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branchRaw = trim((string) $this->input('public_cabang', ''));
            if ($branchRaw === '') {
                if (is_logged_in()) {
                    $this->publicBranch = current_user_branch();
                } else {
                    $validator->errors()->add('public_cabang_missing', 'missing_branch');

                    return;
                }
            } elseif (! is_known_public_branch_code($branchRaw)) {
                $validator->errors()->add('public_cabang', 'invalid_branch');

                return;
            } else {
                $this->publicBranch = normalize_public_branch_code($branchRaw);
            }

            session()->put('public_member_feedback_old.public_cabang', $this->publicBranch);

            $formData = app(MemberFeedbackFormData::class)->forBranch($this->publicBranch);
            $groupMap = $formData['group_map'];
            $groupId = trim((string) $this->input('group_id', ''));
            $respondentPersonId = trim((string) $this->input('respondent_person_id', ''));

            if (count($groupMap) === 0) {
                $validator->errors()->add('public_member_feedback_error', 'Belum ada Kelompok DG yang bisa dipilih.');
            } elseif ($groupId === '' || ! isset($groupMap[$groupId]) || ! is_array($groupMap[$groupId])) {
                $validator->errors()->add('public_member_feedback_error', 'Pilih kelompok DG terlebih dahulu.');
            } else {
                $this->groupRow = $groupMap[$groupId];
                $memberMap = $this->groupMemberMap($this->groupRow);
                if ($respondentPersonId === '' || ! isset($memberMap[$respondentPersonId])) {
                    $validator->errors()->add('public_member_feedback_error', 'Pilih nama pengisi sesuai anggota kelompok DG.');
                } else {
                    $this->respondentName = $memberMap[$respondentPersonId];
                }
            }

            $this->feedbackSession = normalize_public_member_feedback_session($this->input('feedback_session', ''));
            if (! $validator->errors()->has('public_member_feedback_error') && $this->feedbackSession === 0) {
                $validator->errors()->add('public_member_feedback_error', 'Pilih pertemuan umpan balik: pertemuan 3 atau 12.');
            }

            $this->validateRatings($validator);
            $this->collectNotes();
        });
    }

    public function publicBranch(): string
    {
        return $this->publicBranch;
    }

    public function feedbackSession(): int
    {
        return $this->feedbackSession;
    }

    /**
     * @return array<string, mixed>
     */
    public function groupRow(): array
    {
        return $this->groupRow;
    }

    public function respondentName(): string
    {
        return $this->respondentName;
    }

    /**
     * @return array<string, int>
     */
    public function ratingValues(): array
    {
        return $this->ratingValues;
    }

    /**
     * @return array<string, string>
     */
    public function noteValues(): array
    {
        return $this->noteValues;
    }

    /**
     * @return array<string, string>
     */
    public function ratingSectionKeys(): array
    {
        return $this->ratingSectionKeys;
    }

    /**
     * @return array<string, string>
     */
    public function noteSectionKeys(): array
    {
        return $this->noteSectionKeys;
    }

    protected function failedValidation(Validator $validator)
    {
        if ($validator->errors()->has('public_cabang_missing')) {
            throw new HttpResponseException(redirect()->route('public.member-feedback.branch'));
        }

        if ($validator->errors()->has('public_cabang')) {
            throw new HttpResponseException(redirect()->route('public.member-feedback.branch', ['error' => 'invalid_branch']));
        }

        session()->put('public_member_feedback_error', $validator->errors()->first('public_member_feedback_error'));

        $params = [];
        $branch = $this->publicBranch !== '' ? $this->publicBranch : trim((string) $this->input('public_cabang', ''));
        if ($branch !== '') {
            $params['cabang'] = $branch;
        }
        $feedbackSession = normalize_public_member_feedback_session($this->input('feedback_session', ''));
        if ($feedbackSession !== 0) {
            $params['feedback_session'] = $feedbackSession;
        }

        throw new HttpResponseException(redirect()->route('public.member-feedback.form', $params));
    }

    /**
     * @param  array<string, mixed>  $groupRow
     * @return array<string, string>
     */
    private function groupMemberMap(array $groupRow): array
    {
        $members = $groupRow['members'] ?? [];
        if (! is_array($members)) {
            return [];
        }

        $memberMap = [];
        foreach ($members as $memberRow) {
            if (! is_array($memberRow)) {
                continue;
            }

            $memberId = trim((string) ($memberRow['id'] ?? ''));
            $memberName = trim((string) ($memberRow['name'] ?? ''));
            if ($memberId !== '' && $memberName !== '') {
                $memberMap[$memberId] = $memberName;
            }
        }

        return $memberMap;
    }

    private function validateRatings(Validator $validator): void
    {
        $ratingsRaw = $this->input('ratings', []);
        if (! is_array($ratingsRaw)) {
            $ratingsRaw = [];
        }

        foreach (app(MemberFeedbackQuestionCatalog::class)->ratingQuestions() as $question) {
            $key = $question['key'];
            $scale = $question['scale'];
            $rawValue = trim((string) ($ratingsRaw[$key] ?? ''));
            $value = is_numeric($rawValue) ? (int) $rawValue : 0;
            if (! $validator->errors()->has('public_member_feedback_error') && ($value < 1 || $value > $scale)) {
                $validator->errors()->add('public_member_feedback_error', 'Isi semua pertanyaan skala yang wajib.');
            }
            if ($value >= 1 && $value <= $scale) {
                $this->ratingValues[$key] = $value;
                $this->ratingSectionKeys[$key] = $question['section_key'];
            }
        }
    }

    private function collectNotes(): void
    {
        $notesRaw = $this->input('notes', []);
        if (! is_array($notesRaw)) {
            $notesRaw = [];
        }

        $normalizer = app(MemberFeedbackTextNormalizer::class);
        foreach (app(MemberFeedbackQuestionCatalog::class)->noteQuestions() as $note) {
            $key = $note['key'];
            $this->noteValues[$key] = $normalizer->normalize((string) ($notesRaw[$key] ?? ''), 2500);
            $this->noteSectionKeys[$key] = $note['section_key'];
        }
    }
}
