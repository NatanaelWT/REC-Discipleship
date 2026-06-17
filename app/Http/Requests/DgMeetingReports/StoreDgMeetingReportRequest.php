<?php

namespace App\Http\Requests\DgMeetingReports;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipPerson;
use App\Services\DgMeetingReports\DgMeetingReportFormData;
use App\Support\RuntimeBootstrap;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDgMeetingReportRequest extends FormRequest
{
    private string $publicBranch = '';

    /**
     * @var array<string, mixed>
     */
    private array $groupRow = [];

    /**
     * @var array<string, string>
     */
    private array $groupMemberMap = [];

    /**
     * @var array<int, string>
     */
    private array $absentMemberIds = [];

    /**
     * @var array<int, string>
     */
    private array $meditationSharerIds = [];

    private string $meetingDate = '';

    private string $materialLabel = '';

    private string $groupProgress = 'DG 1';

    private int $meditationMinTimes = 2;

    private int $sharingOpennessScore = 0;

    private ?int $leaderPersonId = null;

    private ?int $discipleshipGroupId = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        RuntimeBootstrap::boot($this);

        $routeBranch = trim((string) $this->route('branch', ''));
        if (trim((string) $this->input('public_cabang', '')) === '' && $routeBranch !== '') {
            $this->merge(['public_cabang' => $routeBranch]);
        }

        $_SESSION['public_dg_report_old'] = $this->all();
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

            $_SESSION['public_dg_report_old']['public_cabang'] = $this->publicBranch;

            $formData = app(DgMeetingReportFormData::class)->forBranch($this->publicBranch);
            $groupMap = $formData['group_map'];
            $leaderMap = [];
            foreach ($formData['leaders'] as $leaderRow) {
                $leaderId = trim((string) ($leaderRow['id'] ?? ''));
                if ($leaderId !== '') {
                    $leaderMap[$leaderId] = true;
                }
            }

            $leaderId = $this->leaderPublicId();
            $groupId = $this->groupPublicId();

            if (count($groupMap) === 0) {
                $this->addFirstError($validator, 'Belum ada Kelompok DG yang bisa dipilih.');
            } elseif ($leaderId === '' || ! isset($leaderMap[$leaderId])) {
                $this->addFirstError($validator, 'Pilih nama pemimpin DG terlebih dahulu.');
            } elseif ($groupId === '' || ! isset($groupMap[$groupId]) || ! is_array($groupMap[$groupId])) {
                $this->addFirstError($validator, 'Pilih kelompok DG terlebih dahulu.');
            } else {
                $this->groupRow = $groupMap[$groupId];
                $groupLeaderId = trim((string) ($this->groupRow['leader_id'] ?? ''));
                if ($groupLeaderId !== $leaderId) {
                    $this->addFirstError($validator, 'Kelompok yang dipilih tidak sesuai dengan pemimpin DG.');
                }
            }

            $this->meetingDate = normalize_ymd_date((string) $this->input('meeting_date', ''));
            if (! $this->hasReportError($validator) && $this->meetingDate === '') {
                $this->addFirstError($validator, 'Tanggal pelaksanaan tidak valid.');
            }

            $materialTopic = trim((string) $this->input('material_topic', ''));
            $materialTopicOther = trim((string) $this->input('material_topic_other', ''));
            if (! $this->hasReportError($validator) && ! in_array($materialTopic, $formData['material_options'], true)) {
                $this->addFirstError($validator, 'Pilih materi DG yang dibahas.');
            }
            if (! $this->hasReportError($validator) && $materialTopic === 'Lainnya' && $materialTopicOther === '') {
                $this->addFirstError($validator, 'Isi materi DG pada kolom lainnya.');
            }
            $this->materialLabel = $materialTopic === 'Lainnya' ? $materialTopicOther : $materialTopic;

            if ($this->groupRow !== []) {
                $this->groupProgress = normalize_dg_progress_value((string) ($this->groupRow['progress'] ?? ''));
                if ($this->groupProgress === '') {
                    $this->groupProgress = 'DG 1';
                }
                $this->meditationMinTimes = dg_progress_min_share_times($this->groupProgress);
                $this->groupMemberMap = $this->memberMapForGroup($this->groupRow);
            }

            $this->absentMemberIds = $this->filteredMemberIds($this->input('absent_member_ids', []));
            if (! $this->hasReportError($validator) && $this->absentMemberIds !== [] && $this->absenceReason() === '') {
                $this->addFirstError($validator, 'Isi alasan anggota DG yang tidak hadir.');
            }

            $sharingOpennessRaw = trim((string) $this->input('sharing_openness', ''));
            $sharingOpenness = is_numeric($sharingOpennessRaw) ? (int) $sharingOpennessRaw : 0;
            if (! $this->hasReportError($validator) && ($sharingOpenness < 1 || $sharingOpenness > 10)) {
                $this->addFirstError($validator, 'Isi nilai sharing kelompok dari 1 sampai 10.');
            }
            $this->sharingOpennessScore = $sharingOpenness;

            $this->meditationSharerIds = $this->filteredMemberIds($this->input('meditation_sharer_ids', []));
            $this->leaderPersonId = $this->personRecordId($leaderId);
            $this->discipleshipGroupId = $this->discipleshipGroupRecordId($groupId);
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($validator->errors()->has('public_cabang_missing')) {
            throw new HttpResponseException(redirect()->route('public.dg.branch'));
        }

        if ($validator->errors()->has('public_cabang')) {
            throw new HttpResponseException(redirect()->route('public.dg.branch', ['error' => 'invalid_branch']));
        }

        $_SESSION['public_dg_report_error'] = $validator->errors()->first('public_dg_report_error');

        $branch = $this->publicBranch;
        if ($branch === '') {
            $branchRaw = trim((string) $this->input('public_cabang', ''));
            if ($branchRaw !== '' && is_known_public_branch_code($branchRaw)) {
                $branch = normalize_public_branch_code($branchRaw);
            }
        }

        if ($branch === '') {
            throw new HttpResponseException(redirect()->route('public.dg.branch'));
        }

        throw new HttpResponseException(redirect()->route('public.dg.report', ['branch' => $branch]));
    }

    public function publicBranch(): string
    {
        return $this->publicBranch;
    }

    public function leaderPublicId(): string
    {
        return trim((string) $this->input('leader_id', ''));
    }

    public function groupPublicId(): string
    {
        return trim((string) $this->input('group_id', ''));
    }

    public function leaderPersonId(): ?int
    {
        return $this->leaderPersonId;
    }

    public function discipleshipGroupId(): ?int
    {
        return $this->discipleshipGroupId;
    }

    /**
     * @return array<string, mixed>
     */
    public function groupRow(): array
    {
        return $this->groupRow;
    }

    public function leaderName(): ?string
    {
        return $this->nullableText($this->groupRow['leader_name'] ?? null);
    }

    public function groupName(): string
    {
        return $this->nullableText($this->groupRow['name'] ?? null) ?? 'Kelompok';
    }

    public function groupProgress(): string
    {
        return $this->groupProgress;
    }

    public function meetingDate(): string
    {
        return $this->meetingDate;
    }

    public function materialLabel(): string
    {
        return $this->materialLabel;
    }

    public function absenceReason(): ?string
    {
        return $this->nullableText($this->input('absence_reason', ''));
    }

    public function additionalNotes(): ?string
    {
        return $this->nullableText($this->input('additional_notes', ''));
    }

    public function preparedMaterial(): bool
    {
        return parse_bool_value($this->input('quality_prepare', false));
    }

    public function prayedForMembers(): bool
    {
        return parse_bool_value($this->input('quality_pray', false));
    }

    public function sharedMeditation(): bool
    {
        return parse_bool_value($this->input('quality_share_meditation', false));
    }

    public function relationallyContacted(): bool
    {
        return parse_bool_value($this->input('quality_relational', false));
    }

    public function sharingOpennessScore(): int
    {
        return $this->sharingOpennessScore;
    }

    public function meditationMinTimes(): int
    {
        return $this->meditationMinTimes;
    }

    /**
     * @return array<int, string>
     */
    public function absentMemberIds(): array
    {
        return $this->absentMemberIds;
    }

    /**
     * @return array<int, string>
     */
    public function meditationSharerIds(): array
    {
        return $this->meditationSharerIds;
    }

    public function memberName(string $personPublicId): ?string
    {
        return $this->nullableText($this->groupMemberMap[$personPublicId] ?? null);
    }

    public function memberRecordId(string $personPublicId): ?int
    {
        return $this->personRecordId($personPublicId);
    }

    private function addFirstError(Validator $validator, string $message): void
    {
        if (! $this->hasReportError($validator)) {
            $validator->errors()->add('public_dg_report_error', $message);
        }
    }

    private function hasReportError(Validator $validator): bool
    {
        return $validator->errors()->has('public_dg_report_error');
    }

    /**
     * @param array<string, mixed> $groupRow
     * @return array<string, string>
     */
    private function memberMapForGroup(array $groupRow): array
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

    /**
     * @return array<int, string>
     */
    private function filteredMemberIds(mixed $rawIds): array
    {
        if (! is_array($rawIds)) {
            return [];
        }

        $ids = [];
        foreach ($rawIds as $memberId) {
            $memberId = trim((string) $memberId);
            if ($memberId === '' || ! isset($this->groupMemberMap[$memberId]) || in_array($memberId, $ids, true)) {
                continue;
            }

            $ids[] = $memberId;
        }

        return $ids;
    }

    private function personRecordId(string $publicId): ?int
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }

        $id = DiscipleshipPerson::query()
            ->where('branch_code', $this->publicBranch)
            ->where(static function ($query) use ($publicId): void {
                $query->where('public_id', $publicId)
                    ->orWhere('member_public_id', $publicId);
            })
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function discipleshipGroupRecordId(string $publicId): ?int
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return null;
        }

        $id = DiscipleshipGroup::query()
            ->where('branch_code', $this->publicBranch)
            ->where('public_id', $publicId)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
