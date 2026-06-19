<?php

namespace App\Services\SpiritualJourney;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipGroupLeadership;
use App\Models\DiscipleshipGroupMembership;
use App\Models\DiscipleshipPerson;
use App\Models\DiscipleshipRelationship;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use App\Services\MskParticipants\MskParticipantTableData;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SpiritualJourneyPageData
{
    public function __construct(
        private readonly MskParticipantTableData $mskParticipantTableData,
        private readonly DiscipleshipTargetReader $targetReader,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forCurrentContext(Request $request): array
    {
        $centralReadOnly = is_effective_central_discipleship_readonly();
        $selectedBranch = $centralReadOnly
            ? normalize_central_recap_branch(central_recap_selected_branch())
            : normalize_public_branch_code(current_user_branch());

        $branchCodes = $this->branchCodes($selectedBranch, $centralReadOnly);
        $branchLabels = $this->branchLabels();
        $peopleById = $this->loadPeople($branchCodes, $centralReadOnly, $branchLabels);
        $groups = $this->loadGroups($branchCodes, $branchLabels);
        $memberships = $this->loadMemberships($branchCodes);
        $leaderships = $this->loadLeaderships($branchCodes);
        $relationships = $this->loadRelationships($branchCodes);

        return [
            'settings' => ['church_name' => app_church_name()],
            'page' => 'spiritual_journey',
            'people' => array_values($peopleById),
            'peopleById' => $peopleById,
            'mskClasses' => $this->mskParticipantTableData->participantsForBranches($branchCodes),
            'discipleshipTargets' => $this->targetValues($branchCodes, $selectedBranch),
            'discipleshipV2Model' => [
                'discipleship_persons' => array_values($peopleById),
                'discipleship_groups' => array_values($groups),
                'group_memberships' => $memberships,
                'group_leaderships' => $leaderships,
                'discipleship_relations' => $relationships,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function branchCodes(string $selectedBranch, bool $centralReadOnly): array
    {
        if ($centralReadOnly && $selectedBranch === 'all') {
            return array_values(array_filter(array_map(
                static fn (array $option): string => normalize_public_branch_code((string) ($option['code'] ?? '')),
                public_dg_branch_options(),
            ), static fn (string $branchCode): bool => $branchCode !== ''));
        }

        return [$selectedBranch];
    }

    /**
     * @return array<string, string>
     */
    private function branchLabels(): array
    {
        $labels = [];
        foreach (public_dg_branch_options() as $option) {
            $branchCode = normalize_public_branch_code((string) ($option['code'] ?? ''));
            if ($branchCode === '') {
                continue;
            }
            $label = trim((string) ($option['label'] ?? strtoupper($branchCode)));
            $labels[$branchCode] = $label !== '' ? $label : strtoupper($branchCode);
        }

        return $labels;
    }

    /**
     * @param array<int, string> $branchCodes
     * @param array<string, string> $branchLabels
     * @return array<string, array<string, mixed>>
     */
    private function loadPeople(array $branchCodes, bool $centralReadOnly, array $branchLabels): array
    {
        if (! Schema::hasTable('discipleship_people')) {
            return [];
        }

        $rows = [];
        foreach (DiscipleshipPerson::query()->whereIn('branch_code', $branchCodes)->orderBy('id')->get() as $person) {
            $branchCode = normalize_public_branch_code((string) $person->branch_code);
            $effectiveId = $this->effectiveId($branchCode, (string) $person->public_id);
            if ($effectiveId === '') {
                continue;
            }

            $displayName = trim((string) ($person->full_name ?? ''));
            if ($centralReadOnly) {
                $displayName = append_branch_suffix($displayName, $branchLabels[$branchCode] ?? strtoupper($branchCode));
            }

            $rows[$effectiveId] = [
                'id' => $effectiveId,
                'public_id' => (string) $person->public_id,
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
                'member_id' => trim((string) ($person->member_public_id ?? '')),
                'member_public_id' => trim((string) ($person->member_public_id ?? '')),
                'name' => $displayName !== '' ? $displayName : '-',
                'full_name' => $displayName !== '' ? $displayName : '-',
                'phone' => trim((string) ($person->phone ?? '')),
                'gender' => trim((string) ($person->gender ?? '')),
                'status' => trim((string) ($person->status ?? 'active')) ?: 'active',
                'notes' => trim((string) ($person->notes ?? '')),
                'campus' => trim((string) ($person->campus ?? '')),
                'major' => trim((string) ($person->major ?? '')),
                'occupation' => trim((string) ($person->occupation ?? '')),
                'created_at' => $this->timestampString($person->created_at ?? null),
                'updated_at' => $this->timestampString($person->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, string> $branchCodes
     * @param array<string, string> $branchLabels
     * @return array<string, array<string, mixed>>
     */
    private function loadGroups(array $branchCodes, array $branchLabels): array
    {
        if (! Schema::hasTable('discipleship_groups')) {
            return [];
        }

        $rows = [];
        foreach (DiscipleshipGroup::query()->whereIn('branch_code', $branchCodes)->orderBy('id')->get() as $group) {
            $branchCode = normalize_public_branch_code((string) $group->branch_code);
            $effectiveId = $this->effectiveId($branchCode, (string) $group->public_id);
            if ($effectiveId === '') {
                continue;
            }

            $rows[$effectiveId] = [
                'id' => $effectiveId,
                'public_id' => (string) $group->public_id,
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
                'name' => trim((string) ($group->name ?? 'Kelompok')) ?: 'Kelompok',
                'status' => strtolower(trim((string) ($group->status ?? 'active'))) ?: 'active',
                'start_stage' => normalize_dg_progress_value((string) ($group->start_stage ?? '')),
                'current_stage' => normalize_dg_progress_value((string) ($group->current_stage ?? '')),
                'progress' => normalize_dg_progress_value((string) ($group->current_stage ?? $group->start_stage ?? '')),
                'parent_group_id' => $this->effectiveId($branchCode, (string) ($group->parent_group_public_id ?? '')),
                'parent_group_public_id' => trim((string) ($group->parent_group_public_id ?? '')),
                'notes' => trim((string) ($group->notes ?? '')),
                'created_at' => $this->timestampString($group->created_at ?? null),
                'updated_at' => $this->timestampString($group->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadMemberships(array $branchCodes): array
    {
        if (! Schema::hasTable('discipleship_group_people') && ! Schema::hasTable('discipleship_group_memberships')) {
            return [];
        }

        $rows = [];
        $query = Schema::hasTable('discipleship_group_people')
            ? DiscipleshipGroupPerson::query()->whereIn('branch_code', $branchCodes)->where('role', 'member')->orderBy('id')
            : DiscipleshipGroupMembership::query()->whereIn('branch_code', $branchCodes)->orderBy('id');

        foreach ($query->get() as $membership) {
            $branchCode = normalize_public_branch_code((string) $membership->branch_code);
            $groupId = $this->effectiveId($branchCode, (string) $membership->group_public_id);
            $personId = $this->effectiveId($branchCode, (string) $membership->person_public_id);
            if ($groupId === '' || $personId === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) $membership->public_id,
                'branch_code' => $branchCode,
                'group_id' => $groupId,
                'person_id' => $personId,
                'role' => strtolower(trim((string) ($membership->role ?? 'member'))) ?: 'member',
                'stage' => normalize_dg_progress_value((string) ($membership->stage ?? '')),
                'status' => strtolower(trim((string) ($membership->status ?? 'active'))) ?: 'active',
                'start_date' => $this->dateString($membership->started_on ?? $membership->start_date ?? null),
                'end_date' => $this->dateString($membership->ended_on ?? $membership->end_date ?? null),
                'reason_end' => trim((string) ($membership->end_reason ?? $membership->reason_end ?? '')),
                'created_at' => $this->timestampString($membership->created_at ?? null),
                'updated_at' => $this->timestampString($membership->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadLeaderships(array $branchCodes): array
    {
        if (! Schema::hasTable('discipleship_group_people') && ! Schema::hasTable('discipleship_group_leaderships')) {
            return [];
        }

        $rows = [];
        $query = Schema::hasTable('discipleship_group_people')
            ? DiscipleshipGroupPerson::query()->whereIn('branch_code', $branchCodes)->where('role', '!=', 'member')->orderBy('id')
            : DiscipleshipGroupLeadership::query()->whereIn('branch_code', $branchCodes)->orderBy('id');

        foreach ($query->get() as $leadership) {
            $branchCode = normalize_public_branch_code((string) $leadership->branch_code);
            $groupId = $this->effectiveId($branchCode, (string) $leadership->group_public_id);
            $personId = $this->effectiveId($branchCode, (string) $leadership->person_public_id);
            if ($groupId === '' || $personId === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) $leadership->public_id,
                'branch_code' => $branchCode,
                'group_id' => $groupId,
                'person_id' => $personId,
                'leader_person_id' => $personId,
                'role' => strtolower(trim((string) ($leadership->role ?? 'leader'))) ?: 'leader',
                'status' => strtolower(trim((string) ($leadership->status ?? 'active'))) ?: 'active',
                'start_date' => $this->dateString($leadership->started_on ?? $leadership->start_date ?? null),
                'end_date' => $this->dateString($leadership->ended_on ?? $leadership->end_date ?? null),
                'reason_change' => trim((string) ($leadership->end_reason ?? $leadership->reason_change ?? '')),
                'created_at' => $this->timestampString($leadership->created_at ?? null),
                'updated_at' => $this->timestampString($leadership->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadRelationships(array $branchCodes): array
    {
        if (! Schema::hasTable('discipleship_relationships')) {
            return [];
        }

        $rows = [];
        foreach (DiscipleshipRelationship::query()->whereIn('branch_code', $branchCodes)->orderBy('id')->get() as $relationship) {
            $branchCode = normalize_public_branch_code((string) $relationship->branch_code);
            $mentorId = $this->effectiveId($branchCode, (string) $relationship->mentor_person_public_id);
            $discipleId = $this->effectiveId($branchCode, (string) $relationship->disciple_person_public_id);
            if ($mentorId === '' || $discipleId === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) $relationship->public_id,
                'branch_code' => $branchCode,
                'mentor_person_id' => $mentorId,
                'disciple_person_id' => $discipleId,
                'context_group_id' => $this->effectiveId($branchCode, (string) ($relationship->context_group_public_id ?? '')),
                'relation_type' => trim((string) ($relationship->relation_type ?? 'mentor')),
                'stage_at_start' => normalize_dg_progress_value((string) ($relationship->stage_at_start ?? '')),
                'status' => strtolower(trim((string) ($relationship->status ?? 'active'))) ?: 'active',
                'start_date' => $this->dateString($relationship->start_date ?? null),
                'end_date' => $this->dateString($relationship->end_date ?? null),
                'reason_end' => trim((string) ($relationship->reason_end ?? '')),
                'notes' => trim((string) ($relationship->notes ?? '')),
                'created_at' => $this->timestampString($relationship->created_at ?? null),
                'updated_at' => $this->timestampString($relationship->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<string, int>
     */
    private function targetValues(array $branchCodes, string $selectedBranch): array
    {
        if (! Schema::hasTable('discipleship_targets')) {
            return default_discipleship_targets();
        }

        if ($selectedBranch !== 'all' || count($branchCodes) <= 1) {
            return $this->targetReader->formValuesForBranch($branchCodes[0] ?? current_user_branch());
        }

        $totals = [
            'dg_total_people' => 0,
            'msk_completed' => 0,
            'dg1_people' => 0,
            'dg2_people' => 0,
            'dg3_people' => 0,
        ];

        foreach ($branchCodes as $branchCode) {
            foreach ($this->targetReader->formValuesForBranch($branchCode) as $key => $value) {
                if (array_key_exists($key, $totals)) {
                    $totals[$key] += (int) $value;
                }
            }
        }

        return $totals;
    }

    private function effectiveId(string $branchCode, string $publicId): string
    {
        $branchCode = normalize_public_branch_code($branchCode);
        $publicId = trim($publicId);
        if ($publicId === '') {
            return '';
        }

        return is_effective_central_discipleship_readonly()
            ? scoped_virtual_id($branchCode, $publicId)
            : $publicId;
    }

    private function timestampString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return trim((string) $value);
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }
}
