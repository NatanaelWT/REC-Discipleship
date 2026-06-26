<?php

namespace App\Services\SpiritualJourney;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipPerson;
use App\Models\DiscipleshipRelationship;
use App\Models\MskParticipant;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use App\Services\MskParticipants\MskParticipantHistoryData;
use App\Services\MskParticipants\MskParticipantProfileData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpiritualJourneyPageData
{
    public function __construct(
        private readonly DiscipleshipTargetReader $targetReader,
        private readonly CurrentDiscipleshipScope $scope,
        private readonly MskParticipantHistoryData $historyData,
        private readonly MskParticipantProfileData $profileData,
    ) {}

    /** @return array<string, mixed> */
    public function forCurrentContext(Request $request): array
    {
        $search = strtolower(trim((string) $request->query('q', '')));
        $journeyFilter = trim((string) $request->query('journey_filter', 'all'));
        $query = MskParticipant::query()
            ->select([
                'id', 'branch_id', 'discipleship_person_id', 'full_name', 'gender', 'birth_date', 'birth_day_month',
                'birth_place', 'address', 'email', 'whatsapp', 'batch_month', 'notes', 'completed_at',
                'journey_bridge_status', 'status', 'session_numbers', 'photos', 'created_at', 'updated_at',
            ])
            ->whereIn('branch_id', $this->scope->branchIds());
        $this->applyJourneyFilter($query, $journeyFilter);

        $participants = $query
            ->orderBy('full_name')->orderBy('id')
            ->get();
        $personIds = $participants->pluck('discipleship_person_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->all();
        $people = $this->people($personIds);
        $groupPeople = $this->groupPeople($personIds);
        $groupIds = $groupPeople->pluck('discipleship_group_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->all();
        $groups = $this->groups($groupIds);
        $relationships = $this->relationships($personIds);
        $branches = $this->scope->optionsById();

        $participantRows = $participants->map(static function (MskParticipant $participant) use ($branches): array {
            $row = $participant->toViewArray();
            $row['branch_code'] = $branches[(int) $participant->branch_id]['slug'] ?? '';

            return $row;
        })->values()->all();

        $participantHistories = $this->historyData->forParticipants($participantRows, $this->scope->branchIds());

        return [
            'settings' => ['church_name' => app_church_name()],
            'page' => 'spiritual_journey',
            'people' => array_values($people),
            'peopleById' => $people,
            'mskClasses' => $participantRows,
            'spiritualJourneySearch' => $search,
            'spiritualJourneyFilter' => $journeyFilter,
            'spiritualJourneyTotalParticipants' => $participants->count(),
            'discipleshipTargets' => $this->targets(),
            'participantHistories' => $participantHistories,
            'participantProfiles' => $this->profileData->forParticipants($participantRows, $participantHistories),
            'discipleshipV2Model' => [
                'discipleship_persons' => array_values($people),
                'discipleship_groups' => array_values($groups),
                'group_memberships' => array_values(array_filter($groupPeople->map(fn ($row): array => $this->groupPersonRow($row))->all(), static fn (array $row): bool => $row['role'] === 'member')),
                'group_leaderships' => array_values(array_filter($groupPeople->map(fn ($row): array => $this->groupPersonRow($row))->all(), static fn (array $row): bool => $row['role'] !== 'member')),
                'discipleship_relations' => $relationships,
            ],
        ];
    }

    private function applyJourneyFilter(Builder $query, string &$journeyFilter): void
    {
        if ($journeyFilter !== 'dg_without_kgap') {
            $journeyFilter = 'all';

            return;
        }

        $hasGroupPeople = Schema::hasTable('discipleship_group_people');
        $hasManualJourney = Schema::hasTable('discipleship_manual_journey_records');

        if (! $hasGroupPeople && ! $hasManualJourney) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(static function (Builder $condition): void {
            $condition->whereNull('msk_participants.journey_bridge_status')
                ->orWhereNotIn('msk_participants.journey_bridge_status', ['sudah_kgap', 'ikut_keduanya']);
        });
        $query->whereNotNull('msk_participants.discipleship_person_id');
        $query->where(static function (Builder $condition) use ($hasGroupPeople, $hasManualJourney): void {
            if ($hasGroupPeople) {
                $condition->whereExists(static function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('discipleship_group_people as filter_gp')
                        ->whereColumn('filter_gp.person_id', 'msk_participants.discipleship_person_id')
                        ->whereColumn('filter_gp.branch_id', 'msk_participants.branch_id')
                        ->where('filter_gp.role', 'member')
                        ->whereIn('filter_gp.stage', ['DG 1', 'DG 2', 'DG 3']);
                });
            }
            if ($hasManualJourney) {
                $method = $hasGroupPeople ? 'orWhereExists' : 'whereExists';
                $condition->{$method}(static function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('discipleship_manual_journey_records as manual_journey')
                        ->whereColumn('manual_journey.person_id', 'msk_participants.discipleship_person_id')
                        ->whereColumn('manual_journey.branch_id', 'msk_participants.branch_id')
                        ->whereIn('manual_journey.stage', ['DG 1', 'DG 2', 'DG 3']);
                });
            }
        });
    }

    private function people(array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }
        $branches = $this->scope->optionsById();
        $rows = [];
        foreach (DiscipleshipPerson::query()->whereIn('id', $personIds)->get([
            'id', 'branch_id', 'full_name', 'phone', 'gender', 'status', 'notes', 'campus', 'major', 'occupation', 'created_at', 'updated_at',
        ]) as $person) {
            $name = trim((string) $person->full_name) ?: '-';
            $branch = $branches[(int) $person->branch_id] ?? ['slug' => '', 'label' => 'Tanpa cabang'];
            if ($this->scope->includesAllBranches()) {
                $name = append_branch_suffix($name, $branch['label']);
            }
            $rows[(string) $person->id] = [
                'id' => (string) $person->id, 'member_id' => (string) $person->id,
                'branch_code' => $branch['slug'], 'branch_label' => $branch['label'],
                'name' => $name, 'full_name' => $name, 'phone' => trim((string) $person->phone),
                'gender' => trim((string) $person->gender), 'status' => (string) $person->status,
                'notes' => trim((string) $person->notes), 'campus' => trim((string) $person->campus),
                'major' => trim((string) $person->major), 'occupation' => trim((string) $person->occupation),
                'created_at' => (string) $person->created_at, 'updated_at' => (string) $person->updated_at,
            ];
        }

        return $rows;
    }

    private function groupPeople(array $personIds)
    {
        if ($personIds === []) {
            return collect();
        }

        $rows = DiscipleshipGroupPerson::query()->whereIn('person_id', $personIds)->get([
            'id', 'branch_id', 'discipleship_group_id', 'person_id', 'role', 'stage', 'status', 'started_on', 'ended_on', 'end_reason', 'created_at', 'updated_at',
        ]);

        return $rows->merge($this->manualGroupPeople($personIds));
    }

    private function groups(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }
        $branches = $this->scope->optionsById();
        $rows = [];
        foreach (DiscipleshipGroup::query()->whereIn('id', $groupIds)->get([
            'id', 'branch_id', 'name', 'status', 'start_stage', 'current_stage', 'parent_group_id', 'notes', 'created_at', 'updated_at',
        ]) as $group) {
            $branch = $branches[(int) $group->branch_id] ?? ['slug' => '', 'label' => 'Tanpa cabang'];
            $rows[(string) $group->id] = [
                'id' => (string) $group->id, 'branch_code' => $branch['slug'], 'branch_label' => $branch['label'],
                'name' => trim((string) $group->name) ?: 'Kelompok', 'status' => (string) $group->status,
                'start_stage' => normalize_dg_progress_value((string) $group->start_stage),
                'current_stage' => normalize_dg_progress_value((string) $group->current_stage),
                'progress' => normalize_dg_progress_value((string) ($group->current_stage ?: $group->start_stage)),
                'parent_group_id' => $group->parent_group_id !== null ? (string) $group->parent_group_id : '',
                'notes' => trim((string) $group->notes), 'created_at' => (string) $group->created_at, 'updated_at' => (string) $group->updated_at,
            ];
        }

        return $rows;
    }

    private function relationships(array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }

        return DiscipleshipRelationship::query()
            ->where(function ($query) use ($personIds): void {
                $query->whereIn('mentor_person_id', $personIds)->orWhereIn('disciple_person_id', $personIds);
            })
            ->get(['id', 'branch_id', 'mentor_person_id', 'disciple_person_id', 'context_group_id', 'relation_type', 'stage_at_start', 'status', 'start_date', 'end_date', 'reason_end', 'notes', 'created_at', 'updated_at'])
            ->map(static fn (DiscipleshipRelationship $row): array => [
                'id' => (string) $row->id, 'mentor_person_id' => (string) $row->mentor_person_id,
                'disciple_person_id' => (string) $row->disciple_person_id,
                'context_group_id' => $row->context_group_id !== null ? (string) $row->context_group_id : '',
                'relation_type' => (string) $row->relation_type, 'stage_at_start' => (string) $row->stage_at_start,
                'status' => (string) $row->status, 'start_date' => (string) $row->start_date,
                'end_date' => (string) $row->end_date, 'reason_end' => (string) $row->reason_end,
                'notes' => (string) $row->notes, 'created_at' => (string) $row->created_at, 'updated_at' => (string) $row->updated_at,
            ])->all();
    }

    private function groupPersonRow(DiscipleshipGroupPerson $row): array
    {
        $source = (string) ($row->source ?? '');

        return [
            'id' => (string) $row->id, 'group_id' => (string) $row->discipleship_group_id,
            'person_id' => (string) $row->person_id, 'leader_person_id' => $source === 'manual' ? '' : (string) $row->person_id,
            'role' => strtolower((string) $row->role), 'stage' => normalize_dg_progress_value((string) $row->stage),
            'status' => (string) $row->status, 'start_date' => (string) $row->started_on,
            'end_date' => (string) $row->ended_on, 'reason_end' => (string) $row->end_reason,
            'reason_change' => (string) $row->end_reason, 'created_at' => (string) $row->created_at, 'updated_at' => (string) $row->updated_at,
            'source' => $source, 'notes' => (string) ($row->notes ?? ''),
        ];
    }

    private function manualGroupPeople(array $personIds)
    {
        if (! Schema::hasTable('discipleship_manual_journey_records')) {
            return collect();
        }

        return DB::table('discipleship_manual_journey_records')
            ->whereIn('person_id', $personIds)
            ->orderBy('id')
            ->get()
            ->map(static function (object $row): DiscipleshipGroupPerson {
                $model = new DiscipleshipGroupPerson;
                $model->forceFill([
                    'id' => -1 * (int) $row->id,
                    'branch_id' => (int) $row->branch_id,
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
                    'notes' => trim((string) ($row->notes ?? '')),
                ]);

                return $model;
            });
    }

    private function targets(): array
    {
        $slugs = array_values(array_filter(array_map(fn (int $id): string => $this->scope->optionsById()[$id]['slug'] ?? '', $this->scope->branchIds())));
        $byBranch = $this->targetReader->formValuesForBranches($slugs);
        if (count($slugs) === 1) {
            return $byBranch[$slugs[0]] ?? default_discipleship_targets();
        }
        $total = ['dg_total_people' => 0, 'msk_completed' => 0, 'dg1_people' => 0, 'dg2_people' => 0, 'dg3_people' => 0];
        foreach ($byBranch as $targets) {
            foreach ($total as $key => $value) {
                $total[$key] += (int) ($targets[$key] ?? 0);
            }
        }

        return $total;
    }
}
