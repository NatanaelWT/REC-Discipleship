<?php

namespace App\Services\SpiritualJourney;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\Person;
use App\Models\DiscipleshipRelationship;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use App\Services\MskParticipants\MskParticipantHistoryData;
use App\Services\MskParticipants\MskParticipantProfileData;
use App\Support\DiscipleshipPersonProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpiritualJourneyPageData
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly DiscipleshipTargetReader $targetReader,
        private readonly CurrentDiscipleshipScope $scope,
        private readonly MskParticipantHistoryData $historyData,
        private readonly MskParticipantProfileData $profileData,
    ) {}

    /** @return array<string, mixed> */
    public function forCurrentContext(Request $request): array
    {
        return [
            'settings' => ['church_name' => app_church_name()],
            ...$this->paginatedRowsForCurrentContext($request),
        ];
    }

    /** @return array<string, mixed> */
    public function paginatedRowsForCurrentContext(Request $request): array
    {
        $search = strtolower(trim((string) $request->query('q', '')));
        $journeyFilter = trim((string) $request->query('journey_filter', 'all'));
        $query = Person::query()
            ->from('orang as people')
            ->select([
                'id', 'branch_id', 'full_name', 'gender', 'birth_date',
                'birth_place', 'address', 'email', 'whatsapp', 'batch_month', 'notes', 'completed_at',
                'journey_bridge_status', 'status', 'session_numbers', 'photos', 'created_at', 'updated_at',
            ])
            ->whereIn('branch_id', $this->scope->branchIds());
        $this->applyJourneyFilter($query, $journeyFilter);
        if ($search !== '') {
            $query->where(static function (Builder $builder) use ($search): void {
                $builder->whereRaw('LOWER(full_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(whatsapp) LIKE ?', ['%'.$search.'%']);
            });
        }

        $stats = $this->stats(clone $query);
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $participants = (clone $query)
            ->orderBy('full_name')->orderBy('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();
        $hasMore = $participants->count() > $perPage;
        if ($hasMore) {
            $participants = $participants->slice(0, $perPage)->values();
        }
        $personIds = $participants->pluck('id')->filter()->map(static fn ($id): int => (int) $id)->unique()->all();
        $people = $this->people($personIds);
        $groupPeople = $this->groupPeople($personIds);
        $groupIds = $groupPeople->pluck('discipleship_group_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->all();
        $groups = $this->groups($groupIds);
        $relationships = $this->relationships($personIds);
        $branches = $this->scope->optionsById();

        $participantRows = $participants->map(static function (Person $participant) use ($branches): array {
            $row = $participant->toViewArray();
            $row['branch_code'] = $branches[(int) $participant->branch_id]['slug'] ?? '';

            return $row;
        })->values()->all();

        $participantHistories = $this->historyData->forParticipants($participantRows, $this->scope->branchIds());
        $participantProfiles = $this->profileData->forParticipants($participantRows, $participantHistories);

        return [
            'page' => 'spiritual_journey',
            'people' => array_values($people),
            'peopleById' => $people,
            'mskClasses' => $participantRows,
            'spiritualJourneySearch' => $search,
            'spiritualJourneyFilter' => $journeyFilter,
            'spiritualJourneyTotalParticipants' => $stats['total'],
            'spiritualJourneyStats' => $stats,
            'spiritualJourneyPage' => $page,
            'spiritualJourneyPerPage' => $perPage,
            'hasMoreSpiritualJourneyRows' => $hasMore,
            'nextSpiritualJourneyPage' => $hasMore ? $page + 1 : null,
            'spiritualJourneyEmptyMessage' => $this->emptyMessage($search, $journeyFilter),
            'discipleshipTargets' => $this->targets(),
            'participantHistories' => $participantHistories,
            'participantProfiles' => $participantProfiles,
            'spiritualJourneyRows' => $this->journeyRows($participantRows, $groupPeople),
            'discipleshipV2Model' => [
                'discipleship_persons' => array_values($people),
                'discipleship_groups' => array_values($groups),
                'group_memberships' => array_values(array_filter($groupPeople->map(fn ($row): array => $this->groupPersonRow($row))->all(), static fn (array $row): bool => $row['role'] === 'member')),
                'group_leaderships' => array_values(array_filter($groupPeople->map(fn ($row): array => $this->groupPersonRow($row))->all(), static fn (array $row): bool => $row['role'] !== 'member')),
                'discipleship_relations' => $relationships,
            ],
        ];
    }

    /** @return array{total:int,completed_msk:int,following_kgap:int,completed_dg1:int,completed_dg2:int,completed_dg3:int} */
    private function stats(Builder $query): array
    {
        $participants = $query->get(['id', 'branch_id', 'journey_bridge_status', 'session_numbers']);
        $personIds = $participants->pluck('id')->filter()->map(static fn ($id): int => (int) $id)->unique()->all();
        $groupPeople = $this->groupPeople($personIds);
        $completion = $this->completionMaps($groupPeople);
        $completedMsk = 0;
        $followingKgap = 0;
        $completedDg1 = 0;
        $completedDg2 = 0;
        $completedDg3 = 0;

        foreach ($participants as $participant) {
            $sessionCount = count(normalize_msk_session_numbers($participant->session_numbers ?? []));
            if ($sessionCount >= 12) {
                $completedMsk++;
            }
            $bridgeStatus = normalize_journey_bridge_status((string) ($participant->journey_bridge_status ?? 'belum'));
            if (in_array($bridgeStatus, ['sudah_kgap', 'ikut_keduanya'], true)) {
                $followingKgap++;
            }
            $personId = (string) ((int) ($participant->id ?? 0));
            if ($personId !== '0' && ! empty($completion['dg1'][$personId])) {
                $completedDg1++;
            }
            if ($personId !== '0' && ! empty($completion['dg2'][$personId])) {
                $completedDg2++;
            }
            if ($personId !== '0' && ! empty($completion['dg3'][$personId])) {
                $completedDg3++;
            }
        }

        return [
            'total' => $participants->count(),
            'completed_msk' => $completedMsk,
            'following_kgap' => $followingKgap,
            'completed_dg1' => $completedDg1,
            'completed_dg2' => $completedDg2,
            'completed_dg3' => $completedDg3,
        ];
    }

    private function journeyRows(array $participantRows, $groupPeople): array
    {
        $completion = $this->completionMaps($groupPeople);
        $rows = [];
        foreach ($participantRows as $participant) {
            if (! is_array($participant)) {
                continue;
            }
            $fullName = trim((string) ($participant['full_name'] ?? ''));
            if ($fullName === '') {
                continue;
            }
            $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
            $sessionCount = min(12, count($sessionNumbers));
            $journeyViewKey = trim((string) ($participant['id'] ?? ''));
            if ($journeyViewKey === '') {
                $journeyViewKey = 'spiritual-journey-'.(string) (count($rows) + 1);
            }
            $personId = (string) ((int) ($participant['id'] ?? 0));
            $rows[] = [
                'id' => (string) ($participant['id'] ?? ''),
                'name' => $fullName,
                'search_text' => trim($fullName.' '.(string) ($participant['whatsapp'] ?? '')),
                'msk_progress' => $sessionCount > 0 ? ((string) $sessionCount.'/12') : '-',
                'session_count' => $sessionCount,
                'msk_percent' => (int) round(($sessionCount / 12) * 100),
                'session_label' => $sessionCount > 0 ? 'Sesi '.implode(', ', array_map('strval', $sessionNumbers)) : 'Belum ada sesi',
                'active_dg_progress' => '',
                'completed_dg1' => $personId !== '0' && ! empty($completion['dg1'][$personId]),
                'completed_dg2' => $personId !== '0' && ! empty($completion['dg2'][$personId]),
                'completed_dg3' => $personId !== '0' && ! empty($completion['dg3'][$personId]),
                'journey_bridge_status' => normalize_journey_bridge_status((string) ($participant['journey_bridge_status'] ?? 'belum')),
                'journey_view_key' => $journeyViewKey,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $sessionA = (int) ($a['session_count'] ?? 0);
            $sessionB = (int) ($b['session_count'] ?? 0);
            if ($sessionA !== $sessionB) {
                return $sessionB <=> $sessionA;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $rows;
    }

    /** @return array{dg1:array<string,bool>,dg2:array<string,bool>,dg3:array<string,bool>} */
    private function completionMaps($groupPeople): array
    {
        $dg1 = [];
        $dg2 = [];
        $dg3 = [];
        foreach ($groupPeople as $membershipRecord) {
            $personId = (string) ((int) ($membershipRecord->person_id ?? 0));
            if ($personId === '0') {
                continue;
            }
            $stage = normalize_dg_progress_value((string) ($membershipRecord->stage ?? ''));
            if ($stage === '') {
                continue;
            }
            $stageRank = match ($stage) {
                'DG 3' => 3,
                'DG 2' => 2,
                'DG 1' => 1,
                default => 0,
            };
            $reasonEnd = trim((string) ($membershipRecord->end_reason ?? ''));
            if ($stageRank >= 2 || ($stage === 'DG 1' && in_array($reasonEnd, ['continued_to_child_group', 'group_completed', 'stage_transition', 'manual_completion'], true))) {
                $dg1[$personId] = true;
            }
            if ($stageRank >= 3 || ($stage === 'DG 2' && in_array($reasonEnd, ['continued_to_child_group', 'group_completed', 'stage_transition', 'manual_completion'], true))) {
                $dg2[$personId] = true;
            }
            if ($stage === 'DG 3' && in_array($reasonEnd, ['group_completed', 'continued_to_child_group', 'stage_transition', 'manual_completion'], true)) {
                $dg3[$personId] = true;
            }
        }

        return ['dg1' => $dg1, 'dg2' => $dg2, 'dg3' => $dg3];
    }

    private function applyJourneyFilter(Builder $query, string &$journeyFilter): void
    {
        if ($journeyFilter !== 'dg_without_kgap') {
            $journeyFilter = 'all';

            return;
        }

        $hasGroupPeople = Schema::hasTable('keanggotaan_kelompok_dg');
        $hasManualJourney = Schema::hasTable('dg_manual');

        if (! $hasGroupPeople && ! $hasManualJourney) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(static function (Builder $condition): void {
            $condition->whereNull('people.journey_bridge_status')
                ->orWhereNotIn('people.journey_bridge_status', ['sudah_kgap', 'ikut_keduanya']);
        });
        $query->where(static function (Builder $condition) use ($hasGroupPeople, $hasManualJourney): void {
            if ($hasGroupPeople) {
                $condition->whereExists(static function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('keanggotaan_kelompok_dg as filter_gp')
                        ->whereColumn('filter_gp.person_id', 'people.id')
                        ->whereColumn('filter_gp.branch_id', 'people.branch_id')
                        ->where('filter_gp.role', 'member')
                        ->whereIn('filter_gp.stage', ['DG 1', 'DG 2', 'DG 3']);
                });
            }
            if ($hasManualJourney) {
                $method = $hasGroupPeople ? 'orWhereExists' : 'whereExists';
                $condition->{$method}(static function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('dg_manual as manual_journey')
                        ->whereColumn('manual_journey.person_id', 'people.id')
                        ->whereColumn('manual_journey.branch_id', 'people.branch_id')
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
        $query = Person::query()->from('orang as people');
        DiscipleshipPersonProfile::join($query);

        foreach ($query->whereIn('people.id', $personIds)->get([
            'people.id',
            'people.branch_id',
            'people.status',
            'people.notes',
            'people.created_at',
            'people.updated_at',
            DB::raw(DiscipleshipPersonProfile::expression('full_name').' as full_name'),
            DB::raw(DiscipleshipPersonProfile::expression('phone').' as phone'),
            DB::raw(DiscipleshipPersonProfile::expression('gender').' as gender'),
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
                'notes' => trim((string) $person->notes),
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

        $rows = DiscipleshipGroupPerson::query()
            ->whereIn('branch_id', $this->scope->branchIds())
            ->whereIn('person_id', $personIds)
            ->get([
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
            'id', 'branch_id', 'status', 'stage', 'parent_group_id', 'notes', 'created_at', 'updated_at',
        ]) as $group) {
            $branch = $branches[(int) $group->branch_id] ?? ['slug' => '', 'label' => 'Tanpa cabang'];
            $progress = discipleship_group_stage_value($group);
            $rows[(string) $group->id] = [
                'id' => (string) $group->id, 'branch_code' => $branch['slug'], 'branch_label' => $branch['label'],
                'name' => discipleship_group_display_label(['progress' => $progress]), 'status' => (string) $group->status,
                'stage' => $progress,
                'progress' => $progress,
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
        if (! Schema::hasTable('dg_manual')) {
            return collect();
        }

        return DB::table('dg_manual')
            ->whereIn('branch_id', $this->scope->branchIds())
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

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function perPage(Request $request): int
    {
        return max(1, min(self::MAX_PER_PAGE, (int) $request->query('per_page', self::DEFAULT_PER_PAGE)));
    }

    private function emptyMessage(string $search, string $journeyFilter): string
    {
        if ($journeyFilter === 'dg_without_kgap') {
            return 'Belum ada peserta minimal DG 1 yang belum mengikuti Kamp GAP.';
        }

        return $search !== '' ? 'Peserta tidak ditemukan.' : 'Belum ada data peserta MSK.';
    }
}
