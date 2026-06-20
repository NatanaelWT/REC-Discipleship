<?php

namespace App\Services\SpiritualJourney;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipPerson;
use App\Models\DiscipleshipRelationship;
use App\Models\MskParticipant;
use App\Services\Discipleship\DiscipleshipReadCache;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use DateTimeInterface;
use Illuminate\Http\Request;
use Throwable;

class SpiritualJourneyPageData
{
    public function __construct(
        private readonly DiscipleshipTargetReader $targetReader,
        private readonly DiscipleshipReadCache $cache,
    ) {}

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
        $structure = $this->cache->remember('spiritual-structure', [...$branchCodes, $centralReadOnly ? 'central' : 'branch'], function () use ($branchCodes, $centralReadOnly, $selectedBranch): array {
            $branchLabels = $this->branchLabels();
            $groupPeople = $this->loadGroupPeople($branchCodes);

            return [
                'people' => $this->loadPeople($branchCodes, $centralReadOnly, $branchLabels),
                'groups' => $this->loadGroups($branchCodes, $branchLabels),
                'memberships' => array_values(array_filter($groupPeople, static fn (array $row): bool => ($row['role'] ?? '') === 'member')),
                'leaderships' => array_values(array_filter($groupPeople, static fn (array $row): bool => ($row['role'] ?? '') !== 'member')),
                'relationships' => $this->loadRelationships($branchCodes),
                'targets' => $this->targetValues($branchCodes, $selectedBranch),
            ];
        });
        $peopleById = $structure['people'];
        $search = strtolower(trim((string) $request->query('q', '')));
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $pagination = MskParticipant::query()
            ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
            ->when($search !== '', static function ($query) use ($search): void {
                $query->where(static function ($searchQuery) use ($search): void {
                    $like = '%'.$search.'%';
                    $searchQuery->whereRaw('LOWER(full_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(whatsapp) LIKE ?', [$like]);
                });
            })
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
        $participantRows = $pagination->getCollection()
            ->map(static function (MskParticipant $participant): array {
                $row = $participant->toViewArray();
                $row['branch_code'] = normalize_public_branch_code((string) $participant->branch_code);

                return $row;
            })
            ->values()
            ->all();

