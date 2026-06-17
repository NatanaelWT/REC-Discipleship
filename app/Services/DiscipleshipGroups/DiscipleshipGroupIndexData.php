<?php

namespace App\Services\DiscipleshipGroups;

use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipGroupLeadership;
use App\Models\DiscipleshipGroupMembership;
use App\Models\DiscipleshipPerson;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DiscipleshipGroupIndexData
{
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
        $branchLabels = $this->branchLabels();

        $people = $this->loadPeople($branchCodes, $centralReadOnly, $branchLabels);
        $groups = $this->loadGroups($branchCodes, $centralReadOnly, $branchLabels);
        $leaderships = $this->loadLeaderships($branchCodes, $centralReadOnly);
        $memberships = $this->loadMemberships($branchCodes, $centralReadOnly);

        return $this->prepareRows([
            'central_readonly' => $centralReadOnly,
            'selected_branch' => $selectedBranch,
            'branch_codes' => $branchCodes,
            'branch_labels' => $branchLabels,
            'people' => $people,
            'groups' => $groups,
            'leaderships' => $leaderships,
            'memberships' => $memberships,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function branchCodes(string $selectedBranch, bool $centralReadOnly): array
    {
        if ($centralReadOnly && $selectedBranch === 'all') {
            return array_map(static fn (array $option): string => normalize_public_branch_code((string) ($option['code'] ?? 'kutisari')), public_dg_branch_options());
        }

        return [$selectedBranch];
    }

    /**
     * @return array<string, string>
     */
    private function branchLabels(): array
    {
        $labels = [];
        foreach (public_dg_branch_options() as $option) {
            $branchCode = normalize_public_branch_code((string) ($option['code'] ?? 'kutisari'));
            $label = trim((string) ($option['label'] ?? strtoupper($branchCode)));
            $labels[$branchCode] = $label !== '' ? $label : strtoupper($branchCode);
        }

        return $labels;
    }

    /**
     * @param array<int, string> $branchCodes
     * @param array<string, string> $branchLabels
     * @return array<string, array<string, mixed>>
     */
    private function loadPeople(array $branchCodes, bool $centralReadOnly, array $branchLabels): array
    {
        $peopleById = [];

        $people = DiscipleshipPerson::query()
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('id')
            ->get();

        foreach ($people as $person) {
            $branchCode = normalize_public_branch_code((string) $person->branch_code);
            $effectiveId = $this->effectiveId($branchCode, (string) $person->public_id);
            if ($effectiveId === '') {
                continue;
            }

            $displayName = trim((string) ($person->full_name ?? ''));
            if ($centralReadOnly) {
                $displayName = append_branch_suffix($displayName, $branchLabels[$branchCode] ?? strtoupper($branchCode));
            }

            $peopleById[$effectiveId] = [
                'id' => $effectiveId,
                'name' => $displayName !== '' ? $displayName : '-',
                'phone' => trim((string) ($person->phone ?? '')),
                'gender' => trim((string) ($person->gender ?? '')),
                'status' => trim((string) ($person->status ?? 'active')) ?: 'active',
                'notes' => trim((string) ($person->notes ?? '')),
                'campus' => trim((string) ($person->campus ?? '')),
                'major' => trim((string) ($person->major ?? '')),
                'occupation' => trim((string) ($person->occupation ?? '')),
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
                'public_id' => (string) $person->public_id,
                'member_public_id' => trim((string) ($person->member_public_id ?? '')),
                'created_at' => $this->stringTimestamp($person->created_at ?? null),
                'updated_at' => $this->stringTimestamp($person->updated_at ?? null),
            ];
        }

        return $peopleById;
    }

    /**
     * @param array<int, string> $branchCodes
     * @param array<string, string> $branchLabels
     * @return array<string, array<string, mixed>>
     */
    private function loadGroups(array $branchCodes, bool $centralReadOnly, array $branchLabels): array
    {
        $groupsById = [];

        $groups = DiscipleshipGroup::query()
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('id')
            ->get();

        foreach ($groups as $group) {
            $branchCode = normalize_public_branch_code((string) $group->branch_code);
            $effectiveId = $this->effectiveId($branchCode, (string) $group->public_id);
            if ($effectiveId === '') {
                continue;
            }

            $groupsById[$effectiveId] = [
                'id' => $effectiveId,
                'public_id' => (string) $group->public_id,
                'branch_code' => $branchCode,
                'branch_label' => $branchLabels[$branchCode] ?? strtoupper($branchCode),
                'name' => trim((string) ($group->name ?? 'Kelompok')) ?: 'Kelompok',
                'status' => strtolower(trim((string) ($group->status ?? 'active'))) ?: 'active',
                'start_stage' => normalize_dg_progress_value((string) ($group->start_stage ?? '')),
                'current_stage' => normalize_dg_progress_value((string) ($group->current_stage ?? '')),
                'parent_group_id' => $this->effectiveId($branchCode, (string) ($group->parent_group_public_id ?? '')),
                'parent_group_public_id' => trim((string) ($group->parent_group_public_id ?? '')),
                'notes' => trim((string) ($group->notes ?? '')),
                'created_at' => $this->stringTimestamp($group->created_at ?? null),
                'updated_at' => $this->stringTimestamp($group->updated_at ?? null),
            ];
        }

        return $groupsById;
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadLeaderships(array $branchCodes, bool $centralReadOnly): array
    {
        $rows = [];

        if (Schema::hasTable('discipleship_group_people')) {
            foreach (DiscipleshipGroupPerson::query()->whereIn('branch_code', $branchCodes)->where('role', '!=', 'member')->orderBy('id')->get() as $leadership) {
                $branchCode = normalize_public_branch_code((string) $leadership->branch_code);
                $groupId = $this->effectiveId($branchCode, (string) $leadership->group_public_id);
                $personId = $this->effectiveId($branchCode, (string) $leadership->person_public_id);
                if ($groupId === '' || $personId === '') {
                    continue;
                }

                $rows[] = [
                    'id' => (string) $leadership->public_id,
                    'branch_code' => $branchCode,
                    'group_id' => $groupId,
                    'leader_person_id' => $personId,
                    'role' => strtolower(trim((string) ($leadership->role ?? 'leader'))) ?: 'leader',
                    'status' => strtolower(trim((string) ($leadership->status ?? 'active'))) ?: 'active',
                    'start_date' => $this->dateString($leadership->started_on ?? null),
                    'end_date' => $this->dateString($leadership->ended_on ?? null),
                    'reason_change' => trim((string) ($leadership->end_reason ?? '')),
                    'created_at' => $this->stringTimestamp($leadership->created_at ?? null),
                    'updated_at' => $this->stringTimestamp($leadership->updated_at ?? null),
                ];
            }

            return $rows;
        }

        $leaderships = DiscipleshipGroupLeadership::query()
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('id')
            ->get();

        foreach ($leaderships as $leadership) {
            $branchCode = normalize_public_branch_code((string) $leadership->branch_code);
            $groupId = $this->effectiveId($branchCode, (string) $leadership->group_public_id);
            $personId = $this->effectiveId($branchCode, (string) $leadership->person_public_id);
            if ($groupId === '' || $personId === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) $leadership->public_id,
                'branch_code' => $branchCode,
                'group_id' => $groupId,
                'leader_person_id' => $personId,
                'role' => strtolower(trim((string) ($leadership->role ?? 'leader'))) ?: 'leader',
                'status' => strtolower(trim((string) ($leadership->status ?? 'active'))) ?: 'active',
                'start_date' => $this->dateString($leadership->start_date ?? null),
                'end_date' => $this->dateString($leadership->end_date ?? null),
                'reason_change' => trim((string) ($leadership->reason_change ?? '')),
                'created_at' => $this->stringTimestamp($leadership->created_at ?? null),
                'updated_at' => $this->stringTimestamp($leadership->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadMemberships(array $branchCodes, bool $centralReadOnly): array
    {
        $rows = [];

        if (Schema::hasTable('discipleship_group_people')) {
            foreach (DiscipleshipGroupPerson::query()->whereIn('branch_code', $branchCodes)->where('role', 'member')->orderBy('id')->get() as $membership) {
                $branchCode = normalize_public_branch_code((string) $membership->branch_code);
                $groupId = $this->effectiveId($branchCode, (string) $membership->group_public_id);
                $personId = $this->effectiveId($branchCode, (string) $membership->person_public_id);
                if ($groupId === '' || $personId === '') {
                    continue;
                }

                $rows[] = [
                    'id' => (string) $membership->public_id,
                    'branch_code' => $branchCode,
                    'group_id' => $groupId,
                    'person_id' => $personId,
                    'role' => strtolower(trim((string) ($membership->role ?? 'member'))) ?: 'member',
                    'stage' => normalize_dg_progress_value((string) ($membership->stage ?? '')),
                    'status' => strtolower(trim((string) ($membership->status ?? 'active'))) ?: 'active',
                    'start_date' => $this->dateString($membership->started_on ?? null),
                    'end_date' => $this->dateString($membership->ended_on ?? null),
                    'reason_end' => trim((string) ($membership->end_reason ?? '')),
                    'created_at' => $this->stringTimestamp($membership->created_at ?? null),
                    'updated_at' => $this->stringTimestamp($membership->updated_at ?? null),
                ];
            }

            return $rows;
        }

        $memberships = DiscipleshipGroupMembership::query()
            ->whereIn('branch_code', $branchCodes)
            ->orderBy('id')
            ->get();

        foreach ($memberships as $membership) {
            $branchCode = normalize_public_branch_code((string) $membership->branch_code);
            $groupId = $this->effectiveId($branchCode, (string) $membership->group_public_id);
            $personId = $this->effectiveId($branchCode, (string) $membership->person_public_id);
            if ($groupId === '' || $personId === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) $membership->public_id,
                'branch_code' => $branchCode,
                'group_id' => $groupId,
                'person_id' => $personId,
                'role' => strtolower(trim((string) ($membership->role ?? 'member'))) ?: 'member',
                'stage' => normalize_dg_progress_value((string) ($membership->stage ?? '')),
                'status' => strtolower(trim((string) ($membership->status ?? 'active'))) ?: 'active',
                'start_date' => $this->dateString($membership->start_date ?? null),
                'end_date' => $this->dateString($membership->end_date ?? null),
                'reason_end' => trim((string) ($membership->reason_end ?? '')),
                'created_at' => $this->stringTimestamp($membership->created_at ?? null),
                'updated_at' => $this->stringTimestamp($membership->updated_at ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function prepareRows(array $context): array
    {
        $centralReadOnly = (bool) ($context['central_readonly'] ?? false);
        $selectedBranch = (string) ($context['selected_branch'] ?? '');
        $branchLabels = is_array($context['branch_labels'] ?? null) ? $context['branch_labels'] : [];
        $peopleById = is_array($context['people'] ?? null) ? $context['people'] : [];
        $groupsById = is_array($context['groups'] ?? null) ? $context['groups'] : [];
        $leaderships = is_array($context['leaderships'] ?? null) ? $context['leaderships'] : [];
        $memberships = is_array($context['memberships'] ?? null) ? $context['memberships'] : [];

        $extractFirstName = static function (string $fullName): string {
            $fullName = trim($fullName);
            if ($fullName === '') {
                return '';
            }
            $parts = preg_split('/\s+/', $fullName);
            if (! is_array($parts) || count($parts) === 0) {
                return '';
            }

            return trim((string) $parts[0]);
        };

        $allPeopleLabelsById = [];
        foreach ($peopleById as $personId => $personRow) {
            $personId = trim((string) $personId);
            if ($personId === '') {
                continue;
            }
            $personName = trim((string) ($personRow['name'] ?? ''));
            $allPeopleLabelsById[$personId] = $personName !== '' ? $personName : '-';
        }

        $groupsSorted = [];
        foreach ($groupsById as $groupId => $groupRow) {
            $groupId = trim((string) $groupId);
            if ($groupId === '') {
                continue;
            }

            $leaderId = '';
            $assistantId = '';
            $latestLeaderSort = '';
            $latestAssistantSort = '';
            foreach ($leaderships as $leadershipRecord) {
                if (! is_array($leadershipRecord)) {
                    continue;
                }
                if (trim((string) ($leadershipRecord['group_id'] ?? '')) !== $groupId) {
                    continue;
                }
                $leaderPersonId = trim((string) ($leadershipRecord['leader_person_id'] ?? ''));
                if ($leaderPersonId === '') {
                    continue;
                }
                $leadershipRole = strtolower(trim((string) ($leadershipRecord['role'] ?? 'leader')));
                $leadershipSort = trim((string) ($leadershipRecord['end_date'] ?? ''));
                if ($leadershipSort === '') {
                    $leadershipSort = trim((string) ($leadershipRecord['start_date'] ?? ''));
                }
                if ($leadershipSort === '') {
                    $leadershipSort = trim((string) ($leadershipRecord['updated_at'] ?? $leadershipRecord['created_at'] ?? ''));
                }
                if ($leadershipRole === 'co_leader' || $leadershipRole === 'assistant') {
                    if ($assistantId === '' || strcmp($leadershipSort, $latestAssistantSort) > 0) {
                        $assistantId = $leaderPersonId;
                        $latestAssistantSort = $leadershipSort;
                    }
                } else {
                    if ($leaderId === '' || strcmp($leadershipSort, $latestLeaderSort) > 0) {
                        $leaderId = $leaderPersonId;
                        $latestLeaderSort = $leadershipSort;
                    }
                }
            }

            $activeMemberIds = [];
            $historyMemberIds = [];
            $historyMemberSortById = [];
            foreach ($memberships as $membershipRecord) {
                if (! is_array($membershipRecord)) {
                    continue;
                }
                if (trim((string) ($membershipRecord['group_id'] ?? '')) !== $groupId) {
                    continue;
                }
                $memberPersonId = trim((string) ($membershipRecord['person_id'] ?? ''));
                if ($memberPersonId === '') {
                    continue;
                }
                $historySort = trim((string) ($membershipRecord['end_date'] ?? ''));
                if ($historySort === '') {
                    $historySort = trim((string) ($membershipRecord['start_date'] ?? ''));
                }
                if ($historySort === '') {
                    $historySort = trim((string) ($membershipRecord['updated_at'] ?? $membershipRecord['created_at'] ?? ''));
                }
                if (! isset($historyMemberSortById[$memberPersonId]) || strcmp($historySort, (string) $historyMemberSortById[$memberPersonId]) > 0) {
                    $historyMemberSortById[$memberPersonId] = $historySort;
                }
                if (! in_array($memberPersonId, $historyMemberIds, true)) {
                    $historyMemberIds[] = $memberPersonId;
                }
                if (dgv2_is_current_period($membershipRecord) && ! in_array($memberPersonId, $activeMemberIds, true)) {
                    $activeMemberIds[] = $memberPersonId;
                }
            }

            usort($historyMemberIds, static function (string $a, string $b) use ($activeMemberIds, $historyMemberSortById): int {
                $aActive = in_array($a, $activeMemberIds, true) ? 1 : 0;
                $bActive = in_array($b, $activeMemberIds, true) ? 1 : 0;
                if ($aActive !== $bActive) {
                    return $bActive <=> $aActive;
                }
                $aSort = (string) ($historyMemberSortById[$a] ?? '');
                $bSort = (string) ($historyMemberSortById[$b] ?? '');
                if ($aSort !== $bSort) {
                    return strcmp($bSort, $aSort);
                }

                return strcmp($a, $b);
            });

            $groupsSorted[] = [
                'id' => $groupId,
                'leader_id' => $leaderId,
                'assistant_id' => $assistantId,
                'name' => trim((string) ($groupRow['name'] ?? 'Kelompok')) ?: 'Kelompok',
                'member_ids' => $activeMemberIds,
                'history_member_ids' => $historyMemberIds,
                'progress' => normalize_dg_progress_value((string) ($groupRow['current_stage'] ?? $groupRow['start_stage'] ?? '')) ?: 'DG 1',
                'status' => strtolower(trim((string) ($groupRow['status'] ?? 'active'))) ?: 'active',
                'notes' => trim((string) ($groupRow['notes'] ?? '')),
                'created_at' => trim((string) ($groupRow['created_at'] ?? '')),
                'updated_at' => trim((string) ($groupRow['updated_at'] ?? '')),
            ];
        }

        usort($groupsSorted, function (array $a, array $b) use ($peopleById): int {
            $progressRank = static function (string $progress): int {
                $normalized = normalize_dg_progress_value($progress);
                if (stripos($normalized, 'DG 1') !== false) {
                    return 1;
                }
                if (stripos($normalized, 'DG 2') !== false) {
                    return 2;
                }
                if (stripos($normalized, 'DG 3') !== false) {
                    return 3;
                }

                return 9;
            };

            $rankA = $progressRank((string) ($a['progress'] ?? ''));
            $rankB = $progressRank((string) ($b['progress'] ?? ''));
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            $statusA = strtolower(trim((string) ($a['status'] ?? 'active')));
            $statusB = strtolower(trim((string) ($b['status'] ?? 'active')));
            $activeA = $statusA === 'active' ? 1 : 0;
            $activeB = $statusB === 'active' ? 1 : 0;
            if ($activeA !== $activeB) {
                return $activeB <=> $activeA;
            }
            $leaderA = person_label($peopleById, (string) ($a['leader_id'] ?? ''), '');
            $leaderB = person_label($peopleById, (string) ($b['leader_id'] ?? ''), '');
            $cmp = strcasecmp($leaderA, $leaderB);
            if ($cmp !== 0) {
                return $cmp;
            }
            $aTime = (string) ($a['created_at'] ?? '');
            $bTime = (string) ($b['created_at'] ?? '');
            if ($aTime !== $bTime) {
                return strcmp($aTime, $bTime);
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        $groupRowsPrepared = [];
        $groupsInDg1Count = 0;
        $groupsInDg2Count = 0;
        $groupsInDg3Count = 0;

        foreach ($groupsSorted as $grp) {
            $leaderId = (string) ($grp['leader_id'] ?? '');
            $assistantId = (string) ($grp['assistant_id'] ?? '');
            $leaderName = person_label($peopleById, $leaderId, '-');
            $assistantName = $assistantId !== '' ? person_label($peopleById, $assistantId, '-') : '-';
            $progressLabel = trim((string) ($grp['progress'] ?? ''));
            if ($progressLabel === '') {
                $progressLabel = '-';
            }
            $groupName = trim((string) ($grp['name'] ?? 'Kelompok'));
            if ($groupName === '') {
                $groupName = 'Kelompok';
            }
            $groupStatus = strtolower(trim((string) ($grp['status'] ?? 'active')));
            $isActiveGroup = $groupStatus === 'active';
            $memberIds = $isActiveGroup ? ($grp['member_ids'] ?? []) : ($grp['history_member_ids'] ?? ($grp['member_ids'] ?? []));
            if (! is_array($memberIds)) {
                $memberIds = [];
            }
            $memberFirstNames = [];
            $memberCount = 0;
            $seenMemberIds = [];
            foreach ($memberIds as $mid) {
                $memberId = trim((string) $mid);
                if ($memberId === '' || isset($seenMemberIds[$memberId])) {
                    continue;
                }
                $seenMemberIds[$memberId] = true;
                $memberName = trim((string) ($allPeopleLabelsById[$memberId] ?? ($peopleById[$memberId]['name'] ?? '')));
                if ($memberName === '') {
                    continue;
                }
                $memberCount++;
                $memberFirstName = $extractFirstName($memberName);
                if ($memberFirstName === '') {
                    continue;
                }
                $memberFirstNames[] = $memberFirstName;
            }
            $memberLabel = count($memberFirstNames) > 0 ? implode(', ', $memberFirstNames) : '-';
            $leaderSummary = $assistantId !== '' && $assistantName !== '-' ? 'Pendamping: ' . $assistantName : 'Tanpa pendamping';
            $progressToneClass = 'is-neutral';
            if (stripos($progressLabel, 'DG 1') !== false) {
                $progressToneClass = 'is-dg1';
                $groupsInDg1Count++;
            } elseif (stripos($progressLabel, 'DG 2') !== false) {
                $progressToneClass = 'is-dg2';
                $groupsInDg2Count++;
            } elseif (stripos($progressLabel, 'DG 3') !== false) {
                $progressToneClass = 'is-dg3';
                $groupsInDg3Count++;
            }
            $memberSummary = $memberCount > 0 ? $memberLabel : 'Belum ada peserta';
            $progressKey = 'none';
            if (stripos($progressLabel, 'DG 1') !== false) {
                $progressKey = 'dg1';
            } elseif (stripos($progressLabel, 'DG 2') !== false) {
                $progressKey = 'dg2';
            } elseif (stripos($progressLabel, 'DG 3') !== false) {
                $progressKey = 'dg3';
            }

            $groupRowsPrepared[] = [
                'row_class' => $isActiveGroup ? 'is-group-active' : 'is-group-inactive',
                'row_status' => $isActiveGroup ? 'active' : 'inactive',
                'row_progress' => $progressKey,
                'leader_name' => $leaderName,
                'leader_summary' => $leaderSummary,
                'group_status_class' => $isActiveGroup ? 'is-active' : 'is-inactive',
                'progress_tone_class' => $progressToneClass,
                'progress_label' => $progressLabel,
                'member_count' => $memberCount,
                'member_summary' => $memberSummary,
                'member_first_names' => $memberFirstNames,
                'member_helper_text' => $memberCount > 0
                    ? ($isActiveGroup ? 'Nama depan peserta aktif dalam kelompok' : 'Riwayat nama depan peserta kelompok')
                    : ($isActiveGroup ? 'Tambahkan peserta dari pohon DG' : 'Belum ada riwayat anggota'),
                'progress_helper_text' => $memberCount > 0
                    ? ($memberCount . ($isActiveGroup ? ' peserta aktif' : ' peserta riwayat'))
                    : ($isActiveGroup ? 'Belum ada peserta aktif' : 'Belum ada riwayat peserta'),
            ];
        }

        return [
            'centralReadOnly' => $centralReadOnly,
            'selectedBranch' => $selectedBranch,
            'settings' => ['church_name' => CHURCH_NAME],
            'groups' => $groupRowsPrepared,
            'totalGroupRows' => count($groupRowsPrepared),
            'groupsInDg1Count' => $groupsInDg1Count,
            'groupsInDg2Count' => $groupsInDg2Count,
            'groupsInDg3Count' => $groupsInDg3Count,
        ];
    }

    private function effectiveId(string $branchCode, string $publicId): string
    {
        $branchCode = normalize_public_branch_code($branchCode);
        $publicId = trim($publicId);
        if ($publicId === '') {
            return '';
        }

        return is_effective_central_discipleship_readonly()
            ? scoped_virtual_id($branchCode, $publicId)
            : $publicId;
    }

    private function stringTimestamp(mixed $value): string
    {
        return trim((string) $value);
    }

    private function dateString(mixed $value): string
    {
        return trim((string) $value);
    }
}
