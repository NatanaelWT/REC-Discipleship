<?php

function build_people_tree_group_rows(array $model, array $peopleById): array
{
    $rows = [];
    $groupsById = [];
    foreach (($model['discipleship_groups'] ?? []) as $groupRow) {
        if (! is_array($groupRow)) {
            continue;
        }
        $groupId = trim((string) ($groupRow['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $groupsById[$groupId] = $groupRow;
    }

    $leaderRecordsByGroup = [];
    $assistantRecordsByGroup = [];
    foreach (($model['group_leaderships'] ?? []) as $leadership) {
        if (! is_array($leadership)) {
            continue;
        }
        $groupId = trim((string) ($leadership['group_id'] ?? ''));
        $personId = trim((string) ($leadership['leader_person_id'] ?? ''));
        if ($groupId === '' || $personId === '' || ! isset($peopleById[$personId])) {
            continue;
        }
        $role = strtolower(trim((string) ($leadership['role'] ?? 'leader')));
        $bucket = &$leaderRecordsByGroup;
        if ($role === 'co_leader' || $role === 'assistant') {
            $bucket = &$assistantRecordsByGroup;
        }
        if (! isset($bucket[$groupId])) {
            $bucket[$groupId] = [];
        }
        $bucket[$groupId][] = $leadership;
    }

    $pickLeadershipPersonId = static function (array $records): string {
        usort($records, static function (array $a, array $b): int {
            $activeCompare = (dgv2_is_current_period($b) ? 1 : 0) <=> (dgv2_is_current_period($a) ? 1 : 0);
            if ($activeCompare !== 0) {
                return $activeCompare;
            }
            $dateA = trim((string) ($a['end_date'] ?? $a['start_date'] ?? $a['updated_at'] ?? ''));
            $dateB = trim((string) ($b['end_date'] ?? $b['start_date'] ?? $b['updated_at'] ?? ''));
            if ($dateA !== $dateB) {
                return strcmp($dateB, $dateA);
            }

            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });
        $first = $records[0] ?? null;

        return is_array($first) ? trim((string) ($first['leader_person_id'] ?? '')) : '';
    };

    $chosenGroupIdForMember = [];
    $membershipsByPersonId = [];
    foreach (($model['group_memberships'] ?? []) as $membership) {
        if (! is_array($membership)) {
            continue;
        }
        $groupId = trim((string) ($membership['group_id'] ?? ''));
        $personId = trim((string) ($membership['person_id'] ?? ''));
        if ($groupId === '' || $personId === '') {
            continue;
        }
        $membershipsByPersonId[$personId][] = $membership;
    }

    $stageRank = static function (array $membership) use ($groupsById): int {
        $groupId = trim((string) ($membership['group_id'] ?? ''));
        $group = $groupsById[$groupId] ?? [];
        $stage = normalize_dg_progress_value((string) ($membership['stage'] ?? $group['current_stage'] ?? $group['start_stage'] ?? ''));

        return match ($stage) {
            'DG 3' => 3,
            'DG 2' => 2,
            'DG 1' => 1,
            default => 0,
        };
    };
    $groupProgressionDate = static function (array $membership) use ($groupsById): string {
        $groupId = trim((string) ($membership['group_id'] ?? ''));
        $group = $groupsById[$groupId] ?? [];

        return trim((string) (
            $group['multiplied_at']
            ?? $group['created_at']
            ?? $group['updated_at']
            ?? ''
        ));
    };

    foreach ($membershipsByPersonId as $personId => $mems) {
        usort($mems, static function (array $a, array $b) use ($stageRank, $groupProgressionDate): int {
            $aActive = dgv2_is_current_period($a) ? 1 : 0;
            $bActive = dgv2_is_current_period($b) ? 1 : 0;
            if ($aActive !== $bActive) {
                return $bActive <=> $aActive;
            }
            $dateA = $aActive ? trim((string) ($a['start_date'] ?? '')) : trim((string) ($a['end_date'] ?? ''));
            $dateB = $bActive ? trim((string) ($b['start_date'] ?? '')) : trim((string) ($b['end_date'] ?? ''));
            if ($dateA !== $dateB) {
                return strcmp($dateB, $dateA);
            }

            $stageCompare = $stageRank($b) <=> $stageRank($a);
            if ($stageCompare !== 0) {
                return $stageCompare;
            }

            $groupDateA = $groupProgressionDate($a);
            $groupDateB = $groupProgressionDate($b);
            if ($groupDateA !== $groupDateB) {
                return strcmp($groupDateB, $groupDateA);
            }

            $updatedCompare = strcmp(trim((string) ($b['updated_at'] ?? '')), trim((string) ($a['updated_at'] ?? '')));
            if ($updatedCompare !== 0) {
                return $updatedCompare;
            }

            $groupIdCompare = strcmp(trim((string) ($b['group_id'] ?? '')), trim((string) ($a['group_id'] ?? '')));
            if ($groupIdCompare !== 0) {
                return $groupIdCompare;
            }

            return strcmp(trim((string) ($b['id'] ?? '')), trim((string) ($a['id'] ?? '')));
        });
        if (isset($mems[0]['group_id'])) {
            $chosenGroupIdForMember[$personId] = trim((string) $mems[0]['group_id']);
        }
    }
    $chosenMemberIdsByGroup = [];
    foreach ($chosenGroupIdForMember as $personId => $groupId) {
        $personId = (string) $personId;
        $groupId = (string) $groupId;
        if ($groupId !== '' && isset($peopleById[$personId])) {
            $chosenMemberIdsByGroup[$groupId][] = $personId;
        }
    }

    foreach ($groupsById as $groupId => $groupRow) {
        $leaderPersonId = $pickLeadershipPersonId($leaderRecordsByGroup[$groupId] ?? []);
        if ($leaderPersonId === '' || ! isset($peopleById[$leaderPersonId])) {
            continue;
        }
        $assistantPersonId = $pickLeadershipPersonId($assistantRecordsByGroup[$groupId] ?? []);
        $status = strtolower(trim((string) ($groupRow['status'] ?? 'active')));
        $memberIds = $chosenMemberIdsByGroup[$groupId] ?? [];
        $memberIds = array_values(array_filter(array_unique($memberIds), static function (string $memberId) use ($peopleById): bool {
            return $memberId !== '' && isset($peopleById[$memberId]);
        }));
        usort($memberIds, static function (string $a, string $b) use ($peopleById): int {
            return strcasecmp((string) ($peopleById[$a]['name'] ?? ''), (string) ($peopleById[$b]['name'] ?? ''));
        });

        $rows[] = [
            'id' => $groupId,
            'leader_id' => $leaderPersonId,
            'leader_name' => trim((string) ($peopleById[$leaderPersonId]['name'] ?? '')),
            'assistant_id' => $assistantPersonId,
            'assistant_name' => $assistantPersonId !== '' ? trim((string) ($peopleById[$assistantPersonId]['name'] ?? '')) : '',
            'name' => trim((string) ($groupRow['name'] ?? 'Kelompok')) ?: 'Kelompok',
            'member_ids' => $memberIds,
            'progress' => normalize_dg_progress_value((string) ($groupRow['current_stage'] ?? $groupRow['start_stage'] ?? '')) ?: 'DG 1',
            'parent_group_id' => trim((string) ($groupRow['parent_group_id'] ?? '')),
            'notes' => trim((string) ($groupRow['notes'] ?? '')),
            'status' => $status,
            'created_at' => trim((string) ($groupRow['created_at'] ?? '')),
            'updated_at' => trim((string) ($groupRow['updated_at'] ?? '')),
        ];
    }

    return $rows;
}
