<?php

namespace App\Services\MskParticipants;

use App\Models\Person;
use Illuminate\Http\Request;

class MskParticipantPageData
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly MskParticipantHistoryData $historyData,
        private readonly MskParticipantProfileData $profileData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCurrentContext(Request $request): array
    {
        return [
            'settings' => ['church_name' => app_church_name()],
            ...$this->paginatedRowsForCurrentContext($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paginatedRowsForCurrentContext(Request $request): array
    {
        $centralReadOnly = is_effective_central_discipleship_readonly();
        $selectedBranch = $centralReadOnly
            ? normalize_central_recap_branch(central_recap_selected_branch())
            : normalize_public_branch_code(current_user_branch());

        $branchCodes = $this->branchCodes($selectedBranch, $centralReadOnly);
        $branchIds = branch_ids_from_slugs($branchCodes);
        $editId = trim((string) $request->query('edit', ''));
        $requestedViewId = trim((string) $request->query('view', ''));

        $rawBatchMonthMap = Person::query()
            ->whereIn('branch_id', $branchIds)
            ->selectRaw('batch_month, COUNT(*) AS aggregate')
            ->groupBy('batch_month')
            ->pluck('aggregate', 'batch_month')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
        $batchMonthMap = [];
        foreach ($rawBatchMonthMap as $batchMonth => $count) {
            $normalizedBatchMonth = import_normalize_month_strict((string) $batchMonth);
            if ($normalizedBatchMonth === '') {
                continue;
            }
            $batchMonthMap[$normalizedBatchMonth] = (int) ($batchMonthMap[$normalizedBatchMonth] ?? 0) + (int) $count;
        }
        $batchMonthOptions = array_keys($batchMonthMap);
        rsort($batchMonthOptions, SORT_STRING);
        $latestBatchMonth = count($batchMonthOptions) > 0 ? $batchMonthOptions[0] : date('Y-m');

        $batchMonthFilterInput = trim((string) $request->query('batch_month', ''));
        $batchMonthFilterIsAll = strtolower($batchMonthFilterInput) === 'all';
        $batchMonthFilterNormalized = $batchMonthFilterInput !== '' ? import_normalize_month_strict($batchMonthFilterInput) : '';
        $batchMonthFilter = $latestBatchMonth;
        if (! $batchMonthFilterIsAll && $batchMonthFilterNormalized !== '' && isset($batchMonthMap[$batchMonthFilterNormalized])) {
            $batchMonthFilter = $batchMonthFilterNormalized;
        }

        $batchMonthFilterParam = $batchMonthFilterIsAll ? 'all' : $batchMonthFilter;
        $batchMonthFilterLabel = $batchMonthFilterIsAll ? 'Semua Batch' : format_indo_month($batchMonthFilter);
        $search = strtolower(trim((string) $request->query('q', '')));
        $filteredQuery = Person::query()
            ->select(Person::VIEW_COLUMNS)
            ->whereIn('branch_id', $branchIds)
            ->when(! $batchMonthFilterIsAll, static fn ($query) => $query->where('batch_month', $batchMonthFilter));

        if ($search !== '') {
            $filteredQuery->where(static function ($query) use ($search): void {
                $query->whereRaw('LOWER(full_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(whatsapp) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$search.'%']);
            });
        }

        $totalParticipantsFiltered = (clone $filteredQuery)->count();
        $completedParticipantsFiltered = (clone $filteredQuery)
            ->get(['session_numbers'])
            ->filter(static fn (Person $participant): bool => count(normalize_msk_session_numbers($participant->session_numbers ?? [])) === 12)
            ->count();

        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $participants = (clone $filteredQuery)
            ->orderBy('full_name')
            ->orderBy('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();
        $hasMore = $participants->count() > $perPage;
        if ($hasMore) {
            $participants = $participants->slice(0, $perPage)->values();
        }
        $pageParticipants = $participants
            ->map($this->participantViewRow(...))
            ->values()
            ->all();
        $selectedParticipants = [];
        foreach (array_unique(array_filter([$editId, $requestedViewId])) as $selectedId) {
            if (! ctype_digit((string) $selectedId)) {
                continue;
            }
            $selected = Person::query()
                ->select(Person::VIEW_COLUMNS)
                ->whereIn('branch_id', $branchIds)
                ->whereKey((int) $selectedId)
                ->first();
            if ($selected instanceof Person) {
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

        $participantHistories = $this->historyData->forParticipants($pageParticipants, $branchIds);

        return [
            'page' => 'msk_classes',
            'centralReadOnly' => $centralReadOnly,
            'members' => [],
            'mskClasses' => $pageParticipants,
            'people' => [],
            'participantsById' => $participantsById,
            'participantsSorted' => $pageParticipants,
            'participantsFilteredByBatch' => $pageParticipants,
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
            'totalParticipantsFiltered' => $totalParticipantsFiltered,
            'completedParticipantsFiltered' => $completedParticipantsFiltered,
            'inProgressParticipantsFiltered' => max(0, $totalParticipantsFiltered - $completedParticipantsFiltered),
            'totalParticipantsAll' => array_sum($batchMonthMap),
            'mskPage' => $page,
            'mskPerPage' => $perPage,
            'hasMoreMskRows' => $hasMore,
            'nextMskPage' => $hasMore ? $page + 1 : null,
            'mskEmptyMessage' => $this->emptyMessage($search, $batchMonthFilterParam),
            'participantHistories' => $participantHistories,
            'participantProfiles' => $this->profileData->forParticipants($pageParticipants, $participantHistories),
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
    private function participantViewRow(Person $participant): array
    {
        $row = $participant->toViewArray();
        $row['branch_code'] = normalize_public_branch_code((string) $participant->branch_code);

        return $row;
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function perPage(Request $request): int
    {
        return max(1, min(self::MAX_PER_PAGE, (int) $request->query('per_page', self::DEFAULT_PER_PAGE)));
    }

    private function emptyMessage(string $search, string $batchMonth): string
    {
        return $search !== '' || $batchMonth !== 'all'
            ? 'Peserta tidak ditemukan.'
            : 'Belum ada data peserta kelas MSK.';
    }
}
