<?php

namespace App\Services\DiscipleshipDashboard;

use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\Discipleship\DiscipleshipReadCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiscipleshipDashboardSummaryQuery
{
    public function __construct(
        private readonly CurrentDiscipleshipScope $scope,
        private readonly DiscipleshipReadCache $cache,
    ) {}

    /** @return array<string, mixed> */
    public function get(): array
    {
        $branchIds = $this->scope->branchIds();
        $data = $this->cache->remember('dashboard-summary', $branchIds, fn (): array => $this->build($branchIds));

        return array_merge($data, [
            'page' => 'discipleship_dashboard',
            'centralReadOnly' => $this->scope->isReadOnly(),
            'selectedBranchId' => $this->scope->selectedBranchId(),
            'selectedBranchLabel' => $this->scope->selectedLabel(),
            'allBranches' => $this->scope->includesAllBranches(),
        ]);
    }

    /** @param array<int, int> $branchIds */
    public function warm(array $branchIds): void
    {
        $this->cache->remember('dashboard-summary', $branchIds, fn (): array => $this->build($branchIds));
    }

    /** @param array<int, int> $branchIds */
    private function build(array $branchIds): array
    {
        $metrics = $this->emptyMetrics($branchIds);
        if ($branchIds !== []) {
            $this->mergeRows($metrics, $this->peopleRows($branchIds));
            $this->mergeRows($metrics, $this->groupRows($branchIds));
            $this->mergeRows($metrics, $this->groupPeopleRows($branchIds));
            $this->mergeRows($metrics, $this->meetingRows($branchIds));
            $this->mergeRows($metrics, $this->mskRows($branchIds));
            $this->mergeRows($metrics, $this->completedJourneyRows($branchIds));
            $this->mergeRows($metrics, $this->overdueGroupRows($branchIds));
            $this->mergeTargets($metrics, $this->targetRows($branchIds));
        }

        $branchRows = [];
        foreach ($metrics as $branchId => $row) {
            $branchRows[] = $this->presentBranch((int) $branchId, $row);
        }
        usort($branchRows, static fn (array $a, array $b): int => strcasecmp($a['branch_label'], $b['branch_label']));

        $total = $this->totalMetrics($metrics);
        $journey = $this->journeyRows($total);
        $groupProgress = $this->groupProgressRows($total);

        return [
            'summaryStats' => $this->summaryStats($total),
            'journeyProgressRows' => $journey,
            'groupProgressRows' => $groupProgress,
            'overallProgress' => $this->averageProgress($journey),
            'branchSummaryRows' => $branchRows,
        ];
    }

    /** @param array<int, int> $branchIds */
    private function emptyMetrics(array $branchIds): array
    {
        $rows = [];
        foreach ($branchIds as $branchId) {
            $rows[$branchId] = [
                'people_count' => 0,
                'active_people_count' => 0,
                'leader_count' => 0,
                'group_count' => 0,
                'dg1_group_count' => 0,
                'dg2_group_count' => 0,
                'dg3_group_count' => 0,
                'meeting_count' => 0,
                'msk_active_count' => 0,
                'completed_msk_count' => 0,
                'incomplete_msk_count' => 0,
                'following_kgap_count' => 0,
                'following_rg_count' => 0,
                'completed_dg1_count' => 0,
                'completed_dg2_count' => 0,
                'completed_dg3_count' => 0,
                'overdue_group_count' => 0,
                'target_people' => 0,
                'target_msk_completed' => 0,
                'target_dg1_people' => 0,
                'target_dg2_people' => 0,
                'target_dg3_people' => 0,
            ];
        }

        return $rows;
    }

    /** @param array<int, int> $branchIds */
    private function peopleRows(array $branchIds): Collection
    {
        return $this->query(static fn () => DB::table('discipleship_people')
            ->whereIn('branch_id', $branchIds)
            ->selectRaw("branch_id, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS people_count")
            ->groupBy('branch_id')->get());
    }

    /** @param array<int, int> $branchIds */
    private function groupRows(array $branchIds): Collection
    {
        return $this->query(static fn () => DB::table('discipleship_groups')
            ->whereIn('branch_id', $branchIds)
            ->selectRaw("branch_id,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS group_count,
                SUM(CASE WHEN status = 'active' AND current_stage = 'DG 1' THEN 1 ELSE 0 END) AS dg1_group_count,
                SUM(CASE WHEN status = 'active' AND current_stage = 'DG 2' THEN 1 ELSE 0 END) AS dg2_group_count,
                SUM(CASE WHEN status = 'active' AND current_stage = 'DG 3' THEN 1 ELSE 0 END) AS dg3_group_count")
            ->groupBy('branch_id')->get());
    }

    /** @param array<int, int> $branchIds */
    private function groupPeopleRows(array $branchIds): Collection
    {
        return $this->query(static fn () => DB::table('discipleship_group_people as gp')
            ->join('discipleship_groups as g', 'g.id', '=', 'gp.discipleship_group_id')
            ->whereIn('gp.branch_id', $branchIds)
            ->where('g.status', 'active')
            ->where('gp.status', 'active')
            ->whereNull('gp.ended_on')
            ->selectRaw("gp.branch_id,
                COUNT(DISTINCT CASE WHEN gp.role = 'member' THEN gp.person_id END) AS active_people_count,
                COUNT(DISTINCT CASE WHEN gp.role <> 'member' THEN gp.person_id END) AS leader_count")
            ->groupBy('gp.branch_id')->get());
    }

    /** @param array<int, int> $branchIds */
    private function meetingRows(array $branchIds): Collection
    {
        return $this->query(static fn () => DB::table('discipleship_meeting_reports')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('meeting_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->selectRaw('branch_id, COUNT(*) AS meeting_count')
            ->groupBy('branch_id')->get());
    }

    /** @param array<int, int> $branchIds */
    private function mskRows(array $branchIds): Collection
    {
        $sessions = DB::getDriverName() === 'sqlite'
            ? "json_array_length(COALESCE(session_numbers, '[]'))"
            : "JSON_LENGTH(COALESCE(session_numbers, '[]'))";

        return $this->query(static fn () => DB::table('msk_participants')
            ->whereIn('branch_id', $branchIds)
            ->selectRaw("branch_id,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS msk_active_count,
                SUM(CASE WHEN status = 'active' AND {$sessions} >= 12 THEN 1 ELSE 0 END) AS completed_msk_count,
                SUM(CASE WHEN status = 'active' AND {$sessions} < 12 THEN 1 ELSE 0 END) AS incomplete_msk_count,
                SUM(CASE WHEN status = 'active' AND journey_bridge_status IN ('sudah_kgap', 'ikut_keduanya') THEN 1 ELSE 0 END) AS following_kgap_count,
                SUM(CASE WHEN status = 'active' AND journey_bridge_status IN ('sudah_rg', 'ikut_keduanya') THEN 1 ELSE 0 END) AS following_rg_count")
            ->groupBy('branch_id')->get());
    }

    /** @param array<int, int> $branchIds */
    private function completedJourneyRows(array $branchIds): Collection
    {
        $reasons = "'continued_to_child_group', 'group_completed', 'stage_transition'";

        return $this->query(static fn () => DB::table('discipleship_group_people as gp')
            ->join('msk_participants as mp', function ($join): void {
                $join->on('mp.discipleship_person_id', '=', 'gp.person_id')
                    ->on('mp.branch_id', '=', 'gp.branch_id');
            })
            ->whereIn('gp.branch_id', $branchIds)
            ->where('mp.status', 'active')
            ->where('gp.role', 'member')
            ->selectRaw("gp.branch_id,
                COUNT(DISTINCT CASE WHEN gp.stage IN ('DG 2', 'DG 3') OR (gp.stage = 'DG 1' AND gp.end_reason IN ({$reasons})) THEN gp.person_id END) AS completed_dg1_count,
                COUNT(DISTINCT CASE WHEN gp.stage = 'DG 3' OR (gp.stage = 'DG 2' AND gp.end_reason IN ({$reasons})) THEN gp.person_id END) AS completed_dg2_count,
                COUNT(DISTINCT CASE WHEN gp.stage = 'DG 3' AND gp.end_reason IN ({$reasons}) THEN gp.person_id END) AS completed_dg3_count")
            ->groupBy('gp.branch_id')->get());
    }

    /** @param array<int, int> $branchIds */
    private function overdueGroupRows(array $branchIds): Collection
    {
        $latest = DB::table('discipleship_meeting_reports')
            ->selectRaw('branch_id, discipleship_group_id, MAX(meeting_date) AS last_report_date')
            ->whereNotNull('discipleship_group_id')
            ->groupBy('branch_id', 'discipleship_group_id');

        return $this->query(static fn () => DB::table('discipleship_groups as g')
            ->leftJoinSub($latest, 'latest_report', function ($join): void {
                $join->on('latest_report.branch_id', '=', 'g.branch_id')
                    ->on('latest_report.discipleship_group_id', '=', 'g.id');
            })
            ->whereIn('g.branch_id', $branchIds)
            ->where('g.status', 'active')
            ->where(function ($query): void {
                $query->whereNull('latest_report.last_report_date')
                    ->orWhere('latest_report.last_report_date', '<', now()->subDays(30)->toDateString());
            })
            ->selectRaw('g.branch_id, COUNT(*) AS overdue_group_count')
            ->groupBy('g.branch_id')->get());
    }

    /** @param array<int, int> $branchIds */
    private function targetRows(array $branchIds): Collection
    {
        return $this->query(static fn () => DB::table('discipleship_targets')
            ->whereIn('branch_id', $branchIds)
            ->get([
                'branch_id',
                'camp_gap_participant_target',
                'msk_completion_target',
                'dg1_completion_target',
                'dg2_completion_target',
                'dg3_completion_target',
            ]));
    }

    private function query(callable $callback): Collection
    {
        try {
            $result = $callback();

            return $result instanceof Collection ? $result : collect($result);
        } catch (Throwable) {
            return collect();
        }
    }

    private function mergeRows(array &$metrics, Collection $rows): void
    {
        foreach ($rows as $row) {
            $branchId = (int) ($row->branch_id ?? 0);
            if (! isset($metrics[$branchId])) {
                continue;
            }
            foreach ((array) $row as $key => $value) {
                if ($key !== 'branch_id' && array_key_exists($key, $metrics[$branchId])) {
                    $metrics[$branchId][$key] = max(0, (int) $value);
                }
            }
        }
    }

    private function mergeTargets(array &$metrics, Collection $rows): void
    {
        foreach ($rows as $row) {
            $branchId = (int) ($row->branch_id ?? 0);
            if (! isset($metrics[$branchId])) {
                continue;
            }
            $metrics[$branchId]['target_people'] = max(0, (int) $row->camp_gap_participant_target);
            $metrics[$branchId]['target_msk_completed'] = max(0, (int) $row->msk_completion_target);
            $metrics[$branchId]['target_dg1_people'] = max(0, (int) $row->dg1_completion_target);
            $metrics[$branchId]['target_dg2_people'] = max(0, (int) $row->dg2_completion_target);
            $metrics[$branchId]['target_dg3_people'] = max(0, (int) $row->dg3_completion_target);
        }
    }

    private function totalMetrics(array $metrics): array
    {
        $total = array_values($metrics)[0] ?? [];
        foreach ($total as $key => $value) {
            $total[$key] = 0;
        }
        foreach ($metrics as $row) {
            foreach ($row as $key => $value) {
                $total[$key] = ($total[$key] ?? 0) + (int) $value;
            }
        }

        return $total;
    }

    private function journeyRows(array $row): array
    {
        return [
            ['label' => 'Selesai MSK', 'value' => $row['completed_msk_count'], 'target' => $row['target_msk_completed'], 'color' => '#0f766e'],
            ['label' => 'Selesai DG 1', 'value' => $row['completed_dg1_count'], 'target' => $row['target_dg1_people'], 'color' => '#65a30d'],
            ['label' => 'Selesai Kamp GAP', 'value' => $row['following_kgap_count'], 'target' => $row['target_people'], 'color' => '#0ea5e9'],
            ['label' => 'Selesai DG 2', 'value' => $row['completed_dg2_count'], 'target' => $row['target_dg2_people'], 'color' => '#ea580c'],
            ['label' => 'Selesai DG 3', 'value' => $row['completed_dg3_count'], 'target' => $row['target_dg3_people'], 'color' => '#dc2626'],
        ];
    }

    private function groupProgressRows(array $row): array
    {
        $target = $row['group_count'];

        return [
            ['label' => 'DG 1 Berjalan', 'value' => $row['dg1_group_count'], 'target' => $target, 'color' => '#65a30d'],
            ['label' => 'DG 2 Berjalan', 'value' => $row['dg2_group_count'], 'target' => $target, 'color' => '#ea580c'],
            ['label' => 'DG 3 Berjalan', 'value' => $row['dg3_group_count'], 'target' => $target, 'color' => '#dc2626'],
        ];
    }

    private function summaryStats(array $row): array
    {
        return [
            ['label' => 'Peserta Aktif', 'value' => $row['active_people_count'], 'sub' => 'Anggota dalam kelompok aktif', 'tone' => 'is-primary'],
            ['label' => 'Pemimpin Aktif', 'value' => $row['leader_count'], 'sub' => 'Pemimpin kelompok aktif', 'tone' => 'is-emerald'],
            ['label' => 'Kelompok Aktif', 'value' => $row['group_count'], 'sub' => 'Kelompok yang sedang berjalan', 'tone' => 'is-dg2'],
            ['label' => 'Pertemuan Bulan Ini', 'value' => $row['meeting_count'], 'sub' => 'Laporan DG bulan berjalan', 'tone' => 'is-amber'],
            ['label' => 'Selesai RG', 'value' => $row['following_rg_count'], 'sub' => 'Peserta berstatus RG', 'tone' => 'is-sky'],
            ['label' => 'Belum Lapor DG 30 Hari', 'value' => $row['overdue_group_count'], 'sub' => 'Kelompok perlu ditindaklanjuti', 'tone' => 'is-rose'],
            ['label' => 'Belum Selesai MSK', 'value' => $row['incomplete_msk_count'], 'sub' => 'Peserta menuju 12 sesi', 'tone' => 'is-slate'],
        ];
    }

    private function presentBranch(int $branchId, array $row): array
    {
        $branch = $this->scope->optionsById()[$branchId] ?? ['label' => 'Cabang '.$branchId];
        $journey = $this->journeyRows($row);

        return array_merge($row, [
            'branch_id' => $branchId,
            'branch_label' => $branch['label'],
            'journey_rows' => $journey,
            'overall_progress' => $this->averageProgress($journey),
        ]);
    }

    private function averageProgress(array $rows): float
    {
        if ($rows === []) {
            return 0.0;
        }
        $total = 0.0;
        foreach ($rows as $row) {
            $target = max(0, (int) $row['target']);
            $value = max(0, (int) $row['value']);
            $total += $target > 0 ? min(100, ($value / $target) * 100) : 0;
        }

        return $total / count($rows);
    }
}
