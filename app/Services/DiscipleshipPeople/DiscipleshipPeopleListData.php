<?php

namespace App\Services\DiscipleshipPeople;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipGroupLeadership;
use App\Models\DiscipleshipGroupMembership;
use App\Models\DiscipleshipPerson;
use App\Models\DiscipleshipRelationship;
use App\Services\MskParticipants\MskParticipantTableData;
use Illuminate\Support\Facades\Schema;

class DiscipleshipPeopleListData
{
    public function __construct(private readonly MskParticipantTableData $mskParticipantTableData)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forCurrentContext(): array
    {
        $centralReadOnly = is_effective_central_discipleship_readonly();
        $selectedBranch = $centralReadOnly
            ? normalize_central_recap_branch(central_recap_selected_branch())
            : normalize_public_branch_code(current_user_branch());

        $branchCodes = $this->branchCodes($selectedBranch, $centralReadOnly);

        $people = $this->loadPeople($branchCodes);
        $groups = $this->loadGroups($branchCodes);
        $relationships = $this->loadRelationships($branchCodes);
        $leaderships = $this->loadLeaderships($branchCodes);
        $memberships = $this->loadMemberships($branchCodes);
        $mskClasses = $this->loadMskClasses($branchCodes);

        return $this->prepareRows([
            'central_readonly' => $centralReadOnly,
            'selected_branch' => $selectedBranch,
            'branch_codes' => $branchCodes,
            'people' => $people,
            'groups' => $groups,
            'relationships' => $relationships,
            'leaderships' => $leaderships,
            'memberships' => $memberships,
            'msk_classes' => $mskClasses,
        ]);
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
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadPeople(array $branchCodes): array
    {
        return DiscipleshipPerson::query()
            ->select([
                'id',
                'public_id',
                'branch_code',
                'member_public_id',
                'full_name',
                'phone',
                'status',
                'created_at',
                'updated_at',
            ])
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipPerson $person): array => $person->toArray())
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadGroups(array $branchCodes): array
    {
        return DiscipleshipGroup::query()
            ->select([
                'id',
                'public_id',
                'branch_code',
                'name',
                'status',
                'start_stage',
                'current_stage',
                'parent_group_id',
                'parent_group_public_id',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipGroup $group): array => $group->toArray())
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadRelationships(array $branchCodes): array
    {
        return DiscipleshipRelationship::query()
            ->select([
                'id',
                'public_id',
                'branch_code',
                'mentor_person_id',
                'mentor_person_public_id',
                'disciple_person_id',
                'disciple_person_public_id',
                'context_group_id',
                'context_group_public_id',
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
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipRelationship $relationship): array => $relationship->toArray())
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadLeaderships(array $branchCodes): array
    {
        if (Schema::hasTable('discipleship_group_people')) {
            return DiscipleshipGroupPerson::query()
                ->select([
                    'id',
                    'public_id',
                    'branch_code',
                    'discipleship_group_id',
                    'group_public_id',
                    'person_id',
                    'person_public_id',
                    'role',
                    'status',
                    'started_on',
                    'ended_on',
                    'end_reason',
                    'created_at',
                    'updated_at',
                ])
                ->whereIn('branch_code', $branchCodes)
                ->where('role', '!=', 'member')
                ->orderBy('id')
                ->get()
                ->map(static fn (DiscipleshipGroupPerson $leadership): array => [
                    'id' => $leadership->id,
                    'public_id' => $leadership->public_id,
                    'branch_code' => $leadership->branch_code,
                    'discipleship_group_id' => $leadership->discipleship_group_id,
                    'group_public_id' => $leadership->group_public_id,
                    'person_id' => $leadership->person_id,
                    'person_public_id' => $leadership->person_public_id,
                    'role' => $leadership->role,
                    'status' => $leadership->status,
                    'start_date' => $leadership->started_on,
                    'end_date' => $leadership->ended_on,
                    'reason_change' => $leadership->end_reason,
                    'created_at' => $leadership->created_at,
                    'updated_at' => $leadership->updated_at,
                ])
                ->values()
                ->all();
        }

        return DiscipleshipGroupLeadership::query()
            ->select([
                'id',
                'public_id',
                'branch_code',
                'discipleship_group_id',
                'group_public_id',
                'person_id',
                'person_public_id',
                'role',
                'status',
                'start_date',
                'end_date',
                'reason_change',
                'created_at',
                'updated_at',
            ])
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipGroupLeadership $leadership): array => $leadership->toArray())
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadMemberships(array $branchCodes): array
    {
        if (Schema::hasTable('discipleship_group_people')) {
            return DiscipleshipGroupPerson::query()
                ->select([
                    'id',
                    'public_id',
                    'branch_code',
                    'discipleship_group_id',
                    'group_public_id',
                    'person_id',
                    'person_public_id',
                    'role',
                    'stage',
                    'status',
                    'started_on',
                    'ended_on',
                    'end_reason',
                    'created_at',
                    'updated_at',
                ])
                ->whereIn('branch_code', $branchCodes)
                ->where('role', 'member')
                ->orderBy('id')
                ->get()
                ->map(static fn (DiscipleshipGroupPerson $membership): array => [
                    'id' => $membership->id,
                    'public_id' => $membership->public_id,
                    'branch_code' => $membership->branch_code,
                    'discipleship_group_id' => $membership->discipleship_group_id,
                    'group_public_id' => $membership->group_public_id,
                    'person_id' => $membership->person_id,
                    'person_public_id' => $membership->person_public_id,
                    'role' => $membership->role,
                    'stage' => $membership->stage,
                    'status' => $membership->status,
                    'start_date' => $membership->started_on,
                    'end_date' => $membership->ended_on,
                    'reason_end' => $membership->end_reason,
                    'created_at' => $membership->created_at,
                    'updated_at' => $membership->updated_at,
                ])
                ->values()
                ->all();
        }

        return DiscipleshipGroupMembership::query()
            ->select([
                'id',
                'public_id',
                'branch_code',
                'discipleship_group_id',
                'group_public_id',
                'person_id',
                'person_public_id',
                'role',
                'stage',
                'status',
                'start_date',
                'end_date',
                'reason_end',
                'created_at',
                'updated_at',
            ])
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('id')
            ->get()
            ->map(static fn (DiscipleshipGroupMembership $membership): array => $membership->toArray())
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadMskClasses(array $branchCodes): array
    {
        return $this->mskParticipantTableData->participantsForBranches($branchCodes);
    }

    /**
     * @param array<string, mixed> $context
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
        $peopleByPublicId = [];
        $peopleByMemberPublicId = [];
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
            $publicId = trim((string) ($personRow['public_id'] ?? ''));
            $memberPublicId = trim((string) ($personRow['member_public_id'] ?? ''));
            $fullName = trim((string) ($personRow['full_name'] ?? ''));
            if ($fullName === '') {
                $fullName = '-';
            }

            $peopleById[$personId] = [
                'id' => $personId,
                'branch_code' => $branchCode,
                'public_id' => $publicId,
                'member_public_id' => $memberPublicId,
                'name' => $fullName,
                'phone' => trim((string) ($personRow['phone'] ?? '')),
                'status' => 'active',
            ];

            if ($publicId !== '') {
                $peopleByPublicId[$this->branchScopedKey($branchCode, $publicId)] = $personId;
            }
            if ($memberPublicId !== '') {
                $peopleByMemberPublicId[$this->branchScopedKey($branchCode, $memberPublicId)] = $personId;
            }
            $nameKey = strtolower($fullName);
            if ($nameKey !== '' && ! isset($peopleByName[$nameKey])) {
                $peopleByName[$nameKey] = $personId;
            }
        }

        $groupsByPublicId = [];
        foreach ($groups as $groupRow) {
            if (! is_array($groupRow)) {
                continue;
            }

            $groupId = trim((string) ($groupRow['id'] ?? ''));
            $branchCode = normalize_public_branch_code((string) ($groupRow['branch_code'] ?? ''));
            $publicId = trim((string) ($groupRow['public_id'] ?? ''));
            if ($groupId !== '' && $publicId !== '') {
                $groupsByPublicId[$this->branchScopedKey($branchCode, $publicId)] = $groupId;
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
            $publicId = trim((string) ($groupRow['public_id'] ?? ''));
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
                if ($membershipGroupId === '') {
                    $membershipGroupId = $this->resolvePublicIdToId(
                        $groupsByPublicId,
                        $branchCode,
                        trim((string) ($membershipRow['group_public_id'] ?? '')),
                    );
                }
                if ($membershipGroupId === '' || $membershipGroupId !== $groupId) {
                    continue;
                }

                $personId = $this->resolvePersonId(
                    $membershipRow,
                    $branchCode,
                    $peopleByPublicId,
                    $peopleByMemberPublicId,
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
                'public_id' => $publicId,
                'name' => trim((string) ($groupRow['name'] ?? 'Kelompok')) ?: 'Kelompok',
                'status' => $groupStatus,
                'progress' => $progressLabel,
                'member_ids' => $groupStatus === 'active' ? array_keys($activeMemberIds) : array_keys($historyMemberIds),
                'history_member_ids' => array_keys($historyMemberIds),
                'created_at' => $this->stringTimestamp($groupRow['created_at'] ?? null),
                'updated_at' => $this->stringTimestamp($groupRow['updated_at'] ?? null),
            ];
            if ($publicId !== '') {
                $groupsByPublicId[$this->branchScopedKey($branchCode, $publicId)] = $groupId;
            }
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
                $peopleByPublicId,
                $peopleByMemberPublicId,
                $peopleByName,
                'mentor_person_id',
                'mentor_person_public_id',
            );
            $discipleId = $this->resolvePersonId(
                $relationshipRow,
                $branchCode,
                $peopleByPublicId,
                $peopleByMemberPublicId,
                $peopleByName,
                'disciple_person_id',
                'disciple_person_public_id',
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
                $peopleByPublicId,
                $peopleByMemberPublicId,
                $peopleByName,
                'person_id',
                'person_public_id',
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
                $peopleByPublicId,
                $peopleByMemberPublicId,
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
                $peopleByPublicId,
                $peopleByMemberPublicId,
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
            $participantMemberId = trim((string) ($participantRow['member_id'] ?? ''));
            if ($participantMemberId !== '') {
                $scopedKey = $this->branchScopedKey($branchCode, $participantMemberId);
                if (isset($peopleByMemberPublicId[$scopedKey])) {
                    $resolvedPersonId = (string) $peopleByMemberPublicId[$scopedKey];
                }
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

            $memberPublicId = trim((string) ($personRow['member_public_id'] ?? ''));
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
                    ? ('Sedang ' . $lastProgressStage)
                    : ($lastProgressStage . ' Selesai');
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
                $isExternalFallback = $memberPublicId === ''
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
                'role_subtitle' => $childCount > 0 ? ($childCount . ' binaan langsung') : 'Belum punya binaan langsung',
                'progress_badges' => $progressBadges,
                'phone_label' => $phoneLabel,
                'phone_digits' => $phoneDigits,
                'child_count' => $childCount,
                'search_text' => strtolower($name . ' ' . $parentSummary . ' ' . $roleLabel . ' ' . $progressLabel . ' ' . $phone . ' ' . (string) $childCount),
            ];
        }

        return [
            'settings' => ['church_name' => CHURCH_NAME],
            'people' => $peopleRows,
            'totalPeopleRows' => count($peopleRows),
            'peopleInDg1Count' => $peopleInDg1Count,
            'peopleInDg2Count' => $peopleInDg2Count,
            'peopleInDg3Count' => $peopleInDg3Count,
        ];
    }

    private function branchScopedKey(string $branchCode, string $publicId): string
    {
        $branchCode = normalize_public_branch_code($branchCode);
        $publicId = trim($publicId);

        return $branchCode . '|' . $publicId;
    }

    private function resolvePublicIdToId(array $map, string $branchCode, string $publicId): string
    {
        $publicId = trim($publicId);
        if ($publicId === '') {
            return '';
        }

        $branchCode = normalize_public_branch_code($branchCode);
        if ($branchCode !== '') {
            $scopedKey = $this->branchScopedKey($branchCode, $publicId);
            if (isset($map[$scopedKey])) {
                return (string) $map[$scopedKey];
            }
        }

        foreach ($map as $scopedKey => $resolvedId) {
            if (str_ends_with((string) $scopedKey, '|' . $publicId)) {
                return (string) $resolvedId;
            }
        }

        return '';
    }

    private function resolvePersonId(
        array $row,
        string $branchCode,
        array $peopleByPublicId,
        array $peopleByMemberPublicId,
        array $peopleByName,
        string $idKey = 'person_id',
        string $publicIdKey = 'person_public_id',
    ): string {
        $personId = trim((string) ($row[$idKey] ?? ''));
        if ($personId !== '') {
            return $personId;
        }

        $publicId = trim((string) ($row[$publicIdKey] ?? ''));
        if ($publicId !== '') {
            $resolved = $this->resolvePublicIdToId($peopleByPublicId, $branchCode, $publicId);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        $memberPublicId = trim((string) ($row['member_public_id'] ?? ''));
        if ($memberPublicId !== '') {
            $resolved = $this->resolvePublicIdToId($peopleByMemberPublicId, $branchCode, $memberPublicId);
            if ($resolved !== '') {
                return $resolved;
            }
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
