<?php

namespace App\Services\DiscipleshipPeople;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipPerson;
use App\Models\DiscipleshipRelationship;
use App\Services\Discipleship\DiscipleshipReadCache;
use App\Services\MskParticipants\MskParticipantTableData;
use App\Support\ArrayPaginator;
use Illuminate\Http\Request;

class DiscipleshipPeopleListData
{
    public function __construct(
        private readonly MskParticipantTableData $mskParticipantTableData,
        private readonly ArrayPaginator $paginator,
        private readonly DiscipleshipReadCache $cache,
    ) {}

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

        $data = $this->cache->remember('people-list', [...$branchCodes, $centralReadOnly ? 'central' : 'branch'], function () use ($branchCodes, $centralReadOnly, $selectedBranch): array {
            $groupPeople = $this->loadGroupPeople($branchCodes);

            return $this->prepareRows([
                'central_readonly' => $centralReadOnly,
                'selected_branch' => $selectedBranch,
                'branch_codes' => $branchCodes,
                'people' => $this->loadPeople($branchCodes),
                'groups' => $this->loadGroups($branchCodes),
                'relationships' => $this->loadRelationships($branchCodes),
                'leaderships' => array_values(array_filter($groupPeople, static fn (array $row): bool => ($row['role'] ?? '') !== 'member')),
                'memberships' => array_values(array_filter($groupPeople, static fn (array $row): bool => ($row['role'] ?? '') === 'member')),
                'msk_classes' => $this->loadMskClasses($branchCodes),
            ]);
        });
        $data['settings'] = ['church_name' => app_church_name()];