        return [
            'settings' => ['church_name' => app_church_name()],
            'page' => 'spiritual_journey',
            'people' => array_values($peopleById),
            'peopleById' => $peopleById,
            'mskClasses' => $participantRows,
            'spiritualJourneyPagination' => $pagination,
            'spiritualJourneySearch' => $search,
            'spiritualJourneyTotalParticipants' => $pagination->total(),
            'discipleshipTargets' => $structure['targets'],
            'discipleshipV2Model' => [
                'discipleship_persons' => array_values($peopleById),
                'discipleship_groups' => array_values($structure['groups']),
                'group_memberships' => $structure['memberships'],
                'group_leaderships' => $structure['leaderships'],
                'discipleship_relations' => $structure['relationships'],
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
     * @param  array<int, string>  $branchCodes
     * @param  array<string, string>  $branchLabels
     * @return array<string, array<string, mixed>>
     */
    private function loadPeople(array $branchCodes, bool $centralReadOnly, array $branchLabels): array
    {
        $rows = [];
        try {
            $records = DiscipleshipPerson::query()->whereIn('branch_id', branch_ids_from_slugs($branchCodes))->orderBy('id')->get();
        } catch (Throwable) {
            return [];
        }
        foreach ($records as $person) {
            $branchCode = normalize_public_branch_code((string) $person->branch_code);
            $effectiveId = $this->effectiveId($branchCode, (string) $person->getKey());
            if ($effectiveId === '') {
                continue;
            }

            $displayName = trim((string) ($person->full_name ?? ''));
            if ($centralReadOnly) {
                $displayName = append_branch_suffix($displayName, $branchLabels[$branchCode] ?? strtoupper($branchCode));
            }

            $rows[$effectiveId] = [
                'id' => $effectiveId,
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
                'member_id' => $effectiveId,
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
     * @param  array<int, string>  $branchCodes
     * @param  array<string, string>  $branchLabels
     * @return array<string, array<string, mixed>>
     */
    private function loadGroups(array $branchCodes, array $branchLabels): array
    {
        $rows = [];
        try {
            $records = DiscipleshipGroup::query()->whereIn('branch_id', branch_ids_from_slugs($branchCodes))->orderBy('id')->get();
        } catch (Throwable) {
            return [];
        }
        foreach ($records as $group) {
            $branchCode = normalize_public_branch_code((string) $group->branch_code);
            $effectiveId = $this->effectiveId($branchCode, (string) $group->getKey());
            if ($effectiveId === '') {
                continue;
            }

            $rows[$effectiveId] = [
                'id' => $effectiveId,
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
                'name' => trim((string) ($group->name ?? 'Kelompok')) ?: 'Kelompok',
                'status' => strtolower(trim((string) ($group->status ?? 'active'))) ?: 'active',
                'start_stage' => normalize_dg_progress_value((string) ($group->start_stage ?? '')),
                'current_stage' => normalize_dg_progress_value((string) ($group->current_stage ?? '')),
                'progress' => normalize_dg_progress_value((string) ($group->current_stage ?? $group->start_stage ?? '')),
                'parent_group_id' => $group->parent_group_id !== null
                    ? $this->effectiveId($branchCode, (string) $group->parent_group_id)
                    : '',
                'notes' => trim((string) ($group->notes ?? '')),
                'created_at' => $this->timestampString($group->created_at ?? null),
                'updated_at' => $this->timestampString($group->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadGroupPeople(array $branchCodes): array
    {
        $rows = [];
        try {
            $records = DiscipleshipGroupPerson::query()
                ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
                ->orderBy('id')
                ->get();
        } catch (Throwable) {
            return [];
        }

        foreach ($records as $record) {
            $branchCode = normalize_public_branch_code((string) $record->branch_code);
            $groupId = $this->effectiveId($branchCode, (string) $record->discipleship_group_id);
            $personId = $this->effectiveId($branchCode, (string) $record->person_id);
            if ($groupId === '' || $personId === '') {
                continue;
            }

            $role = strtolower(trim((string) ($record->role ?? 'member'))) ?: 'member';
            $rows[] = [
                'id' => (string) $record->getKey(),
                'branch_code' => $branchCode,
                'group_id' => $groupId,
                'person_id' => $personId,
                'role' => $role,
                'leader_person_id' => $personId,
                'stage' => normalize_dg_progress_value((string) ($record->stage ?? '')),
                'status' => strtolower(trim((string) ($record->status ?? 'active'))) ?: 'active',
                'start_date' => $this->dateString($record->started_on ?? null),
                'end_date' => $this->dateString($record->ended_on ?? null),
                'reason_end' => trim((string) ($record->end_reason ?? '')),
                'reason_change' => trim((string) ($record->end_reason ?? '')),
                'created_at' => $this->timestampString($record->created_at ?? null),
                'updated_at' => $this->timestampString($record->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadRelationships(array $branchCodes): array
    {
        $rows = [];
        try {
            $records = DiscipleshipRelationship::query()->whereIn('branch_id', branch_ids_from_slugs($branchCodes))->orderBy('id')->get();
        } catch (Throwable) {
            return [];
        }
        foreach ($records as $relationship) {
            $branchCode = normalize_public_branch_code((string) $relationship->branch_code);
            $mentorId = $this->effectiveId($branchCode, (string) $relationship->mentor_person_id);
            $discipleId = $this->effectiveId($branchCode, (string) $relationship->disciple_person_id);
            if ($mentorId === '' || $discipleId === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) $relationship->getKey(),
                'branch_code' => $branchCode,
                'mentor_person_id' => $mentorId,
                'disciple_person_id' => $discipleId,
                'context_group_id' => $relationship->context_group_id !== null
                    ? $this->effectiveId($branchCode, (string) $relationship->context_group_id)
                    : '',
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
     * @param  array<int, string>  $branchCodes
     * @return array<string, int>
     */
    private function targetValues(array $branchCodes, string $selectedBranch): array
    {
        $targetsByBranch = $this->targetReader->formValuesForBranches($branchCodes);
        if ($selectedBranch !== 'all' || count($branchCodes) <= 1) {
            return $targetsByBranch[$branchCodes[0] ?? ''] ?? default_discipleship_targets();
        }

        $totals = [
            'dg_total_people' => 0,
            'msk_completed' => 0,
            'dg1_people' => 0,
            'dg2_people' => 0,
            'dg3_people' => 0,
        ];

        foreach ($targetsByBranch as $targets) {
            foreach ($targets as $key => $value) {
                if (array_key_exists($key, $totals)) {
                    $totals[$key] += (int) $value;
                }
            }
        }

        return $totals;
    }

    private function effectiveId(string $branchCode, string $id): string
    {
        return trim($id);
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
