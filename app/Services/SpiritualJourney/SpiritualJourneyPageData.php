<?php

namespace App\Services\SpiritualJourney;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\Person;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\DiscipleshipTargets\DiscipleshipTargetReader;
use App\Services\MskParticipants\MskParticipantHistoryData;
use App\Services\MskParticipants\MskParticipantProfileData;
use App\Support\DiscipleshipPersonProfile;
use App\Support\StableNameCursor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /** @return array{participant:array<string,mixed>,profile:array<string,mixed>}|null */
    public function detailForCurrentContext(Request $request, int $participantId): ?array
    {
        if ($participantId < 1) {
            return null;
        }

        $participant = Person::query()
            ->select(Person::VIEW_COLUMNS)
            ->whereIn('branch_id', $this->scope->branchIds())
            ->whereKey($participantId)
            ->first();
        if (! $participant instanceof Person) {
            return null;
        }

        $row = $participant->toViewArray();
        $row['branch_code'] = $this->scope->optionsById()[(int) $participant->branch_id]['slug'] ?? '';
        $histories = $this->historyData->forParticipants([$row], $this->scope->branchIds());
        $profiles = $this->profileData->forParticipants([$row], $histories);
        $id = (string) $participant->getKey();

        return [
            'participant' => $row,
            'profile' => is_array($profiles[$id] ?? null) ? $profiles[$id] : [],
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
        $limit = $this->limit($request);
        $cursor = StableNameCursor::decode($request->query('cursor'));
        // full_name is normalized on write; keep the raw column order index-friendly.
        $nameExpression = 'people.full_name';
        $participantsQuery = (clone $query)
            ->addSelect(DB::raw($nameExpression.' as cursor_name'))
            ->orderByRaw($nameExpression)->orderBy('id')
            ->limit($limit + 1);
        StableNameCursor::apply($participantsQuery, $nameExpression, 'people.id', $cursor, nullableName: true);
        $participants = $participantsQuery
            ->get();
        $hasMore = $participants->count() > $limit;
        if ($hasMore) {
            $participants = $participants->slice(0, $limit)->values();
        }
        $last = $participants->last();
        $nextCursor = $hasMore && $last instanceof Person
            ? StableNameCursor::encode($last->cursor_name !== null ? (string) $last->cursor_name : null, (int) $last->id)
            : null;
        $personIds = $participants->pluck('id')->filter()->map(static fn ($id): int => (int) $id)->unique()->all();
        $groupPeople = $this->groupPeople($personIds);
        $groupIds = $groupPeople->pluck('discipleship_group_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->all();
        $modelGroupPeople = $this->mergeUniqueGroupPeople($groupPeople, $this->groupLinksForGroups($groupIds));
        $people = $this->people(collect($personIds)
            ->merge($modelGroupPeople->pluck('person_id')->map(static fn ($id): int => (int) $id))
            ->filter()
            ->unique()
            ->values()
            ->all());
        $groups = $this->groups($groupIds);
        $branches = $this->scope->optionsById();

        $participantRows = $participants->map(static function (Person $participant) use ($branches): array {
            $row = $participant->toViewArray();
            $row['branch_code'] = $branches[(int) $participant->branch_id]['slug'] ?? '';

            return $row;
        })->values()->all();

        return [
            'page' => 'spiritual_journey',
            'people' => array_values($people),
            'peopleById' => $people,
            'mskClasses' => $participantRows,
            'spiritualJourneySearch' => $search,
            'spiritualJourneyFilter' => $journeyFilter,
            'spiritualJourneyFilterCounts' => $this->journeyFilterCounts($search),
            'spiritualJourneyTotalParticipants' => $stats['total'],
            'spiritualJourneyStats' => $stats,
            'spiritualJourneyLimit' => $limit,
            'hasMoreSpiritualJourneyRows' => $hasMore,
            'nextSpiritualJourneyCursor' => $nextCursor,
            'spiritualJourneyEmptyMessage' => $this->emptyMessage($search, $journeyFilter),
            'discipleshipTargets' => $this->targets(),
            'participantHistories' => [],
            'participantProfiles' => [],
            'spiritualJourneyRows' => $this->journeyRows($participantRows, $groupPeople),
            'discipleshipV2Model' => [
                'discipleship_persons' => array_values($people),
                'discipleship_groups' => array_values($groups),
                'group_memberships' => array_values(array_filter($modelGroupPeople->map(fn ($row): array => $this->groupPersonRow($row))->all(), static fn (array $row): bool => $row['role'] === 'member')),
                'group_leaderships' => array_values(array_filter($modelGroupPeople->map(fn ($row): array => $this->groupPersonRow($row))->all(), static fn (array $row): bool => $row['role'] !== 'member')),
            ],
        ];
    }

    /** @return array{total:int,completed_msk:int,following_kgap:int,completed_dg1:int,completed_dg2:int,completed_dg3:int} */
    private function stats(Builder $query): array
    {
        $aggregate = (clone $query)
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$this->sessionCountExpression().' >= 12 THEN 1 ELSE 0 END), 0) AS completed_msk')
            ->selectRaw("COALESCE(SUM(CASE WHEN people.journey_bridge_status IN ('sudah_kgap', 'ikut_keduanya') THEN 1 ELSE 0 END), 0) AS following_kgap")
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$this->completedDgExpression(1).' THEN 1 ELSE 0 END), 0) AS completed_dg1')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$this->completedDgExpression(2).' THEN 1 ELSE 0 END), 0) AS completed_dg2')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$this->completedDgExpression(3).' THEN 1 ELSE 0 END), 0) AS completed_dg3')
            ->toBase()
            ->first();

        return [
            'total' => (int) ($aggregate->total ?? 0),
            'completed_msk' => (int) ($aggregate->completed_msk ?? 0),
            'following_kgap' => (int) ($aggregate->following_kgap ?? 0),
            'completed_dg1' => (int) ($aggregate->completed_dg1 ?? 0),
            'completed_dg2' => (int) ($aggregate->completed_dg2 ?? 0),
            'completed_dg3' => (int) ($aggregate->completed_dg3 ?? 0),
        ];
    }

    private function sessionCountExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "COALESCE(json_array_length(people.session_numbers), 0)"
            : 'COALESCE(JSON_LENGTH(people.session_numbers), 0)';
    }

    private function completedDgExpression(int $stage): string
    {
        $completionReasons = "'continued_to_child_group', 'group_completed', 'stage_transition', 'manual_completion'";
        $membershipCondition = match ($stage) {
            1 => "(completion_gp.stage IN ('DG 2', 'DG 3') OR (completion_gp.stage = 'DG 1' AND completion_gp.end_reason IN ({$completionReasons})))",
            2 => "(completion_gp.stage = 'DG 3' OR (completion_gp.stage = 'DG 2' AND completion_gp.end_reason IN ({$completionReasons})))",
            default => "completion_gp.stage = 'DG 3' AND completion_gp.end_reason IN ({$completionReasons})",
        };
        $manualStages = match ($stage) {
            1 => "'DG 1', 'DG 2', 'DG 3'",
            2 => "'DG 2', 'DG 3'",
            default => "'DG 3'",
        };

        return "(EXISTS (
            SELECT 1 FROM keanggotaan_kelompok_dg AS completion_gp
            WHERE completion_gp.person_id = people.id
              AND completion_gp.branch_id = people.branch_id
              AND {$membershipCondition}
        ) OR EXISTS (
            SELECT 1 FROM dg_manual AS completion_manual
            WHERE completion_manual.person_id = people.id
              AND completion_manual.branch_id = people.branch_id
              AND completion_manual.stage IN ({$manualStages})
        ))";
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

        $query->where(static function (Builder $condition): void {
            $condition->whereNull('people.journey_bridge_status')
                ->orWhereNotIn('people.journey_bridge_status', ['sudah_kgap', 'ikut_keduanya']);
        });
        $query->where(static function (Builder $condition): void {
            $condition->whereExists(static function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('keanggotaan_kelompok_dg as filter_gp')
                    ->whereColumn('filter_gp.person_id', 'people.id')
                    ->whereColumn('filter_gp.branch_id', 'people.branch_id')
                    ->where('filter_gp.role', 'member')
                    ->whereIn('filter_gp.stage', ['DG 1', 'DG 2', 'DG 3']);
            })->orWhereExists(static function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('dg_manual as manual_journey')
                    ->whereColumn('manual_journey.person_id', 'people.id')
                    ->whereColumn('manual_journey.branch_id', 'people.branch_id')
                    ->whereIn('manual_journey.stage', ['DG 1', 'DG 2', 'DG 3']);
            });
        });
    }

    /** @return array<string, int> */
    private function journeyFilterCounts(string $search): array
    {
        $query = Person::query()
            ->from('orang as people')
            ->whereIn('branch_id', $this->scope->branchIds());
        if ($search !== '') {
            $query->where(static function (Builder $builder) use ($search): void {
                $builder->whereRaw('LOWER(full_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(whatsapp) LIKE ?', ['%'.$search.'%']);
            });
        }

        $dgCondition = "COALESCE(people.journey_bridge_status, '') NOT IN ('sudah_kgap', 'ikut_keduanya')
            AND (EXISTS (
                SELECT 1 FROM keanggotaan_kelompok_dg AS filter_gp
                WHERE filter_gp.person_id = people.id
                  AND filter_gp.branch_id = people.branch_id
                  AND filter_gp.role = 'member'
                  AND filter_gp.stage IN ('DG 1', 'DG 2', 'DG 3')
            ) OR EXISTS (
                SELECT 1 FROM dg_manual AS manual_journey
                WHERE manual_journey.person_id = people.id
                  AND manual_journey.branch_id = people.branch_id
                  AND manual_journey.stage IN ('DG 1', 'DG 2', 'DG 3')
            ))";
        $counts = $query
            ->select([])
            ->selectRaw('COUNT(*) AS all_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN {$dgCondition} THEN 1 ELSE 0 END), 0) AS dg_without_kgap_count")
            ->toBase()
            ->first();

        return [
            'all' => (int) ($counts->all_count ?? 0),
            'dg_without_kgap' => (int) ($counts->dg_without_kgap_count ?? 0),
        ];
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

    private function groupLinksForGroups(array $groupIds)
    {
        if ($groupIds === []) {
            return collect();
        }

        return DiscipleshipGroupPerson::query()
            ->whereIn('branch_id', $this->scope->branchIds())
            ->whereIn('discipleship_group_id', $groupIds)
            ->get([
                'id', 'branch_id', 'discipleship_group_id', 'person_id', 'role', 'stage', 'status', 'started_on', 'ended_on', 'end_reason', 'created_at', 'updated_at',
            ]);
    }

    private function mergeUniqueGroupPeople($primary, $additional)
    {
        return $primary
            ->merge($additional)
            ->unique(static fn (DiscipleshipGroupPerson $row): string => (string) ($row->source ?? '').'|'.(string) $row->id)
            ->values();
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

    private function limit(Request $request): int
    {
        return max(1, min(self::MAX_PER_PAGE, (int) $request->query('limit', self::DEFAULT_PER_PAGE)));
    }

    private function emptyMessage(string $search, string $journeyFilter): string
    {
        if ($journeyFilter === 'dg_without_kgap') {
            return 'Belum ada peserta minimal DG 1 yang belum mengikuti Kamp GAP.';
        }

        return $search !== '' ? 'Peserta tidak ditemukan.' : 'Belum ada data peserta MSK.';
    }
}
