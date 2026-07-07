<?php

namespace App\Services\DiscipleshipGroups;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\Person;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Support\DiscipleshipPersonProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DiscipleshipGroupIndexData
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly CurrentDiscipleshipScope $scope) {}

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
        $search = $this->search($request);
        $status = $this->statusFilter($request);
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $query = $this->filteredGroupQuery($search, $status)
            ->select(['id', 'branch_id', 'status', 'stage', 'created_at'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('stage')
            ->orderBy('id');

        $groups = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();
        $hasMore = $groups->count() > $perPage;
        if ($hasMore) {
            $groups = $groups->slice(0, $perPage)->values();
        }
        $stats = $this->stats($search, $status);

        return [
            'groups' => $this->rows($groups)->all(),
            'filteredGroupRows' => $stats['total'],
            'totalGroupRows' => $stats['total'],
            'groupsInDg1Count' => $stats['dg1'],
            'groupsInDg2Count' => $stats['dg2'],
            'groupsInDg3Count' => $stats['dg3'],
            'groupsSearch' => $search,
            'groupsStatusFilter' => $status,
            'groupsPage' => $page,
            'groupsPerPage' => $perPage,
            'hasMoreGroupRows' => $hasMore,
            'nextGroupPage' => $hasMore ? $page + 1 : null,
            'groupsEmptyMessage' => $this->emptyMessage($search, $status),
        ];
    }

    private function filteredGroupQuery(string $search, string $status): Builder
    {
        $query = DiscipleshipGroup::query()
            ->from('kelompok_dg as discipleship_groups')
            ->whereIn('branch_id', $this->scope->branchIds())
            ->whereExists(static function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('keanggotaan_kelompok_dg as existing_gp')
                    ->whereColumn('existing_gp.discipleship_group_id', 'discipleship_groups.id')
                    ->whereColumn('existing_gp.branch_id', 'discipleship_groups.branch_id');
            });

        if ($status === 'active') {
            $query->where('status', $status);
        } elseif ($status === 'inactive') {
            $query->where('status', '!=', 'active');
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereRaw('LOWER(stage) LIKE ?', ['%'.$search.'%'])
                    ->orWhereExists(function ($subquery) use ($search): void {
                        $subquery->selectRaw('1')
                            ->from('keanggotaan_kelompok_dg as search_gp')
                            ->join('orang as search_person', 'search_person.id', '=', 'search_gp.person_id');
                        DiscipleshipPersonProfile::join($subquery, 'search_person', 'search_profile');
                        $subquery
                            ->whereColumn('search_gp.discipleship_group_id', 'discipleship_groups.id')
                            ->whereColumn('search_gp.branch_id', 'discipleship_groups.branch_id')
                            ->whereRaw('LOWER('.DiscipleshipPersonProfile::expression('full_name', 'search_person', 'search_profile').') LIKE ?', ['%'.$search.'%']);
                    });
            });
        }

        return $query;
    }

    /**
     * @param Collection<int, DiscipleshipGroup> $groups
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Collection $groups): Collection
    {
        $groupIds = $groups->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $links = $this->groupPeople($groupIds);
        $people = $this->people($links->pluck('person_id')->filter()->unique()->all());
        $branchOptions = $this->scope->optionsById();

        return $groups->map(function (DiscipleshipGroup $group) use ($links, $people, $branchOptions): array {
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
            $progress = discipleship_group_stage_value($group) ?: '-';
            $branchLabel = $branchOptions[(int) $group->branch_id]['label'] ?? 'Tanpa cabang';
            if ($this->scope->includesAllBranches()) {
                $leaderName = append_branch_suffix($leaderName, $branchLabel);
            }
            $groupLabel = discipleship_group_display_label([
                'progress' => $progress,
                'leader_name' => $leaderName,
            ], $progress !== '-' ? $progress : 'Kelompok DG');

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
                'progress_helper_text' => $groupLabel,
                'member_summary' => $members !== [] ? implode(', ', array_slice($members, 0, 8)) : ($isActiveGroup ? 'Belum ada peserta' : 'Tanpa riwayat peserta'),
                'member_helper_text' => count($members).($isActiveGroup ? ' peserta aktif' : ' peserta tercatat'),
                'member_count' => count($members),
            ];
        })->values();
    }

    /** @return array{total:int,dg1:int,dg2:int,dg3:int} */
    private function stats(string $search, string $status): array
    {
        $row = $this->filteredGroupQuery($search, $status)
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN stage = 'DG 1' THEN 1 ELSE 0 END) AS dg1,
                SUM(CASE WHEN stage = 'DG 2' THEN 1 ELSE 0 END) AS dg2,
                SUM(CASE WHEN stage = 'DG 3' THEN 1 ELSE 0 END) AS dg3")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'dg1' => (int) ($row->dg1 ?? 0),
            'dg2' => (int) ($row->dg2 ?? 0),
            'dg3' => (int) ($row->dg3 ?? 0),
        ];
    }

    private function groupPeople(array $groupIds)
    {
        if ($groupIds === []) {
            return collect();
        }

        return DiscipleshipGroupPerson::query()
            ->whereIn('branch_id', $this->scope->branchIds())
            ->whereIn('discipleship_group_id', $groupIds)
            ->get(['id', 'discipleship_group_id', 'person_id', 'role', 'status', 'ended_on']);
    }

    private function people(array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }

        $branchOptions = $this->scope->optionsById();
        $contextBranchIds = $this->scope->branchIds();

        $query = Person::query()->from('orang as people');
        DiscipleshipPersonProfile::join($query);

        return $query
            ->whereIn('people.id', $personIds)
            ->get([
                'people.id',
                'people.branch_id',
                DB::raw(DiscipleshipPersonProfile::expression('full_name').' as full_name'),
            ])
            ->mapWithKeys(function (Person $person) use ($branchOptions, $contextBranchIds): array {
                $name = trim((string) $person->full_name);
                $personBranchId = (int) $person->branch_id;
                if (! $this->scope->includesAllBranches() && ! in_array($personBranchId, $contextBranchIds, true)) {
                    $branchLabel = $branchOptions[$personBranchId]['label'] ?? '';
                    if ($branchLabel !== '') {
                        $name = append_branch_suffix($name, $branchLabel);
                    }
                }

                return [(int) $person->id => $name];
            })
            ->all();
    }

    private function search(Request $request): string
    {
        $value = trim((string) $request->query('q', ''));

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function statusFilter(Request $request): string
    {
        $status = trim((string) $request->query('status', 'all'));

        return in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all';
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function perPage(Request $request): int
    {
        return max(1, min(self::MAX_PER_PAGE, (int) $request->query('per_page', self::DEFAULT_PER_PAGE)));
    }

    private function emptyMessage(string $search, string $status): string
    {
        return $search !== '' || $status !== 'all'
            ? 'Kelompok tidak ditemukan.'
            : 'Belum ada kelompok.';
    }
}
