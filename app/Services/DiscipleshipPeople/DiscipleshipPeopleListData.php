<?php

namespace App\Services\DiscipleshipPeople;

use App\Models\DiscipleshipGroupPerson;
use App\Models\Person;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Support\DiscipleshipPersonProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiscipleshipPeopleListData
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly CurrentDiscipleshipScope $scope) {}

    /** @return array<string, mixed> */
    public function forCurrentContext(Request $request): array
    {
        $pageData = $this->paginatedRowsForCurrentContext($request);

        return [
            'settings' => ['church_name' => app_church_name()],
            ...$pageData,
        ];
    }

    /** @return array<string, mixed> */
    public function paginatedRowsForCurrentContext(Request $request): array
    {
        $search = $this->search($request);
        $progress = $this->progressFilter($request);
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $query = $this->filteredPeopleQuery($search, $progress)
            ->select([
                'people.id',
                'people.branch_id',
                'people.status',
                DB::raw(DiscipleshipPersonProfile::expression('full_name').' as full_name'),
            ])
            ->orderByRaw(DiscipleshipPersonProfile::expression('full_name'))
            ->orderBy('people.id');

        $people = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();
        $hasMore = $people->count() > $perPage;
        if ($hasMore) {
            $people = $people->slice(0, $perPage)->values();
        }

        $stats = $this->progressStats($search, $progress);

        return [
            'people' => $this->rows($people)->all(),
            'filteredPeopleRows' => $stats['total'],
            'totalPeopleRows' => $stats['total'],
            'peopleInDg1Count' => $stats['dg1'],
            'peopleInDg2Count' => $stats['dg2'],
            'peopleInDg3Count' => $stats['dg3'],
            'peopleProgressFilterCounts' => $this->progressFilterCounts($search),
            'peopleSearch' => $search,
            'peopleProgressFilter' => $progress,
            'peoplePage' => $page,
            'peoplePerPage' => $perPage,
            'hasMorePeopleRows' => $hasMore,
            'nextPeoplePage' => $hasMore ? $page + 1 : null,
            'peopleEmptyMessage' => $this->emptyMessage($search, $progress),
        ];
    }

    /** @return array<string, mixed> */
    public function allRowsForCurrentContext(Request $request): array
    {
        $search = $this->search($request);
        $progress = $this->progressFilter($request);
        $people = $this->filteredPeopleQuery($search, $progress)
            ->select([
                'people.id',
                'people.branch_id',
                'people.status',
                DB::raw(DiscipleshipPersonProfile::expression('full_name').' as full_name'),
            ])
            ->orderByRaw(DiscipleshipPersonProfile::expression('full_name'))
            ->orderBy('people.id')
            ->get();
        $stats = $this->progressStats($search, $progress);

        return [
            'people' => $this->rows($people)->all(),
            'filteredPeopleRows' => $stats['total'],
            'totalPeopleRows' => $stats['total'],
            'peopleInDg1Count' => $stats['dg1'],
            'peopleInDg2Count' => $stats['dg2'],
            'peopleInDg3Count' => $stats['dg3'],
            'peopleProgressFilterCounts' => $this->progressFilterCounts($search),
            'peopleSearch' => $search,
            'peopleProgressFilter' => $progress,
        ];
    }

    private function filteredPeopleQuery(string $search, string $progress): Builder
    {
        $query = Person::query()->from('orang as people');
        DiscipleshipPersonProfile::join($query);

        $query
            ->whereIn('people.branch_id', $this->scope->branchIds())
            ->where('people.status', 'active')
            ->where(function (Builder $condition): void {
                $condition->whereExists(static function ($subquery): void {
                    $subquery->selectRaw('1')
                        ->from('keanggotaan_kelompok_dg as participant_history')
                        ->whereColumn('participant_history.person_id', 'people.id')
                        ->whereColumn('participant_history.branch_id', 'people.branch_id')
                        ->where('participant_history.role', 'member');
                });
                if (Schema::hasTable('dg_manual')) {
                    $condition->orWhereExists(static function ($subquery): void {
                        $subquery->selectRaw('1')
                            ->from('dg_manual as manual_journey')
                            ->whereColumn('manual_journey.person_id', 'people.id')
                            ->whereColumn('manual_journey.branch_id', 'people.branch_id');
                    });
                }
            });

        if ($search !== '') {
            $query->whereRaw('LOWER('.DiscipleshipPersonProfile::expression('full_name').') LIKE ?', ['%'.$search.'%']);
        }

        $this->applyProgressFilter($query, $progress);

        return $query;
    }

    private function applyProgressFilter(Builder $query, string $progress): void
    {
        $activeStages = ['active_dg1' => 'DG 1', 'active_dg2' => 'DG 2', 'active_dg3' => 'DG 3'];
        if (isset($activeStages[$progress])) {
            $stage = $activeStages[$progress];
            $query->whereExists(static function ($subquery) use ($stage): void {
                $subquery->selectRaw('1')->from('keanggotaan_kelompok_dg as filter_gp')
                    ->whereColumn('filter_gp.person_id', 'people.id')
                    ->whereColumn('filter_gp.branch_id', 'people.branch_id')
                    ->where('filter_gp.role', 'member')->where('filter_gp.stage', $stage)
                    ->where('filter_gp.status', 'active')->whereNull('filter_gp.ended_on');
            });

            return;
        }

        $completedStages = ['complete_dg1' => 1, 'complete_dg2' => 2, 'complete_dg3' => 3];
        if (isset($completedStages[$progress])) {
            $rank = $completedStages[$progress];
            $query->where(static function (Builder $completion) use ($rank): void {
                $completion->whereExists(static function ($subquery) use ($rank): void {
                    $subquery->selectRaw('1')->from('keanggotaan_kelompok_dg as filter_gp')
                        ->whereColumn('filter_gp.person_id', 'people.id')
                        ->whereColumn('filter_gp.branch_id', 'people.branch_id')
                        ->where('filter_gp.role', 'member')
                        ->where(function ($condition) use ($rank): void {
                            if ($rank === 1) {
                                $condition->whereIn('filter_gp.stage', ['DG 2', 'DG 3'])
                                    ->orWhere(fn ($q) => $q->where('filter_gp.stage', 'DG 1')->whereIn('filter_gp.end_reason', ['continued_to_child_group', 'group_completed', 'stage_transition']));
                            } elseif ($rank === 2) {
                                $condition->where('filter_gp.stage', 'DG 3')
                                    ->orWhere(fn ($q) => $q->where('filter_gp.stage', 'DG 2')->whereIn('filter_gp.end_reason', ['continued_to_child_group', 'group_completed', 'stage_transition']));
                            } else {
                                $condition->where('filter_gp.stage', 'DG 3')->whereIn('filter_gp.end_reason', ['continued_to_child_group', 'group_completed', 'stage_transition']);
                            }
                        });
                });
                if (Schema::hasTable('dg_manual')) {
                    $stages = $rank === 1 ? ['DG 1', 'DG 2', 'DG 3'] : ($rank === 2 ? ['DG 2', 'DG 3'] : ['DG 3']);
                    $completion->orWhereExists(static function ($subquery) use ($stages): void {
                        $subquery->selectRaw('1')->from('dg_manual as manual_journey')
                            ->whereColumn('manual_journey.person_id', 'people.id')
                            ->whereColumn('manual_journey.branch_id', 'people.branch_id')
                            ->whereIn('manual_journey.stage', $stages);
                    });
                }
            });
        }
    }

    /**
     * @param Collection<int, Person> $people
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Collection $people): Collection
    {
        $personIds = $people->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $groupPeople = $this->groupPeople($personIds);
        $groupIds = $groupPeople->pluck('discipleship_group_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->all();
        $allGroupLinks = $this->groupLinks($groupIds);
        $relatedIds = $groupPeople->pluck('person_id')
            ->merge($allGroupLinks->pluck('person_id'))
            ->filter()
            ->unique()
            ->all();
        $names = DiscipleshipPersonProfile::namesByPersonIds($relatedIds);
        $branchOptions = $this->scope->optionsById();

        return $people->map(function (Person $person) use ($groupPeople, $allGroupLinks, $names, $branchOptions): array {
            $personId = (int) $person->id;
            $links = $groupPeople->where('person_id', $personId);
            $isLeader = $links->contains(static fn (DiscipleshipGroupPerson $row): bool => $row->role !== 'member' && $row->status === 'active' && $row->ended_on === null);
            $activeGroups = $this->activeGroupSummaries($links, $allGroupLinks, $names);
            $progress = $this->progress($links);
            $tokens = $progress['filters'];
            $lastStage = $this->lastStage($links);
            $branchLabel = $branchOptions[(int) $person->branch_id]['label'] ?? 'Tanpa cabang';
            $exportName = trim((string) $person->full_name) ?: '-';
            $name = $exportName;
            if ($this->scope->includesAllBranches()) {
                $name = append_branch_suffix($name, $branchLabel);
            }

            return [
                'id' => $personId,
                'row_filter_state' => implode(' ', $tokens) ?: 'none',
                'row_progress_key' => $lastStage !== '' ? strtolower(str_replace(' ', '', $lastStage)) : 'none',
                'name' => $name,
                'export_name' => $exportName,
                'branch_label' => $branchLabel,
                'parent_summary' => $activeGroups !== [] ? 'Kelompok aktif: '.implode(', ', $activeGroups) : 'Belum ada kelompok aktif',
                'role_label' => $isLeader ? 'Pemimpin' : 'Anggota',
                'role_tone_class' => $isLeader ? 'is-leader' : 'is-member',
                'role_subtitle' => $isLeader ? 'Memimpin kelompok DG' : ($activeGroups !== [] ? 'Anggota kelompok DG' : 'Belum ada kelompok aktif'),
                'progress_steps' => $progress['steps'],
                'progress_summary' => $progress['summary'],
            ];
        })->values();
    }

    private function groupLinks(array $groupIds): Collection
    {
        if ($groupIds === []) {
            return collect();
        }

        return DiscipleshipGroupPerson::query()
            ->whereIn('branch_id', $this->scope->branchIds())
            ->whereIn('discipleship_group_id', $groupIds)
            ->get(['id', 'discipleship_group_id', 'person_id', 'role', 'stage', 'status', 'ended_on', 'end_reason', 'started_on', 'updated_at']);
    }

    private function groupPeople(array $personIds)
    {
        if ($personIds === []) {
            return collect();
        }

        $rows = DiscipleshipGroupPerson::query()
            ->whereIn('branch_id', $this->scope->branchIds())
            ->whereIn('person_id', $personIds)
            ->get(['id', 'discipleship_group_id', 'person_id', 'role', 'stage', 'status', 'ended_on', 'end_reason', 'started_on']);

        return $rows->merge($this->manualGroupPeople($personIds));
    }

    private function activeGroupSummaries(Collection $links, Collection $allGroupLinks, array $names): array
    {
        return $links
            ->filter(static fn (DiscipleshipGroupPerson $row): bool => $row->role === 'member' && $row->status === 'active' && $row->ended_on === null)
            ->map(function (DiscipleshipGroupPerson $link) use ($allGroupLinks, $names): string {
                $groupId = (int) $link->discipleship_group_id;
                $leaders = $allGroupLinks
                    ->where('discipleship_group_id', $groupId)
                    ->filter(static fn (DiscipleshipGroupPerson $row): bool => $row->role !== 'member' && $row->status === 'active' && $row->ended_on === null)
                    ->map(static fn (DiscipleshipGroupPerson $row): string => trim((string) ($names[(int) $row->person_id] ?? '')))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return discipleship_group_display_label([
                    'progress' => normalize_dg_progress_value((string) $link->stage),
                    'leader_name' => implode(', ', $leaders),
                ]);
            })
            ->filter(static fn (string $label): bool => $label !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     filters: array<int, string>,
     *     steps: array<int, array{label:string,state:string,state_label:string}>,
     *     summary: string
     * }
     */
    private function progress($links): array
    {
        $filters = [];
        $steps = [];
        $currentStage = '';
        $highestCompletedStage = '';
        foreach ([1 => 'DG 1', 2 => 'DG 2', 3 => 'DG 3'] as $rank => $stage) {
            $active = $links->contains(static fn (DiscipleshipGroupPerson $row): bool => $row->role === 'member' && $row->stage === $stage && $row->status === 'active' && $row->ended_on === null);
            $completed = $this->completedStage($links, $rank);
            $recorded = $links->contains(static fn (DiscipleshipGroupPerson $row): bool => $row->role === 'member' && $row->stage === $stage);

            $state = 'is-pending';
            $stateLabel = 'Belum';
            if ($completed) {
                $state = 'is-complete';
                $stateLabel = 'Selesai';
                $highestCompletedStage = $stage;
                $filters[] = 'complete_dg'.$rank;
            }
            if ($active) {
                $state = 'is-current';
                $stateLabel = 'Sedang';
                $currentStage = $stage;
                $filters[] = 'active_dg'.$rank;
            } elseif (! $completed && $recorded) {
                $state = 'is-stopped';
                $stateLabel = 'Terhenti';
            }

            $steps[] = [
                'label' => $stage,
                'state' => $state,
                'state_label' => $stateLabel,
            ];
        }

        $summary = 'Belum memulai DG';
        if ($currentStage !== '') {
            $summary = 'Sedang menjalani '.$currentStage;
        } elseif ($highestCompletedStage !== '') {
            $summary = 'Terakhir menyelesaikan '.$highestCompletedStage;
        } elseif ($links->contains(static fn (DiscipleshipGroupPerson $row): bool => $row->role === 'member')) {
            $summary = 'Progres DG terhenti';
        }

        return [
            'filters' => array_values(array_unique($filters)),
            'steps' => $steps,
            'summary' => $summary,
        ];
    }

    private function completedStage($links, int $rank): bool
    {
        $reasons = ['continued_to_child_group', 'group_completed', 'stage_transition', 'manual_completion'];

        return $links->contains(static function (DiscipleshipGroupPerson $row) use ($rank, $reasons): bool {
            $stageRank = match ($row->stage) {
                'DG 1' => 1, 'DG 2' => 2, 'DG 3' => 3, default => 0
            };
            if ($stageRank > $rank) {
                return true;
            }

            return $stageRank === $rank && in_array($row->end_reason, $reasons, true);
        });
    }

    private function lastStage($links): string
    {
        foreach (['DG 3', 'DG 2', 'DG 1'] as $stage) {
            if ($links->contains(static fn (DiscipleshipGroupPerson $row): bool => $row->stage === $stage)) {
                return $stage;
            }
        }

        return '';
    }

    /** @return array{total:int,dg1:int,dg2:int,dg3:int} */
    private function progressStats(string $search, string $progress): array
    {
        $people = $this->filteredPeopleQuery($search, $progress)
            ->select(['people.id', 'people.branch_id']);

        $journeyRows = DB::query()
            ->fromSub((clone $people), 'p')
            ->join('keanggotaan_kelompok_dg as gp', function ($join): void {
                $join->on('gp.person_id', '=', 'p.id')
                    ->on('gp.branch_id', '=', 'p.branch_id');
            })
            ->where('gp.role', 'member')
            ->selectRaw("gp.person_id,
                CASE gp.stage
                    WHEN 'DG 3' THEN 3
                    WHEN 'DG 2' THEN 2
                    WHEN 'DG 1' THEN 1
                    ELSE 0
                END AS stage_rank");

        if (Schema::hasTable('dg_manual')) {
            $manualRows = DB::query()
                ->fromSub((clone $people), 'p')
                ->join('dg_manual as manual_journey', function ($join): void {
                    $join->on('manual_journey.person_id', '=', 'p.id')
                        ->on('manual_journey.branch_id', '=', 'p.branch_id');
                })
                ->selectRaw("manual_journey.person_id,
                    CASE manual_journey.stage
                        WHEN 'DG 3' THEN 3
                        WHEN 'DG 2' THEN 2
                        WHEN 'DG 1' THEN 1
                        ELSE 0
                    END AS stage_rank");
            $journeyRows->unionAll($manualRows);
        }

        $participantStages = DB::query()
            ->fromSub($journeyRows, 'journey_rows')
            ->selectRaw('person_id, MAX(stage_rank) AS last_stage_rank')
            ->groupBy('person_id');

        $row = DB::query()
            ->fromSub($participantStages, 'participant_stages')
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN last_stage_rank = 1 THEN 1 ELSE 0 END) AS dg1,
                SUM(CASE WHEN last_stage_rank = 2 THEN 1 ELSE 0 END) AS dg2,
                SUM(CASE WHEN last_stage_rank = 3 THEN 1 ELSE 0 END) AS dg3")
            ->first();

        return ['total' => (int) ($row->total ?? 0), 'dg1' => (int) ($row->dg1 ?? 0), 'dg2' => (int) ($row->dg2 ?? 0), 'dg3' => (int) ($row->dg3 ?? 0)];
    }

    /** @return array<string, int> */
    private function progressFilterCounts(string $search): array
    {
        $people = $this->filteredPeopleQuery($search, 'all')
            ->select(['people.id', 'people.branch_id']);

        $row = DB::query()
            ->fromSub($people, 'filtered_people')
            ->selectRaw("COUNT(*) AS all_count,
                SUM(CASE WHEN ".$this->activeStageExistsSql('DG 1')." THEN 1 ELSE 0 END) AS active_dg1,
                SUM(CASE WHEN ".$this->completedStageExistsSql(1)." THEN 1 ELSE 0 END) AS complete_dg1,
                SUM(CASE WHEN ".$this->activeStageExistsSql('DG 2')." THEN 1 ELSE 0 END) AS active_dg2,
                SUM(CASE WHEN ".$this->completedStageExistsSql(2)." THEN 1 ELSE 0 END) AS complete_dg2,
                SUM(CASE WHEN ".$this->activeStageExistsSql('DG 3')." THEN 1 ELSE 0 END) AS active_dg3,
                SUM(CASE WHEN ".$this->completedStageExistsSql(3)." THEN 1 ELSE 0 END) AS complete_dg3")
            ->first();

        return [
            'all' => (int) ($row->all_count ?? 0),
            'active_dg1' => (int) ($row->active_dg1 ?? 0),
            'complete_dg1' => (int) ($row->complete_dg1 ?? 0),
            'active_dg2' => (int) ($row->active_dg2 ?? 0),
            'complete_dg2' => (int) ($row->complete_dg2 ?? 0),
            'active_dg3' => (int) ($row->active_dg3 ?? 0),
            'complete_dg3' => (int) ($row->complete_dg3 ?? 0),
        ];
    }

    private function activeStageExistsSql(string $stage): string
    {
        $stage = str_replace("'", "''", $stage);

        return "EXISTS (
            SELECT 1
            FROM keanggotaan_kelompok_dg AS filter_count_gp
            WHERE filter_count_gp.person_id = filtered_people.id
              AND filter_count_gp.branch_id = filtered_people.branch_id
              AND filter_count_gp.role = 'member'
              AND filter_count_gp.stage = '{$stage}'
              AND filter_count_gp.status = 'active'
              AND filter_count_gp.ended_on IS NULL
        )";
    }

    private function completedStageExistsSql(int $rank): string
    {
        $reasons = "'continued_to_child_group', 'group_completed', 'stage_transition'";
        $groupCondition = match ($rank) {
            1 => "filter_count_gp.stage IN ('DG 2', 'DG 3') OR (filter_count_gp.stage = 'DG 1' AND filter_count_gp.end_reason IN ({$reasons}))",
            2 => "filter_count_gp.stage = 'DG 3' OR (filter_count_gp.stage = 'DG 2' AND filter_count_gp.end_reason IN ({$reasons}))",
            default => "filter_count_gp.stage = 'DG 3' AND filter_count_gp.end_reason IN ({$reasons})",
        };

        $sql = "EXISTS (
            SELECT 1
            FROM keanggotaan_kelompok_dg AS filter_count_gp
            WHERE filter_count_gp.person_id = filtered_people.id
              AND filter_count_gp.branch_id = filtered_people.branch_id
              AND filter_count_gp.role = 'member'
              AND ({$groupCondition})
        )";

        if (Schema::hasTable('dg_manual')) {
            $manualStages = $rank === 1 ? "'DG 1', 'DG 2', 'DG 3'" : ($rank === 2 ? "'DG 2', 'DG 3'" : "'DG 3'");
            $sql .= " OR EXISTS (
                SELECT 1
                FROM dg_manual AS manual_count_journey
                WHERE manual_count_journey.person_id = filtered_people.id
                  AND manual_count_journey.branch_id = filtered_people.branch_id
                  AND manual_count_journey.stage IN ({$manualStages})
            )";
        }

        return '('.$sql.')';
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
                    'person_id' => (int) $row->person_id,
                    'role' => 'member',
                    'stage' => normalize_dg_progress_value((string) $row->stage),
                    'status' => 'completed',
                    'started_on' => $row->completed_on,
                    'ended_on' => $row->completed_on,
                    'end_reason' => 'manual_completion',
                ]);

                return $model;
            });
    }

    private function search(Request $request): string
    {
        return $this->lower(trim((string) $request->query('q', '')));
    }

    private function progressFilter(Request $request): string
    {
        $progress = trim((string) $request->query('progress', 'all'));
        $allowed = ['all', 'active_dg1', 'complete_dg1', 'active_dg2', 'complete_dg2', 'active_dg3', 'complete_dg3'];

        return in_array($progress, $allowed, true) ? $progress : 'all';
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function perPage(Request $request): int
    {
        return max(1, min(self::MAX_PER_PAGE, (int) $request->query('per_page', self::DEFAULT_PER_PAGE)));
    }

    private function emptyMessage(string $search, string $progress): string
    {
        return $search !== '' || $progress !== 'all'
            ? 'Peserta tidak ditemukan.'
            : 'Belum ada data orang.';
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
