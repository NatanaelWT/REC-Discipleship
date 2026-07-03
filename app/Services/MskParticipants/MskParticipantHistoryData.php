<?php

namespace App\Services\MskParticipants;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\Person;
use App\Models\DiscipleshipRelationship;
use App\Support\DiscipleshipPersonProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MskParticipantHistoryData
{
    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<int, int>  $branchIds
     * @return array<string, array<string, mixed>>
     */
    public function forParticipants(array $participants, array $branchIds): array
    {
        $histories = [];
        $participantPersonIds = [];
        foreach ($participants as $participant) {
            $participantId = trim((string) ($participant['id'] ?? ''));
            if ($participantId === '') {
                continue;
            }
            $personId = (int) ($participant['member_id'] ?? 0);
            $histories[$participantId] = $this->emptyHistory($personId);
            if ($personId > 0) {
                $participantPersonIds[$participantId] = $personId;
            }
        }

        if (
            $participantPersonIds === []
            || $branchIds === []
            || ! Schema::hasTable('people')
            || ! Schema::hasTable('discipleship_groups')
            || ! Schema::hasTable('discipleship_group_people')
            || ! Schema::hasTable('discipleship_relationships')
        ) {
            return $histories;
        }

        $personIds = array_values(array_unique($participantPersonIds));
        $targetPeople = Person::query()
            ->whereIn('id', $personIds)
            ->get(['id', 'branch_id'])
            ->keyBy('id');
        $validPersonIds = $targetPeople->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if ($validPersonIds === []) {
            return $histories;
        }

        $targetLinks = DiscipleshipGroupPerson::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('person_id', $validPersonIds)
            ->get([
                'id', 'discipleship_group_id', 'person_id', 'role', 'stage', 'status',
                'started_on', 'ended_on', 'end_reason', 'created_at', 'updated_at',
            ]);
        $targetLinks = $targetLinks->merge($this->manualLinks($validPersonIds, $branchIds));
        $groupIds = $targetLinks->pluck('discipleship_group_id')->map(static fn ($id): int => (int) $id)->unique()->all();
        $groups = DiscipleshipGroup::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('id', $groupIds)
            ->get(['id', 'name', 'start_stage', 'current_stage'])
            ->keyBy('id');
        $allGroupLinks = $groupIds === []
            ? collect()
            : DiscipleshipGroupPerson::query()
                ->whereIn('branch_id', $branchIds)
                ->whereIn('discipleship_group_id', $groupIds)
                ->get([
                    'id', 'discipleship_group_id', 'person_id', 'role', 'stage', 'status',
                    'started_on', 'ended_on', 'end_reason', 'created_at', 'updated_at',
                ]);
        $relations = DiscipleshipRelationship::query()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('disciple_person_id', $validPersonIds)
            ->get([
                'id', 'mentor_person_id', 'disciple_person_id', 'status', 'start_date',
                'end_date', 'reason_end', 'created_at', 'updated_at',
            ]);

        $relatedPersonIds = collect($validPersonIds)
            ->merge($allGroupLinks->pluck('person_id'))
            ->merge($relations->pluck('mentor_person_id'))
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->all();
        $names = array_map(
            static fn (string $name): string => $name !== '' ? $name : '-',
            DiscipleshipPersonProfile::namesByPersonIds($relatedPersonIds),
        );

        foreach ($participantPersonIds as $participantId => $personId) {
            if (! in_array($personId, $validPersonIds, true)) {
                continue;
            }
            $personLinks = $targetLinks->where('person_id', $personId);
            $personRelations = $relations->where('disciple_person_id', $personId);
            $histories[$participantId] = $this->history(
                $personId,
                $personLinks,
                $personRelations,
                $groups,
                $allGroupLinks,
                $names,
                $this->externalContextBranchId($personId, $targetPeople, $branchIds),
            );
        }

        return $histories;
    }

    /** @return array<string, mixed> */
    private function emptyHistory(int $personId): array
    {
        return [
            'linked' => false,
            'person_id' => $personId > 0 ? (string) $personId : '',
            'current_mentors' => [],
            'current_groups' => [],
            'current_stage' => '',
            'member_items' => [],
            'leader_items' => [],
            'is_external_context' => false,
        ];
    }

    /**
     * @param  Collection<int, Person>  $people
     * @param  array<int, int>  $branchIds
     */
    private function externalContextBranchId(int $personId, Collection $people, array $branchIds): int
    {
        $person = $people->get($personId);
        $personBranchId = $person instanceof Person ? (int) $person->branch_id : 0;
        $branchIds = array_values(array_unique(array_map(static fn (mixed $branchId): int => (int) $branchId, $branchIds)));

        return count($branchIds) === 1 && $personBranchId > 0 && $branchIds[0] !== $personBranchId
            ? $branchIds[0]
            : 0;
    }

    /**
     * @param  Collection<int, DiscipleshipGroupPerson>  $personLinks
     * @param  Collection<int, DiscipleshipRelationship>  $relations
     * @param  Collection<int, DiscipleshipGroup>  $groups
     * @param  Collection<int, DiscipleshipGroupPerson>  $allGroupLinks
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function history(
        int $personId,
        Collection $personLinks,
        Collection $relations,
        Collection $groups,
        Collection $allGroupLinks,
        array $names,
        int $externalContextBranchId,
    ): array {
        $activeRelations = $relations->filter(fn (DiscipleshipRelationship $row): bool => $this->isActive($row->status, $row->end_date));
        $currentMentors = $activeRelations->map(fn (DiscipleshipRelationship $row): string => $names[(int) $row->mentor_person_id] ?? '-')
            ->filter(static fn (string $name): bool => $name !== '-')->unique()->values()->all();
        $currentGroups = [];
        $currentStage = '';
        $memberItems = [];
        $leaderItems = [];

        foreach ($personLinks as $link) {
            $isManual = trim((string) ($link->source ?? '')) === 'manual';
            $groupId = (int) $link->discipleship_group_id;
            $group = $groups->get($groupId);
            $groupName = $isManual ? 'Riwayat manual DG' : (trim((string) ($group?->name ?? '')) ?: 'Kelompok');
            $stage = normalize_dg_progress_value((string) ($link->stage ?? $group?->current_stage ?? $group?->start_stage));
            $active = $this->isActive($link->status, $link->ended_on);
            $role = strtolower(trim((string) $link->role));
            $groupLinks = $allGroupLinks->where('discipleship_group_id', $groupId);

            if ($role === 'member') {
                if ($active) {
                    $currentGroups[] = $groupName;
                    if ($this->stageRank($stage) > $this->stageRank($currentStage)) {
                        $currentStage = $stage;
                    }
                } elseif ($isManual && $this->stageRank($stage) > $this->stageRank($currentStage)) {
                    $currentStage = $stage;
                }
                $leader = $isManual
                    ? null
                    : $groupLinks
                        ->filter(static fn (DiscipleshipGroupPerson $row): bool => strtolower((string) $row->role) !== 'member')
                        ->sortByDesc(fn (DiscipleshipGroupPerson $row): string => ($this->isActive($row->status, $row->ended_on) ? '1' : '0').(string) ($row->updated_at ?? ''))
                        ->first();
                $memberItems[] = [
                    'title' => $isManual ? ('Selesai '.($stage !== '' ? $stage : 'DG').' manual') : $groupName,
                    'stage' => $stage,
                    'role' => $isManual ? 'Manual' : 'Anggota',
                    'mentor' => $leader ? ($names[(int) $leader->person_id] ?? '') : '',
                    'active' => $active,
                    'date' => $this->dateLabel($link->started_on, $link->ended_on),
                    'note' => $this->reasonLabel((string) $link->end_reason),
                    'sort_date' => (string) ($link->ended_on ?? $link->started_on ?? $link->updated_at ?? ''),
                ];
                continue;
            }

            $memberNames = $groupLinks
                ->filter(static fn (DiscipleshipGroupPerson $row): bool => strtolower((string) $row->role) === 'member')
                ->map(fn (DiscipleshipGroupPerson $row): string => $names[(int) $row->person_id] ?? '-')
                ->filter(static fn (string $name): bool => $name !== '-')->unique()->values()->all();
            $leaderItems[] = [
                'title' => $groupName,
                'stage' => $stage,
                'role' => $role === 'co_leader' ? 'Pendamping' : 'Pemimpin',
                'active' => $active,
                'date' => $this->dateLabel($link->started_on, $link->ended_on),
                'note' => $this->reasonLabel((string) $link->end_reason),
                'members' => $memberNames,
                'sort_date' => (string) ($link->ended_on ?? $link->started_on ?? $link->updated_at ?? ''),
            ];
        }

        $sort = static function (array $left, array $right): int {
            $active = ((int) ($right['active'] ?? 0)) <=> ((int) ($left['active'] ?? 0));
            if ($active !== 0) {
                return $active;
            }

            return strcmp((string) ($right['sort_date'] ?? ''), (string) ($left['sort_date'] ?? ''));
        };
        usort($memberItems, $sort);
        usort($leaderItems, $sort);

        return [
            'linked' => true,
            'person_id' => (string) $personId,
            'current_mentors' => array_values(array_unique($currentMentors)),
            'current_groups' => array_values(array_unique($currentGroups)),
            'current_stage' => $currentStage,
            'member_items' => $memberItems,
            'leader_items' => $leaderItems,
            'is_external_context' => $externalContextBranchId > 0,
        ];
    }

    private function isActive(mixed $status, mixed $endDate): bool
    {
        return strtolower(trim((string) $status)) === 'active' && trim((string) $endDate) === '';
    }

    private function stageRank(string $stage): int
    {
        return match (normalize_dg_progress_value($stage)) {
            'DG 3' => 3,
            'DG 2' => 2,
            'DG 1' => 1,
            default => 0,
        };
    }

    private function dateLabel(mixed $startDate, mixed $endDate): string
    {
        $start = normalize_ymd_date((string) $startDate);
        $end = normalize_ymd_date((string) $endDate);
        if ($start === '' && $end === '') {
            return '-';
        }

        return ($start !== '' ? format_indo_date($start) : '-').' - '.($end !== '' ? format_indo_date($end) : 'Sekarang');
    }

    private function reasonLabel(string $reason): string
    {
        return match (trim($reason)) {
            'continued_to_child_group', 'group_completed' => 'Kelompok selesai',
            'stage_transition' => 'Transisi tahap',
            'manual_completion' => 'Ditandai selesai tanpa kelompok/pemimpin',
            'group_archived' => 'Kelompok diarsipkan',
            'left_group' => 'Keluar dari kelompok',
            'removed_from_group' => 'Dikeluarkan dari kelompok',
            'person_archived' => 'Data peserta diarsipkan',
            'moved_group' => 'Pindah kelompok',
            default => trim($reason),
        };
    }

    /** @param array<int, int> $personIds */
    private function manualLinks(array $personIds, array $branchIds): Collection
    {
        if (! Schema::hasTable('discipleship_manual_journey_records')) {
            return collect();
        }

        return DB::table('discipleship_manual_journey_records')
            ->whereIn('branch_id', $branchIds)
            ->whereIn('person_id', $personIds)
            ->orderBy('id')
            ->get()
            ->map(static function (object $row): DiscipleshipGroupPerson {
                $model = new DiscipleshipGroupPerson;
                $model->forceFill([
                    'id' => -1 * (int) $row->id,
                    'discipleship_group_id' => null,
                    'person_id' => (int) $row->person_id,
                    'role' => 'member',
                    'stage' => normalize_dg_progress_value((string) $row->stage),
                    'status' => 'completed',
                    'started_on' => $row->completed_on,
                    'ended_on' => $row->completed_on,
                    'end_reason' => 'manual_completion',
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'source' => 'manual',
                ]);

                return $model;
            });
    }
}
