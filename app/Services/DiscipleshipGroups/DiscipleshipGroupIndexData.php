<?php

namespace App\Services\DiscipleshipGroups;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipPerson;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DiscipleshipGroupIndexData
{
    public function __construct(private readonly CurrentDiscipleshipScope $scope) {}

    /** @return array<string, mixed> */
    public function forCurrentContext(Request $request): array
    {
        $search = strtolower(trim((string) $request->query('q', '')));
        $status = trim((string) $request->query('status', 'all'));
        $base = DiscipleshipGroup::query()
            ->whereIn('branch_id', $this->scope->branchIds())
            ->whereExists(static function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('discipleship_group_people as existing_gp')
                    ->whereColumn('existing_gp.discipleship_group_id', 'discipleship_groups.id');
            });
        $stats = (clone $base)
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN current_stage = 'DG 1' THEN 1 ELSE 0 END) AS dg1,
                SUM(CASE WHEN current_stage = 'DG 2' THEN 1 ELSE 0 END) AS dg2,
                SUM(CASE WHEN current_stage = 'DG 3' THEN 1 ELSE 0 END) AS dg3")
            ->first();

        $query = (clone $base)->select([
            'id', 'branch_id', 'name', 'status', 'start_stage', 'current_stage', 'created_at',
        ]);
        if ($status === 'active') {
            $query->where('status', $status);
        } elseif ($status === 'inactive') {
            $query->where('status', '!=', 'active');
        } else {
            $status = 'all';
        }
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereExists(function ($subquery) use ($search): void {
                        $subquery->selectRaw('1')
                            ->from('discipleship_group_people as search_gp')
                            ->join('discipleship_people as search_person', 'search_person.id', '=', 'search_gp.person_id')
                            ->whereColumn('search_gp.discipleship_group_id', 'discipleship_groups.id')
                            ->whereRaw('LOWER(search_person.full_name) LIKE ?', ['%'.$search.'%']);
                    });
            });
        }

        $groups = $query->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
        $groupIds = $groups->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $links = $this->groupPeople($groupIds);
        $people = $this->people($links->pluck('person_id')->filter()->unique()->all());
        $branchOptions = $this->scope->optionsById();

        $rows = $groups->map(function (DiscipleshipGroup $group) use ($links, $people, $branchOptions): array {
            $groupLinks = $links->where('discipleship_group_id', $group->id);
            $isActiveGroup = strtolower((string) $group->status) === 'active';
            $activeLinks = $groupLinks
                ->filter(static fn (DiscipleshipGroupPerson $link): bool => strtolower((string) $link->status) === 'active' && $link->ended_on === null);
            $displayLinks = $isActiveGroup ? $activeLinks : $groupLinks;
            $leaders = $displayLinks->where('role', '!=', 'member')->sortByDesc('id');
            if ($isActiveGroup && $leaders->isEmpty()) {
                $leaders = $groupLinks->where('role', '!=', 'member')->sortByDesc('id');
            }
            $primary = $leaders->first(static fn (DiscipleshipGroupPerson $link): bool => ! in_array($link->role, ['co_leader', 'assistant', 'pendamping'], true));
            $primary ??= $leaders->first();
            $leaderName = $primary !== null ? ($people[(int) $primary->person_id] ?? '') : '';
            if ($leaderName === '') {
                $leaderName = $isActiveGroup ? 'Tanpa pemimpin' : 'Tanpa riwayat pemimpin';
            }
            $assistants = $leaders
                ->reject(static fn (DiscipleshipGroupPerson $link): bool => $primary !== null && $link->id === $primary->id)
                ->map(static fn (DiscipleshipGroupPerson $link): string => $people[(int) $link->person_id] ?? '')
                ->filter()->unique()->values()->all();
            $members = $displayLinks->where('role', 'member')
                ->map(static fn (DiscipleshipGroupPerson $link): string => $people[(int) $link->person_id] ?? '')
                ->filter()->unique()->values()->all();
            $progress = normalize_dg_progress_value((string) ($group->current_stage ?: $group->start_stage)) ?: '-';
            $branchLabel = $branchOptions[(int) $group->branch_id]['label'] ?? 'Tanpa cabang';
            if ($this->scope->includesAllBranches()) {
                $leaderName = append_branch_suffix($leaderName, $branchLabel);
            }

            return [
                'id' => (int) $group->id,
                'row_status' => strtolower((string) $group->status) === 'active' ? 'active' : 'inactive',
                'row_progress' => strtolower(str_replace(' ', '', $progress)),
                'row_class' => strtolower((string) $group->status) === 'active' ? '' : 'is-inactive',
                'leader_name' => $leaderName,
                'leader_summary' => $assistants !== []
                    ? ($isActiveGroup ? 'Pendamping: ' : 'Riwayat pendamping: ').implode(', ', $assistants)
                    : ($isActiveGroup ? 'Tanpa pendamping' : 'Tanpa riwayat pendamping'),
                'group_status_class' => strtolower((string) $group->status) === 'active' ? 'is-active' : 'is-inactive',
                'progress_tone_class' => match ($progress) {
                    'DG 1' => 'is-dg1', 'DG 2' => 'is-dg2', 'DG 3' => 'is-dg3', default => 'is-neutral',
                },
                'progress_label' => $progress,
                'progress_helper_text' => trim((string) $group->name).($this->scope->includesAllBranches() ? ' - '.$branchLabel : ''),
                'member_summary' => $members !== [] ? implode(', ', array_slice($members, 0, 8)) : ($isActiveGroup ? 'Belum ada peserta' : 'Tanpa riwayat peserta'),
                'member_helper_text' => count($members).($isActiveGroup ? ' peserta aktif' : ' peserta tercatat'),
                'member_count' => count($members),
            ];
        })->values();
        return [
            'settings' => ['church_name' => app_church_name()],
            'groups' => $rows->all(),
            'filteredGroupRows' => $rows->count(),
            'totalGroupRows' => (int) ($stats->total ?? 0),
            'groupsInDg1Count' => (int) ($stats->dg1 ?? 0),
            'groupsInDg2Count' => (int) ($stats->dg2 ?? 0),
            'groupsInDg3Count' => (int) ($stats->dg3 ?? 0),
            'groupsSearch' => $search,
            'groupsStatusFilter' => $status,
        ];
    }

    private function groupPeople(array $groupIds)
    {
        if ($groupIds === []) {
            return collect();
        }

        return DiscipleshipGroupPerson::query()
            ->whereIn('discipleship_group_id', $groupIds)
            ->get(['id', 'discipleship_group_id', 'person_id', 'role', 'status', 'ended_on']);
    }

    private function people(array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }

        return DiscipleshipPerson::query()
            ->whereIn('id', $personIds)
            ->pluck('full_name', 'id')
            ->map(static fn ($name): string => trim((string) $name))
            ->all();
    }
}
