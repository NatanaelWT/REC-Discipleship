<?php

namespace App\Services\Discipleship;

use App\Models\DiscipleshipGroupPerson;
use Illuminate\Support\Collection;

final class DgProgressStateResolver
{
    private const COMPLETION_REASONS = [
        'continued_to_child_group',
        'group_completed',
        'stage_transition',
        'manual_completion',
    ];

    /**
     * @param  Collection<int, DiscipleshipGroupPerson>  $links
     * @return array{
     *     filters:array<int, string>,
     *     steps:array<int, array{
     *         label:string,
     *         state:string,
     *         state_label:string,
     *         is_complete:bool,
     *         is_active:bool,
     *         is_recorded:bool
     *     }>,
     *     summary:string
     * }
     */
    public function resolve(Collection $links): array
    {
        $filters = [];
        $steps = [];
        $currentStage = '';
        $highestCompletedStage = '';

        foreach ([1 => 'DG 1', 2 => 'DG 2', 3 => 'DG 3'] as $rank => $stage) {
            $active = $links->contains(static fn (DiscipleshipGroupPerson $row): bool => $row->role === 'member'
                && $row->stage === $stage
                && $row->status === 'active'
                && $row->ended_on === null);
            $completed = $this->completedStage($links, $rank);
            $recorded = $links->contains(static fn (DiscipleshipGroupPerson $row): bool => $row->role === 'member'
                && $row->stage === $stage);

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
                'is_complete' => $completed,
                'is_active' => $active,
                'is_recorded' => $recorded,
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

    /** @param Collection<int, DiscipleshipGroupPerson> $links */
    private function completedStage(Collection $links, int $rank): bool
    {
        return $links->contains(static function (DiscipleshipGroupPerson $row) use ($rank): bool {
            $stageRank = match ($row->stage) {
                'DG 1' => 1,
                'DG 2' => 2,
                'DG 3' => 3,
                default => 0,
            };
            if ($stageRank > $rank) {
                return true;
            }

            return $stageRank === $rank && in_array($row->end_reason, self::COMPLETION_REASONS, true);
        });
    }
}