        $search = strtolower(trim((string) $request->query('q', '')));
        $progress = trim((string) $request->query('progress', 'all'));
        $filteredRows = array_values(array_filter($data['people'], static function (array $row) use ($search, $progress): bool {
            if ($search !== '' && ! str_contains((string) ($row['search_text'] ?? ''), $search)) {
                return false;
            }

            return $progress === 'all'
                || in_array($progress, preg_split('/\s+/', (string) ($row['row_filter_state'] ?? '')) ?: [], true);
        }));
        $pagination = $this->paginator->paginate($filteredRows, $request);
        $data['people'] = $pagination->items();
        $data['peoplePagination'] = $pagination;
        $data['filteredPeopleRows'] = $pagination->total();
        $data['peopleSearch'] = $search;
        $data['peopleProgressFilter'] = $progress;

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function branchCodes(string $selectedBranch, bool $centralReadOnly): array
    {
        if ($centralReadOnly && $selectedBranch === 'all') {
            return array_values(array_filter(array_map(
                static fn (array $option): string => normalize_public_branch_code((string) ($option['code'] ?? 'kutisari')),
                public_dg_branch_options(),
            ), static fn (string $branchCode): bool => $branchCode !== ''));
        }

        return [$selectedBranch];
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadPeople(array $branchCodes): array
    {
        return DiscipleshipPerson::query()
            ->select([
                'id',
                'branch_id',
                'full_name',
                'phone',
                'status',
                'created_at',
                'updated_at',
            ])
            ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipPerson $person): array => $person->toArray())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadGroups(array $branchCodes): array
    {
        return DiscipleshipGroup::query()
            ->select([
                'id',
                'branch_id',
                'name',
                'status',
                'start_stage',
                'current_stage',
                'parent_group_id',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipGroup $group): array => $group->toArray())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadRelationships(array $branchCodes): array
    {
        return DiscipleshipRelationship::query()
            ->select([
                'id',
                'branch_id',
                'mentor_person_id',
                'disciple_person_id',
                'context_group_id',
                'relation_type',
                'stage_at_start',
                'status',
                'start_date',
                'end_date',
                'reason_end',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipRelationship $relationship): array => $relationship->toArray())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadGroupPeople(array $branchCodes): array
    {
        return DiscipleshipGroupPerson::query()
            ->select([
                'id',
                'branch_id',
                'discipleship_group_id',
                'person_id',
                'role',
                'stage',
                'status',
                'started_on',
                'ended_on',
                'end_reason',
                'created_at',
                'updated_at',
            ])
            ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipGroupPerson $row): array => [
                'id' => $row->id,
                'branch_code' => $row->branch_code,
                'discipleship_group_id' => $row->discipleship_group_id,
                'person_id' => $row->person_id,
                'role' => $row->role,
                'stage' => $row->stage,
                'status' => $row->status,
                'start_date' => $row->started_on,
                'end_date' => $row->ended_on,
                'reason_change' => $row->end_reason,
                'reason_end' => $row->end_reason,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadMskClasses(array $branchCodes): array
    {
        return $this->mskParticipantTableData->participantsForBranches($branchCodes);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function prepareRows(array $context): array
    {
        $people = array_values(is_array($context['people'] ?? null) ? $context['people'] : []);
        $groups = array_values(is_array($context['groups'] ?? null) ? $context['groups'] : []);
        $relationships = array_values(is_array($context['relationships'] ?? null) ? $context['relationships'] : []);
        $leaderships = array_values(is_array($context['leaderships'] ?? null) ? $context['leaderships'] : []);
        $memberships = array_values(is_array($context['memberships'] ?? null) ? $context['memberships'] : []);
        $mskClasses = array_values(is_array($context['msk_classes'] ?? null) ? $context['msk_classes'] : []);

        $peopleById = [];
        $peopleByName = [];
        foreach ($people as $personRow) {
            if (! is_array($personRow)) {
                continue;
            }
            $personId = trim((string) ($personRow['id'] ?? ''));
            if ($personId === '') {
                continue;
            }

            $status = strtolower(trim((string) ($personRow['status'] ?? 'active')));
            if ($status !== 'active') {
                continue;
            }

            $branchCode = normalize_public_branch_code((string) ($personRow['branch_code'] ?? ''));
            $fullName = trim((string) ($personRow['full_name'] ?? ''));
            if ($fullName === '') {
                $fullName = '-';
            }

            $peopleById[$personId] = [
                'id' => $personId,
                'branch_code' => $branchCode,
                'member_id' => $personId,
                'name' => $fullName,
                'phone' => trim((string) ($personRow['phone'] ?? '')),
                'status' => 'active',
            ];

            $nameKey = strtolower($fullName);
            if ($nameKey !== '' && ! isset($peopleByName[$nameKey])) {
                $peopleByName[$nameKey] = $personId;
            }
        }

        $groupRows = [];
        foreach ($groups as $groupRow) {
            if (! is_array($groupRow)) {
                continue;
            }
            $groupId = trim((string) ($groupRow['id'] ?? ''));
            if ($groupId === '') {
                continue;
            }

            $branchCode = normalize_public_branch_code((string) ($groupRow['branch_code'] ?? ''));
            $groupStatus = strtolower(trim((string) ($groupRow['status'] ?? 'active'))) ?: 'active';
            $progressLabel = normalize_dg_progress_value((string) ($groupRow['current_stage'] ?? ''));
            if ($progressLabel === '') {
                $progressLabel = normalize_dg_progress_value((string) ($groupRow['start_stage'] ?? ''));
            }
            if ($progressLabel === '') {
                $progressLabel = 'DG 1';
            }

            $activeMemberIds = [];
            $historyMemberIds = [];
            foreach ($memberships as $membershipRow) {
                if (! is_array($membershipRow)) {
                    continue;
                }

                $membershipGroupId = trim((string) ($membershipRow['discipleship_group_id'] ?? ''));
                if ($membershipGroupId === '' || $membershipGroupId !== $groupId) {
                    continue;
                }

                $personId = $this->resolvePersonId(
                    $membershipRow,
                    $branchCode,
                    $peopleByName,
                );
                if ($personId === '') {
                    continue;
                }

                $historyMemberIds[$personId] = true;
                if ($this->isCurrentPeriod($membershipRow)) {
                    $activeMemberIds[$personId] = true;
                }
            }

            $groupRows[] = [
                'id' => $groupId,
                'branch_code' => $branchCode,
                'name' => trim((string) ($groupRow['name'] ?? 'Kelompok')) ?: 'Kelompok',
                'status' => $groupStatus,
                'progress' => $progressLabel,
                'member_ids' => $groupStatus === 'active' ? array_keys($activeMemberIds) : array_keys($historyMemberIds),
                'history_member_ids' => array_keys($historyMemberIds),
                'created_at' => $this->stringTimestamp($groupRow['created_at'] ?? null),
                'updated_at' => $this->stringTimestamp($groupRow['updated_at'] ?? null),
            ];
        }

        $parentIdsByPerson = [];
        $childrenMap = [];
        foreach ($relationships as $relationshipRow) {
            if (! is_array($relationshipRow) || ! $this->isCurrentPeriod($relationshipRow)) {
                continue;
            }

            $branchCode = normalize_public_branch_code((string) ($relationshipRow['branch_code'] ?? ''));
            $mentorId = $this->resolvePersonId(
                $relationshipRow,
                $branchCode,
                $peopleByName,
                'mentor_person_id',
            );
            $discipleId = $this->resolvePersonId(
                $relationshipRow,
                $branchCode,
                $peopleByName,
                'disciple_person_id',
            );
            if ($mentorId === '' || $discipleId === '') {
                continue;
            }

            if (! isset($parentIdsByPerson[$discipleId])) {
                $parentIdsByPerson[$discipleId] = [];
            }
            $parentIdsByPerson[$discipleId][$mentorId] = true;

            if (! isset($childrenMap[$mentorId])) {
                $childrenMap[$mentorId] = [];
            }
            $childrenMap[$mentorId][$discipleId] = true;
        }

        $peopleEverLedGroupMap = [];
        foreach ($leaderships as $leadershipRow) {
            if (! is_array($leadershipRow)) {
                continue;
            }

            $branchCode = normalize_public_branch_code((string) ($leadershipRow['branch_code'] ?? ''));
            $leaderPersonId = $this->resolvePersonId(
                $leadershipRow,
                $branchCode,
                $peopleByName,
                'person_id',
            );
            if ($leaderPersonId !== '') {
                $peopleEverLedGroupMap[$leaderPersonId] = true;
            }
        }

        $peopleLastProgressMap = [];
        foreach ($memberships as $membershipRow) {
            if (! is_array($membershipRow)) {
                continue;
            }

            $branchCode = normalize_public_branch_code((string) ($membershipRow['branch_code'] ?? ''));
            $personId = $this->resolvePersonId(
                $membershipRow,
                $branchCode,
                $peopleByName,
            );
            if ($personId === '') {
                continue;
            }

            $stage = normalize_dg_progress_value((string) ($membershipRow['stage'] ?? ''));
            if ($stage === '') {
                continue;
            }

            $sortDate = trim((string) ($membershipRow['end_date'] ?? ''));
            if ($sortDate === '') {
                $sortDate = trim((string) ($membershipRow['start_date'] ?? ''));
            }
            if ($sortDate === '') {
                $sortDate = $this->stringTimestamp($membershipRow['updated_at'] ?? $membershipRow['created_at'] ?? null);
            }

            $existing = $peopleLastProgressMap[$personId] ?? null;
            if (! is_array($existing)) {
                $peopleLastProgressMap[$personId] = [
                    'stage' => $stage,
                    'sort_date' => $sortDate,
                    'stage_rank' => $this->stageRank($stage),
                ];

                continue;
            }

            $existingSortDate = trim((string) ($existing['sort_date'] ?? ''));
            $replaceExisting = false;
            if ($sortDate !== '' && ($existingSortDate === '' || strcmp($sortDate, $existingSortDate) > 0)) {
                $replaceExisting = true;
            } elseif ($sortDate === $existingSortDate && $this->stageRank($stage) > (int) ($existing['stage_rank'] ?? 0)) {
                $replaceExisting = true;
            }

            if ($replaceExisting) {
                $peopleLastProgressMap[$personId] = [
                    'stage' => $stage,
                    'sort_date' => $sortDate,
                    'stage_rank' => $this->stageRank($stage),
                ];
            }
        }

        $peopleCurrentProgressMap = [];
        foreach ($groupRows as $groupRow) {
            if (! is_array($groupRow)) {
                continue;
            }

            $progressLabel = trim((string) ($groupRow['progress'] ?? ''));
            if ($progressLabel === '') {
                $progressLabel = '-';
            }

            $memberIds = $groupRow['member_ids'] ?? [];
            if (! is_array($memberIds)) {
                continue;
            }

            foreach ($memberIds as $memberIdRaw) {
                $memberId = trim((string) $memberIdRaw);
                if ($memberId === '') {
                    continue;
                }

                if (! isset($peopleCurrentProgressMap[$memberId])) {
                    $peopleCurrentProgressMap[$memberId] = [];
                }

                if (! in_array($progressLabel, $peopleCurrentProgressMap[$memberId], true)) {
                    $peopleCurrentProgressMap[$memberId][] = $progressLabel;
                }
            }
        }

        $peopleCompletedDgFilterMap = [
            'dg1' => [],
            'dg2' => [],
            'dg3' => [],
        ];
        $completionReasonValues = ['continued_to_child_group', 'group_completed', 'stage_transition'];
        foreach ($memberships as $membershipRow) {
            if (! is_array($membershipRow)) {
                continue;
            }

            $branchCode = normalize_public_branch_code((string) ($membershipRow['branch_code'] ?? ''));
            $personId = $this->resolvePersonId(
                $membershipRow,
                $branchCode,
                $peopleByName,
            );
            if ($personId === '') {
                continue;
            }

            $stage = normalize_dg_progress_value((string) ($membershipRow['stage'] ?? ''));
            if ($stage === '') {
                continue;
            }

            $stageRank = $this->stageRank($stage);
            $reasonEnd = trim((string) ($membershipRow['reason_end'] ?? ''));
            if ($stageRank >= 2 || ($stage === 'DG 1' && in_array($reasonEnd, $completionReasonValues, true))) {
                $peopleCompletedDgFilterMap['dg1'][$personId] = true;
            }
            if ($stageRank >= 3 || ($stage === 'DG 2' && in_array($reasonEnd, $completionReasonValues, true))) {
                $peopleCompletedDgFilterMap['dg2'][$personId] = true;
            }
            if ($stage === 'DG 3' && in_array($reasonEnd, $completionReasonValues, true)) {
                $peopleCompletedDgFilterMap['dg3'][$personId] = true;
            }
        }

        $peopleBridgeFilterMap = [];
        foreach ($mskClasses as $participantRow) {
            if (! is_array($participantRow)) {
                continue;
            }

            $journeyBridgeStatus = normalize_journey_bridge_status((string) ($participantRow['journey_bridge_status'] ?? 'belum'));
            if (! in_array($journeyBridgeStatus, ['sudah_rg', 'sudah_kgap', 'ikut_keduanya'], true)) {
                continue;
            }

            $branchCode = normalize_public_branch_code((string) ($participantRow['branch_code'] ?? $participantRow['cabang'] ?? ''));
            $resolvedPersonId = '';
            $participantPersonId = trim((string) ($participantRow['member_id'] ?? ''));
            if ($participantPersonId !== '' && isset($peopleById[$participantPersonId])) {
                $resolvedPersonId = $participantPersonId;
            }
            if ($resolvedPersonId === '') {
                $participantNameKey = strtolower(trim((string) ($participantRow['full_name'] ?? '')));
                if ($participantNameKey !== '' && isset($peopleByName[$participantNameKey])) {
                    $resolvedPersonId = (string) $peopleByName[$participantNameKey];
                }
            }
            if ($resolvedPersonId === '') {
                continue;
            }

            if (! isset($peopleBridgeFilterMap[$resolvedPersonId])) {
                $peopleBridgeFilterMap[$resolvedPersonId] = [];
            }

            if (in_array($journeyBridgeStatus, ['sudah_kgap', 'ikut_keduanya'], true)) {
                $peopleBridgeFilterMap[$resolvedPersonId]['kgap_complete'] = true;
            }
            if (in_array($journeyBridgeStatus, ['sudah_rg', 'ikut_keduanya'], true)) {
                $peopleBridgeFilterMap[$resolvedPersonId]['rg_complete'] = true;
            }
        }

        $peopleSorted = array_values($peopleById);
        usort($peopleSorted, function (array $a, array $b) use ($peopleLastProgressMap): int {
            $rankA = $this->stageRank((string) ($peopleLastProgressMap[(string) ($a['id'] ?? '')]['stage'] ?? ''));
            $rankB = $this->stageRank((string) ($peopleLastProgressMap[(string) ($b['id'] ?? '')]['stage'] ?? ''));
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $peopleRows = [];
        $peopleInDg1Count = 0;
        $peopleInDg2Count = 0;
        $peopleInDg3Count = 0;

        foreach ($peopleSorted as $personRow) {
            $personId = trim((string) ($personRow['id'] ?? ''));
            if ($personId === '') {
                continue;
            }

            $name = trim((string) ($personRow['name'] ?? ''));
            if ($name === '') {
                $name = '-';
            }

            $memberId = trim((string) ($personRow['member_id'] ?? ''));
            $parentIds = array_keys($parentIdsByPerson[$personId] ?? []);
            $parentSummary = format_parent_names($peopleById, $parentIds);
            if ($parentSummary === '') {
                $parentSummary = 'Belum terhubung ke pembina';
            }

            $childCount = count($childrenMap[$personId] ?? []);
            $lastProgressStage = trim((string) ($peopleLastProgressMap[$personId]['stage'] ?? ''));
            $currentProgressValues = $peopleCurrentProgressMap[$personId] ?? [];
            if (! is_array($currentProgressValues)) {
                $currentProgressValues = [];
            }
            $currentProgressValues = array_values(array_filter(array_map('strval', $currentProgressValues), static function ($value): bool {
                return trim((string) $value) !== '';
            }));

            $progressLabel = $lastProgressStage !== '' ? $lastProgressStage : '-';
            $roleLabel = (isset($peopleEverLedGroupMap[$personId]) || $childCount > 0) ? 'Pemimpin' : 'Anggota';
            $roleToneClass = 'is-member';
            $roleLabelLower = strtolower($roleLabel);
            if (str_contains($roleLabelLower, 'leader') || str_contains($roleLabelLower, 'pemimpin')) {
                $roleToneClass = 'is-leader';
            } elseif (str_contains($roleLabelLower, 'coach') || str_contains($roleLabelLower, 'mentor')) {
                $roleToneClass = 'is-coach';
            }

            $progressBadges = [];
            $progressFilterState = 'none';
            $progressFilterTokens = [];
            if ($lastProgressStage !== '') {
                $isCurrentStageActive = in_array($lastProgressStage, $currentProgressValues, true);
                $progressFilterState = $isCurrentStageActive ? 'active' : 'complete';
                $progressFilterTokens[] = $progressFilterState;
                $progressBadgeText = $isCurrentStageActive
                    ? ('Sedang '.$lastProgressStage)
                    : ($lastProgressStage.' Selesai');
                $progressToneClass = 'is-neutral';
                if (stripos($lastProgressStage, 'DG 1') !== false) {
                    $progressToneClass = $isCurrentStageActive ? 'is-dg1-active' : 'is-dg1-complete';
                } elseif (stripos($lastProgressStage, 'DG 2') !== false) {
                    $progressToneClass = $isCurrentStageActive ? 'is-dg2-active' : 'is-dg2-complete';
                } elseif (stripos($lastProgressStage, 'DG 3') !== false) {
                    $progressToneClass = $isCurrentStageActive ? 'is-dg3-active' : 'is-dg3-complete';
                }
                $progressBadges[] = [
                    'class' => $progressToneClass,
                    'label' => $progressBadgeText,
                ];
            }
            if ($progressBadges === []) {
                $isExternalFallback = $memberId === ''
                    || isset($peopleEverLedGroupMap[$personId])
                    || $childCount > 0;
                $fallbackProgressLabel = $isExternalFallback ? 'External' : 'Belum masuk progres';
                if ($isExternalFallback) {
                    $progressFilterState = 'external';
                    $progressFilterTokens[] = 'external';
                }
                $progressBadges[] = [
                    'class' => 'is-neutral',
                    'label' => $fallbackProgressLabel,
                ];
            }
            foreach ($currentProgressValues as $progressValue) {
                if (stripos($progressValue, 'DG 1') !== false) {
                    $progressFilterTokens[] = 'active_dg1';
                }
                if (stripos($progressValue, 'DG 2') !== false) {
                    $progressFilterTokens[] = 'active_dg2';
                }
                if (stripos($progressValue, 'DG 3') !== false) {
                    $progressFilterTokens[] = 'active_dg3';
                }
            }
            if (! empty($peopleCompletedDgFilterMap['dg1'][$personId])) {
                $progressFilterTokens[] = 'complete_dg1';
            }
            if (! empty($peopleCompletedDgFilterMap['dg2'][$personId])) {
                $progressFilterTokens[] = 'complete_dg2';
            }
            if (! empty($peopleCompletedDgFilterMap['dg3'][$personId])) {
                $progressFilterTokens[] = 'complete_dg3';
            }
            foreach (($peopleBridgeFilterMap[$personId] ?? []) as $bridgeFilterKey => $hasBridgeStatus) {
                if ($hasBridgeStatus) {
                    $progressFilterTokens[] = (string) $bridgeFilterKey;
                }
            }
            $progressFilterTokens = array_values(array_filter(array_unique($progressFilterTokens), static function ($token): bool {
                return trim((string) $token) !== '';
            }));
            if ($progressFilterTokens === []) {
                $progressFilterTokens[] = $progressFilterState;
            }

            $lastProgressKey = 'none';
            if (stripos($lastProgressStage, 'DG 1') !== false) {
                $lastProgressKey = 'dg1';
                $peopleInDg1Count++;
            } elseif (stripos($lastProgressStage, 'DG 2') !== false) {
                $lastProgressKey = 'dg2';
                $peopleInDg2Count++;
            } elseif (stripos($lastProgressStage, 'DG 3') !== false) {
                $lastProgressKey = 'dg3';
                $peopleInDg3Count++;
            }

            $phone = trim((string) ($personRow['phone'] ?? ''));
            $phoneDigits = normalize_whatsapp_digits($phone);
            $phoneLabel = $phone !== '' ? $phone : 'Belum ada nomor';

            $peopleRows[] = [
                'id' => $personId,
                'row_filter_state' => implode(' ', $progressFilterTokens),
                'row_progress_key' => $lastProgressKey,
                'name' => $name,
                'parent_summary' => $parentSummary !== '' ? $parentSummary : 'Belum terhubung ke pembina',
                'role_label' => $roleLabel,
                'role_tone_class' => $roleToneClass,
                'role_subtitle' => $childCount > 0 ? ($childCount.' binaan langsung') : 'Belum punya binaan langsung',
                'progress_badges' => $progressBadges,
                'phone_label' => $phoneLabel,
                'phone_digits' => $phoneDigits,
                'child_count' => $childCount,
                'search_text' => strtolower($name.' '.$parentSummary.' '.$roleLabel.' '.$progressLabel.' '.$phone.' '.(string) $childCount),
            ];
        }

        return [
            'settings' => ['church_name' => app_church_name()],
            'people' => $peopleRows,
            'totalPeopleRows' => count($peopleRows),
            'peopleInDg1Count' => $peopleInDg1Count,
            'peopleInDg2Count' => $peopleInDg2Count,
            'peopleInDg3Count' => $peopleInDg3Count,
        ];
    }

    private function resolvePersonId(
        array $row,
        string $branchCode,
        array $peopleByName,
        string $idKey = 'person_id',
    ): string {
        $personId = trim((string) ($row[$idKey] ?? ''));
        if ($personId !== '') {
            return $personId;
        }

        $name = strtolower(trim((string) ($row['full_name'] ?? $row['name'] ?? '')));
        if ($name !== '' && isset($peopleByName[$name])) {
            return (string) $peopleByName[$name];
        }

        return '';
    }

    private function isCurrentPeriod(array $row): bool
    {
        if (function_exists('dgv2_is_current_period')) {
            return dgv2_is_current_period($row);
        }

        $status = strtolower(trim((string) ($row['status'] ?? 'active')));
        if (in_array($status, ['inactive', 'archived', 'closed', 'completed'], true)) {
            return false;
        }

        return trim((string) ($row['end_date'] ?? '')) === '';
    }

    private function stageRank(string $stage): int
    {
        $stage = normalize_dg_progress_value($stage);
        if ($stage === 'DG 3') {
            return 3;
        }
        if ($stage === 'DG 2') {
            return 2;
        }
        if ($stage === 'DG 1') {
            return 1;
        }

        return 0;
    }

    private function stringTimestamp(mixed $value): string
    {
        return trim((string) $value);
    }
}
