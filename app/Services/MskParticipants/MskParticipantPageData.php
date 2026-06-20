<?php

namespace App\Services\MskParticipants;

use App\Models\MskParticipant;
use Illuminate\Http\Request;

class MskParticipantPageData
{
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
        $branchIds = branch_ids_from_slugs($branchCodes);
        $editId = trim((string) $request->query('edit', ''));
        $requestedViewId = trim((string) $request->query('view', ''));

        $batchMonthMap = MskParticipant::query()
            ->whereIn('branch_id', $branchIds)
            ->selectRaw('batch_month, COUNT(*) AS aggregate')
            ->groupBy('batch_month')
            ->pluck('aggregate', 'batch_month')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
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
        $search = strtolower(trim((string) $request->query('q', '')));
        $filteredQuery = MskParticipant::query()
            ->select(MskParticipant::VIEW_COLUMNS)
            ->whereIn('branch_id', $branchIds)
            ->when(! $batchMonthFilterIsAll, static fn ($query) => $query->where('batch_month', $batchMonthFilter))
            ->when($search !== '', static function ($query) use ($search): void {
                $query->where(static function ($searchQuery) use ($search): void {
                    $like = '%'.$search.'%';
                    $searchQuery->whereRaw('LOWER(full_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(whatsapp) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                });
            });
        $completedParticipantsFiltered = (clone $filteredQuery)
            ->whereJsonLength('session_numbers', '=', 12)
            ->count();
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $pagination = (clone $filteredQuery)
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
        $pageParticipants = $pagination->getCollection()
            ->map($this->participantViewRow(...))
            ->values()
            ->all();
        $selectedParticipants = [];
        foreach (array_unique(array_filter([$editId, $requestedViewId])) as $selectedId) {
            if (! ctype_digit((string) $selectedId)) {
                continue;
            }
            $selected = MskParticipant::query()
                ->select(MskParticipant::VIEW_COLUMNS)
                ->whereIn('branch_id', $branchIds)
                ->whereKey((int) $selectedId)
                ->first();
            if ($selected instanceof MskParticipant) {
                $selectedParticipants[(string) $selected->getKey()] = $this->participantViewRow($selected);
            }
        }
        $participantsById = array_merge(index_by_id($pageParticipants), $selectedParticipants);
        $editParticipant = $editId !== '' ? ($participantsById[$editId] ?? null) : null;
        foreach ([$editParticipant, $participantsById[$requestedViewId] ?? null] as $selectedParticipant) {
            if (! is_array($selectedParticipant)) {
                continue;
            }
            $selectedId = (string) ($selectedParticipant['id'] ?? '');
            if ($selectedId !== '' && ! isset(index_by_id($pageParticipants)[$selectedId])) {
                array_unshift($pageParticipants, $selectedParticipant);
            }
        }

        return [
            'settings' => ['church_name' => app_church_name()],
            'page' => 'msk_classes',
            'centralReadOnly' => $centralReadOnly,
            'members' => [],
            'mskClasses' => $pageParticipants,
            'people' => [],
            'participantsById' => $participantsById,
            'participantsSorted' => $pageParticipants,
            'participantsFilteredByBatch' => $pageParticipants,
            'participantsPagination' => $pagination,
            'participantsSearch' => $search,
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
            'totalParticipantsFiltered' => $pagination->total(),
            'completedParticipantsFiltered' => $completedParticipantsFiltered,
            'inProgressParticipantsFiltered' => max(0, $pagination->total() - $completedParticipantsFiltered),
            'totalParticipantsAll' => array_sum($batchMonthMap),
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

    /** @return array<string, mixed> */
    private function participantViewRow(MskParticipant $participant): array
    {
        $row = $participant->toViewArray();
        $row['branch_code'] = normalize_public_branch_code((string) $participant->branch_code);

        return $row;
    }
}
