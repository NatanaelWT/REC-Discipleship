<?php

namespace App\Services\MskParticipants;

use App\Models\Person;
use App\Support\StableNameCursor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /** @return array{participant:array<string,mixed>,profile:array<string,mixed>,centralReadOnly:bool,batchMonthFilterParam:string}|null */
    public function detailForCurrentContext(Request $request, int $participantId): ?array
    {
        if ($participantId < 1) {
            return null;
        }

        $centralReadOnly = is_effective_central_discipleship_readonly();
        $selectedBranch = $centralReadOnly
            ? normalize_central_recap_branch(central_recap_selected_branch())
            : normalize_user_branch(current_user_branch());
        $branchIds = branch_ids_from_slugs($this->branchCodes($selectedBranch, $centralReadOnly));
        $participant = Person::query()
            ->select(Person::VIEW_COLUMNS)
            ->whereIn('branch_id', $branchIds)
            ->whereKey($participantId)
            ->first();
        if (! $participant instanceof Person) {
            return null;
        }

        $row = $this->participantViewRow($participant);
        $histories = $this->historyData->forParticipants([$row], $branchIds);
        $profiles = $this->profileData->forParticipants([$row], $histories);
        $id = (string) $participant->getKey();
        $batchMonthInput = strtolower(trim((string) $request->query('batch_month', '')));
        $batchMonth = $batchMonthInput === 'all'
            ? 'all'
            : import_normalize_month_strict($batchMonthInput);
        if ($batchMonth === '') {
            $batchMonth = import_normalize_month_strict((string) $participant->batch_month) ?: date('Y-m');
        }

        return [
            'participant' => $row,
            'profile' => is_array($profiles[$id] ?? null) ? $profiles[$id] : [],
            'centralReadOnly' => $centralReadOnly,
            'batchMonthFilterParam' => $batchMonth,
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
            : normalize_user_branch(current_user_branch());

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

        $summary = (clone $filteredQuery)
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(*) AS total_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$this->sessionCountExpression().' = 12 THEN 1 ELSE 0 END), 0) AS completed_count')
            ->toBase()
            ->first();
        $totalParticipantsFiltered = (int) ($summary->total_count ?? 0);
        $completedParticipantsFiltered = (int) ($summary->completed_count ?? 0);

        $limit = $this->limit($request);
        $cursor = StableNameCursor::decode($request->query('cursor'));
        // full_name is normalized on write and is covered by the branch/batch/name/id index.
        $nameExpression = 'full_name';
        $participantsQuery = (clone $filteredQuery)
            ->addSelect(DB::raw($nameExpression.' as cursor_name'))
            ->orderByRaw($nameExpression)
            ->orderBy('id')
            ->limit($limit + 1);
        StableNameCursor::apply($participantsQuery, $nameExpression, 'id', $cursor, nullableName: true);
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
        $pageParticipants = $participants
            ->map($this->participantViewRow(...))
            ->values()
            ->all();
        $selectedParticipants = [];
        $resolvedSelectedIds = [];
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
                $resolvedId = (string) $selected->getKey();
                $selectedParticipants[$resolvedId] = $this->participantViewRow($selected);
                $resolvedSelectedIds[(string) $selectedId] = $resolvedId;
            }
        }
        if ($search !== '' && $totalParticipantsFiltered === 1 && count($pageParticipants) === 1) {
            $fallbackId = (string) ($pageParticipants[0]['id'] ?? '');
            if ($fallbackId !== '') {
                if ($editId !== '' && ! isset($resolvedSelectedIds[$editId])) {
                    $resolvedSelectedIds[$editId] = $fallbackId;
                    $selectedParticipants[$fallbackId] = $pageParticipants[0];
                }
                if ($requestedViewId !== '' && ! isset($resolvedSelectedIds[$requestedViewId])) {
                    $resolvedSelectedIds[$requestedViewId] = $fallbackId;
                    $selectedParticipants[$fallbackId] = $pageParticipants[0];
                }
            }
        }
        $participantsById = array_merge(index_by_id($pageParticipants), $selectedParticipants);
        $resolvedEditId = $editId !== '' ? (string) ($resolvedSelectedIds[$editId] ?? $editId) : '';
        $resolvedViewId = $requestedViewId !== '' ? (string) ($resolvedSelectedIds[$requestedViewId] ?? $requestedViewId) : '';
        $editParticipant = $resolvedEditId !== '' ? ($participantsById[$resolvedEditId] ?? null) : null;
        foreach ([$editParticipant, $participantsById[$resolvedViewId] ?? null] as $selectedParticipant) {
            if (! is_array($selectedParticipant)) {
                continue;
            }
            $selectedId = (string) ($selectedParticipant['id'] ?? '');
            if ($selectedId !== '' && ! isset(index_by_id($pageParticipants)[$selectedId])) {
                array_unshift($pageParticipants, $selectedParticipant);
            }
        }

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
            'editId' => $resolvedEditId,
            'editParticipant' => $editParticipant,
            'autoOpenEditParticipantId' => $editParticipant !== null ? $resolvedEditId : '',
            'requestedViewId' => $resolvedViewId,
            'autoOpenViewParticipantId' => $resolvedViewId !== '' && isset($participantsById[$resolvedViewId]) ? $resolvedViewId : '',
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
            'mskLimit' => $limit,
            'hasMoreMskRows' => $hasMore,
            'nextMskCursor' => $nextCursor,
            'mskEmptyMessage' => $this->emptyMessage($search, $batchMonthFilterParam),
            'participantHistories' => [],
            'participantProfiles' => [],
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
        $row['branch_code'] = normalize_user_branch((string) $participant->branch_code);

        return $row;
    }

    private function sessionCountExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'COALESCE(json_array_length(session_numbers), 0)'
            : 'COALESCE(JSON_LENGTH(session_numbers), 0)';
    }

    private function limit(Request $request): int
    {
        return max(1, min(self::MAX_PER_PAGE, (int) $request->query('limit', self::DEFAULT_PER_PAGE)));
    }

    private function emptyMessage(string $search, string $batchMonth): string
    {
        return $search !== '' || $batchMonth !== 'all'
            ? 'Peserta tidak ditemukan.'
            : 'Belum ada data peserta kelas MSK.';
    }
}
