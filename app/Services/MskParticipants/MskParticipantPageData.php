<?php

namespace App\Services\MskParticipants;

use Illuminate\Http\Request;

class MskParticipantPageData
{
    public function __construct(
        private readonly MskParticipantTableData $tableData,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forCurrentContext(Request $request): array
    {
        $centralReadOnly = is_effective_central_discipleship_readonly();
        $selectedBranch = $centralReadOnly
            ? normalize_central_recap_branch(central_recap_selected_branch())
            : normalize_public_branch_code(current_user_branch());

        $branchCodes = $this->branchCodes($selectedBranch, $centralReadOnly);
        $participantsSorted = $this->tableData->participantsForBranches($branchCodes);
        usort($participantsSorted, static function ($a, $b): int {
            return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
        });

        $participantsById = index_by_id($participantsSorted);
        $editId = trim((string) $request->query('edit', ''));
        $editParticipant = $editId !== '' ? ($participantsById[$editId] ?? null) : null;
        $requestedViewId = trim((string) $request->query('view', ''));

        $batchMonthMap = $this->batchMonthMap($participantsSorted);
        $batchMonthOptions = array_keys($batchMonthMap);
        rsort($batchMonthOptions, SORT_STRING);
        $latestBatchMonth = count($batchMonthOptions) > 0 ? $batchMonthOptions[0] : date('Y-m');

        $batchMonthFilterInput = trim((string) $request->query('batch_month', ''));
        $batchMonthFilterIsAll = strtolower($batchMonthFilterInput) === 'all';
        $batchMonthFilterNormalized = $batchMonthFilterInput !== '' ? normalize_month_value($batchMonthFilterInput) : '';
        $batchMonthFilter = $latestBatchMonth;
        if (! $batchMonthFilterIsAll && $batchMonthFilterNormalized !== '' && isset($batchMonthMap[$batchMonthFilterNormalized])) {
            $batchMonthFilter = $batchMonthFilterNormalized;
        }

        $batchMonthFilterParam = $batchMonthFilterIsAll ? 'all' : $batchMonthFilter;
        $batchMonthFilterLabel = $batchMonthFilterIsAll ? 'Semua Batch' : format_indo_month($batchMonthFilter);
        $participantsFilteredByBatch = $this->participantsFilteredByBatch($participantsSorted, $batchMonthFilterIsAll, $batchMonthFilter);
        $completedParticipantsFiltered = $this->completedCount($participantsFilteredByBatch);
        $totalParticipantsFiltered = count($participantsFilteredByBatch);

        return [
            'settings' => ['church_name' => app_church_name()],
            'page' => 'msk_classes',
            'centralReadOnly' => $centralReadOnly,
            'members' => [],
            'mskClasses' => $participantsSorted,
            'people' => [],
            'participantsById' => $participantsById,
            'participantsSorted' => $participantsSorted,
            'participantsFilteredByBatch' => $participantsFilteredByBatch,
            'editId' => $editId,
            'editParticipant' => $editParticipant,
            'autoOpenEditParticipantId' => $editParticipant !== null ? $editId : '',
            'requestedViewId' => $requestedViewId,
            'autoOpenViewParticipantId' => $requestedViewId !== '' && isset($participantsById[$requestedViewId]) ? $requestedViewId : '',
            'batchMonthMap' => $batchMonthMap,
            'batchMonthOptions' => $batchMonthOptions,
            'latestBatchMonth' => $latestBatchMonth,
            'batchMonthFilterInput' => $batchMonthFilterInput,
            'batchMonthFilterIsAll' => $batchMonthFilterIsAll,
            'batchMonthFilter' => $batchMonthFilter,
            'batchMonthFilterParam' => $batchMonthFilterParam,
            'batchMonthFilterLabel' => $batchMonthFilterLabel,
            'totalParticipantsFiltered' => $totalParticipantsFiltered,
            'completedParticipantsFiltered' => $completedParticipantsFiltered,
            'inProgressParticipantsFiltered' => max(0, $totalParticipantsFiltered - $completedParticipantsFiltered),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function branchCodes(string $selectedBranch, bool $centralReadOnly): array
    {
        if ($centralReadOnly && $selectedBranch === 'all') {
            return array_values(array_filter(array_map(
                static fn (array $option): string => normalize_public_branch_code((string) ($option['code'] ?? '')),
                public_dg_branch_options(),
            ), static fn (string $branchCode): bool => $branchCode !== ''));
        }

        return [$selectedBranch];
    }

    /**
     * @param array<int, array<string, mixed>> $participants
     * @return array<string, int>
     */
    private function batchMonthMap(array $participants): array
    {
        $map = [];
        foreach ($participants as $participant) {
            $participantBatchMonth = normalize_month_value((string) ($participant['msk_month'] ?? date('Y-m')));
            if (! isset($map[$participantBatchMonth])) {
                $map[$participantBatchMonth] = 0;
            }
            $map[$participantBatchMonth]++;
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $participants
     * @return array<int, array<string, mixed>>
     */
    private function participantsFilteredByBatch(array $participants, bool $isAll, string $batchMonth): array
    {
        if ($isAll) {
            return $participants;
        }

        return array_values(array_filter($participants, static function (array $participant) use ($batchMonth): bool {
            return normalize_month_value((string) ($participant['msk_month'] ?? date('Y-m'))) === $batchMonth;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $participants
     */
    private function completedCount(array $participants): int
    {
        $count = 0;
        foreach ($participants as $participant) {
            if (msk_is_complete($participant)) {
                $count++;
            }
        }

        return $count;
    }
}
