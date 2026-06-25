<?php

namespace App\Services\DiscipleshipPeople;

use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipPerson;
use App\Models\DiscipleshipRelationship;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DiscipleshipPeopleListData
{
    public function __construct(private readonly CurrentDiscipleshipScope $scope) {}

    /** @return array<string, mixed> */
    public function forCurrentContext(Request $request): array
    {
        $search = strtolower(trim((string) $request->query('q', '')));
        $progress = trim((string) $request->query('progress', 'all'));
        $base = DiscipleshipPerson::query()
            ->whereIn('branch_id', $this->scope->branchIds())
            ->where('status', 'active')
            ->whereExists(static function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('discipleship_group_people as participant_history')
                    ->whereColumn('participant_history.person_id', 'discipleship_people.id')
                    ->whereColumn('participant_history.branch_id', 'discipleship_people.branch_id')
                    ->where('participant_history.role', 'member');
            });
        $query = (clone $base)->select(['id', 'branch_id', 'full_name', 'status']);

        $this->applyProgressFilter($query, $progress);

        $people = $query->orderBy('full_name')->get();
        $personIds = $people->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $relationships = $this->relationships($personIds);
        $groupPeople = $this->groupPeople($personIds);
        $relatedIds = $relationships->flatMap(static fn (DiscipleshipRelationship $row): array => [(int) $row->mentor_person_id, (int) $row->disciple_person_id])
            ->merge($groupPeople->pluck('person_id'))->filter()->unique()->all();
        $names = DiscipleshipPerson::query()->whereIn('id', $relatedIds)->pluck('full_name', 'id')->all();
        $branchOptions = $this->scope->optionsById();

        $rows = $people->map(function (DiscipleshipPerson $person) use ($relationships, $groupPeople, $names, $branchOptions): array {
            $personId = (int) $person->id;
            $parents = $relationships->where('disciple_person_id', $personId)
                ->where('status', 'active')
                ->map(static fn (DiscipleshipRelationship $row): string => trim((string) ($names[(int) $row->mentor_person_id] ?? '')))
                ->filter()->unique()->values()->all();
            $hasChildren = $relationships->where('mentor_person_id', $personId)
                ->where('status', 'active')
                ->isNotEmpty();
            $links = $groupPeople->where('person_id', $personId);
            $isLeader = $links->contains(static fn (DiscipleshipGroupPerson $row): bool => $row->role !== 'member' && $row->status === 'active' && $row->ended_on === null);
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
                'parent_summary' => $parents !== [] ? 'Dibina oleh '.implode(', ', $parents) : 'Belum terhubung ke pembina',
                'role_label' => $isLeader ? 'Pemimpin' : ($hasChildren ? 'Pembina' : 'Anggota'),
                'role_tone_class' => $isLeader ? 'is-leader' : ($hasChildren ? 'is-mentor' : 'is-member'),
                'role_subtitle' => $hasChildren ? 'Memiliki binaan langsung' : 'Belum memiliki binaan langsung',
                'progress_steps' => $progress['steps'],
                'progress_summary' => $progress['summary'],
            ];
        })->values();
        $stats = $this->progressStats();

        return [
            'settings' => ['church_name' => app_church_name()],
            'people' => $rows->all(),
            'filteredPeopleRows' => $rows->count(),
            'totalPeopleRows' => $stats['total'],
            'peopleInDg1Count' => $stats['dg1'],
            'peopleInDg2Count' => $stats['dg2'],
            'peopleInDg3Count' => $stats['dg3'],
            'peopleSearch' => $search,
            'peopleProgressFilter' => $progress,
        ];
    }

    private function applyProgressFilter(Builder $query, string &$progress): void
    {
        $activeStages = ['active_dg1' => 'DG 1', 'active_dg2' => 'DG 2', 'active_dg3' => 'DG 3'];
        if (isset($activeStages[$progress])) {
            $stage = $activeStages[$progress];
            $query->whereExists(static function ($subquery) use ($stage): void {
                $subquery->selectRaw('1')->from('discipleship_group_people as filter_gp')
                    ->whereColumn('filter_gp.person_id', 'discipleship_people.id')
                    ->where('filter_gp.role', 'member')->where('filter_gp.stage', $stage)
                    ->where('filter_gp.status', 'active')->whereNull('filter_gp.ended_on');
            });

            return;
        }

        $completedStages = ['complete_dg1' => 1, 'complete_dg2' => 2, 'complete_dg3' => 3];
        if (isset($completedStages[$progress])) {
            $rank = $completedStages[$progress];
            $query->whereExists(static function ($subquery) use ($rank): void {
                $subquery->selectRaw('1')->from('discipleship_group_people as filter_gp')
                    ->whereColumn('filter_gp.person_id', 'discipleship_people.id')->where('filter_gp.role', 'member')
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

            return;
        }

        $progress = 'all';
    }

    private function relationships(array $personIds)
    {
        if ($personIds === []) {
            return collect();
        }

        return DiscipleshipRelationship::query()
            ->whereIn('branch_id', $this->scope->branchIds())
            ->where(function (Builder $query) use ($personIds): void {
                $query->whereIn('mentor_person_id', $personIds)->orWhereIn('disciple_person_id', $personIds);
            })
            ->get(['id', 'mentor_person_id', 'disciple_person_id', 'status']);
    }

    private function groupPeople(array $personIds)
    {
        if ($personIds === []) {
            return collect();
        }

        return DiscipleshipGroupPerson::query()->whereIn('person_id', $personIds)
            ->get(['id', 'person_id', 'role', 'stage', 'status', 'ended_on', 'end_reason', 'started_on']);
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
        $reasons = ['continued_to_child_group', 'group_completed', 'stage_transition'];

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
    private function progressStats(): array
    {
        $participantStages = DiscipleshipGroupPerson::query()
            ->from('discipleship_group_people as gp')
            ->join('discipleship_people as p', function ($join): void {
                $join->on('p.id', '=', 'gp.person_id')
                    ->on('p.branch_id', '=', 'gp.branch_id');
            })
            ->whereIn('gp.branch_id', $this->scope->branchIds())
            ->where('p.status', 'active')
            ->where('gp.role', 'member')
            ->selectRaw("gp.person_id,
                MAX(CASE gp.stage
                    WHEN 'DG 3' THEN 3
                    WHEN 'DG 2' THEN 2
                    WHEN 'DG 1' THEN 1
                    ELSE 0
                END) AS last_stage_rank")
            ->groupBy('gp.person_id');

        $row = DiscipleshipGroupPerson::query()
            ->fromSub($participantStages, 'participant_stages')
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN last_stage_rank = 1 THEN 1 ELSE 0 END) AS dg1,
                SUM(CASE WHEN last_stage_rank = 2 THEN 1 ELSE 0 END) AS dg2,
                SUM(CASE WHEN last_stage_rank = 3 THEN 1 ELSE 0 END) AS dg3")
            ->first();

        return ['total' => (int) ($row->total ?? 0), 'dg1' => (int) ($row->dg1 ?? 0), 'dg2' => (int) ($row->dg2 ?? 0), 'dg3' => (int) ($row->dg3 ?? 0)];
    }
}
