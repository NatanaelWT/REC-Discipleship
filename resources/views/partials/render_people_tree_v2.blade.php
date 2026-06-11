<?php

function render_people_tree_v2(
    string $personId,
    array $peopleById,
    array $childrenMap,
    array $groupsByLeader,
    array $membersById,
    string $rootLeaderId,
    array $stack = [],
    int $depth = 0,
    bool $canManageTree = true
): void {
    if ($personId === '' || !isset($peopleById[$personId]) || in_array($personId, $stack, true)) {
        return;
    }

    $stack[] = $personId;
    $person = $peopleById[$personId];
    $isRoot = $personId === $rootLeaderId;
    $personName = trim((string) ($person['name'] ?? '-'));
    if ($personName === '') {
        $personName = '-';
    }
    $role = trim((string) ($person['role'] ?? ''));
    if ($role === '') {
        $role = $isRoot ? 'Leader Utama' : 'Anggota';
    }

    $children = $childrenMap[$personId] ?? [];
    if (!is_array($children)) {
        $children = [];
    }
    usort($children, function ($a, $b): int {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    $directChildIds = [];
    foreach ($children as $childRow) {
        if (!is_array($childRow)) {
            continue;
        }
        $childId = trim((string) ($childRow['id'] ?? ''));
        if ($childId === '') {
            continue;
        }
        $directChildIds[$childId] = true;
    }

    $leaderGroups = $groupsByLeader[$personId] ?? [];
    if (!is_array($leaderGroups)) {
        $leaderGroups = [];
    }
    usort($leaderGroups, function ($a, $b): int {
        $aTime = trim((string) ($a['created_at'] ?? ''));
        $bTime = trim((string) ($b['created_at'] ?? ''));
        if ($aTime !== $bTime) {
            return strcmp($aTime, $bTime);
        }
        $aId = trim((string) ($a['id'] ?? ''));
        $bId = trim((string) ($b['id'] ?? ''));
        if ($aId !== $bId) {
            return strcmp($aId, $bId);
        }
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $groupBranches = [];
    $assignedChildIds = [];
    foreach ($leaderGroups as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $groupName = trim((string) ($groupRow['name'] ?? 'Kelompok'));
        if ($groupName === '') {
            $groupName = 'Kelompok';
        }
        $isVirtualGroup = !empty($groupRow['virtual']);
        if ($isRoot && $isVirtualGroup && strcasecmp($groupName, 'kelompok') === 0) {
            $groupName = 'Jalur Pemuridan';
        }
        $progressLabel = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        if ($progressLabel === '') {
            $progressLabel = '-';
        }
        $assistantId = trim((string) ($groupRow['assistant_id'] ?? ''));
        $assistantName = $assistantId !== '' ? person_label($peopleById, $assistantId, '') : '';
        $memberIds = $groupRow['member_ids'] ?? [];
        if (!is_array($memberIds)) {
            $memberIds = [];
        }
        $normalizedMemberIds = [];
        $seenMemberIds = [];
        foreach ($memberIds as $memberIdRaw) {
            $memberId = trim((string) $memberIdRaw);
            if ($memberId === '' || isset($seenMemberIds[$memberId]) || !isset($peopleById[$memberId])) {
                continue;
            }
            $seenMemberIds[$memberId] = true;
            $normalizedMemberIds[] = $memberId;
            if (isset($directChildIds[$memberId])) {
                $assignedChildIds[$memberId] = true;
            }
        }
        usort($normalizedMemberIds, function (string $a, string $b) use ($peopleById): int {
            $nameA = trim((string) ($peopleById[$a]['name'] ?? ''));
            $nameB = trim((string) ($peopleById[$b]['name'] ?? ''));
            return strcasecmp($nameA, $nameB);
        });
        $groupBranches[] = [
            'id' => trim((string) ($groupRow['id'] ?? '')),
            'name' => $groupName,
            'progress' => $progressLabel,
            'assistant_id' => $assistantId,
            'assistant_name' => $assistantName,
            'notes' => str_replace(["\r", "\n"], ' ', (string) ($groupRow['notes'] ?? '')),
            'member_ids' => $normalizedMemberIds,
            'is_virtual' => $isVirtualGroup,
            'is_ungrouped' => false,
            'parent_group_id' => trim((string) ($groupRow['parent_group_id'] ?? '')),
            'status' => strtolower(trim((string) ($groupRow['status'] ?? 'active'))),
            'created_at' => trim((string) ($groupRow['created_at'] ?? '')),
        ];
    }

    $ungroupedMemberIds = [];
    foreach (array_keys($directChildIds) as $childId) {
        if (isset($assignedChildIds[$childId])) {
            continue;
        }
        $ungroupedMemberIds[] = $childId;
    }
    if (count($ungroupedMemberIds) > 0) {
        usort($ungroupedMemberIds, function (string $a, string $b) use ($peopleById): int {
            $nameA = trim((string) ($peopleById[$a]['name'] ?? ''));
            $nameB = trim((string) ($peopleById[$b]['name'] ?? ''));
            return strcasecmp($nameA, $nameB);
        });
        $groupBranches[] = [
            'id' => '',
            'name' => 'Tanpa Kelompok',
            'progress' => '-',
            'assistant_id' => '',
            'assistant_name' => '',
            'notes' => '',
            'member_ids' => $ungroupedMemberIds,
            'is_virtual' => false,
            'is_ungrouped' => true,
            'parent_group_id' => '',
            'status' => 'active',
            'created_at' => '',
        ];
    }

    $groupBranchIndexById = [];
    foreach ($groupBranches as $groupBranchIndex => $groupBranch) {
        if (!is_array($groupBranch)) {
            continue;
        }
        $groupId = trim((string) ($groupBranch['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $groupBranchIndexById[$groupId] = $groupBranchIndex;
    }
    $topLevelGroupBranches = [];
    foreach ($groupBranches as $groupBranchIndex => $groupBranch) {
        if (!is_array($groupBranch)) {
            continue;
        }
        $groupBranch['child_groups'] = $groupBranch['child_groups'] ?? [];
        $parentGroupId = trim((string) ($groupBranch['parent_group_id'] ?? ''));
        if ($parentGroupId !== '' && isset($groupBranchIndexById[$parentGroupId])) {
            $parentIndex = $groupBranchIndexById[$parentGroupId];
            if (!isset($groupBranches[$parentIndex]['child_groups']) || !is_array($groupBranches[$parentIndex]['child_groups'])) {
                $groupBranches[$parentIndex]['child_groups'] = [];
            }
            $groupBranches[$parentIndex]['child_groups'][] = $groupBranch;
            continue;
        }
        $topLevelGroupBranches[] = $groupBranch;
    }
    $groupBranches = $topLevelGroupBranches;

    $personMetaParts = [];
    if ($isRoot) {
        $personMetaParts[] = 'Akar Pemuridan';
    } else {
        $personMetaParts[] = $role;
    }
    if (count($groupBranches) > 0) {
        $personMetaParts[] = (string) count($groupBranches) . ' kelompok';
    }
    if (count($directChildIds) > 0) {
        $personMetaParts[] = (string) count($directChildIds) . ' anggota';
    } elseif (!$isRoot) {
        $personMetaParts[] = 'Belum ada binaan';
    }
    $personMetaLabel = implode(' • ', $personMetaParts);

    $personGender = normalize_member_gender_value((string) ($person['gender'] ?? ''));
    if ($personGender === '') {
        $personMemberId = trim((string) ($person['member_id'] ?? ''));
        if ($personMemberId !== '' && isset($membersById[$personMemberId])) {
            $personGender = normalize_member_gender_value((string) ($membersById[$personMemberId]['gender'] ?? ''));
        }
    }

    $leaderIds = get_parent_ids($person);
    $leader1 = trim((string) ($leaderIds[0] ?? ''));
    $attrName = str_replace(["\r", "\n"], ' ', (string) ($person['name'] ?? ''));
    $attrPhone = str_replace(["\r", "\n"], ' ', (string) ($person['phone'] ?? ''));
    $attrNotes = str_replace(["\r", "\n"], ' ', (string) ($person['notes'] ?? ''));
    $attrMemberId = trim((string) ($person['member_id'] ?? ''));
    $canPersonManage = $canManageTree && !$isRoot;

    $personItemClass = 'tree-v2-item tree-v2-item-person' . ($isRoot ? ' is-root' : '');
    $personNodeClass = 'tree-v2-node tree-v2-person' . ($isRoot ? ' is-root' : '');
    if ($personGender === 'Laki-laki') {
        $personNodeClass .= ' is-male';
    } elseif ($personGender === 'Perempuan') {
        $personNodeClass .= ' is-female';
    }
    if ($canPersonManage) {
        $personNodeClass .= ' is-actionable';
    }
    $personNodeAttrs = '';
    if ($canPersonManage) {
        $personNodeAttrs .= ' data-tree-v2-node-action="person"';
        $personNodeAttrs .= ' data-person-id="' . h($personId) . '"';
        $personNodeAttrs .= ' data-member-id="' . h($attrMemberId) . '"';
        $personNodeAttrs .= ' data-name="' . h($attrName) . '"';
        $personNodeAttrs .= ' data-phone="' . h($attrPhone) . '"';
        $personNodeAttrs .= ' data-notes="' . h($attrNotes) . '"';
        $personNodeAttrs .= ' data-leader1-id="' . h($leader1) . '"';
        $personNodeAttrs .= ' data-is-root="' . ($isRoot ? '1' : '0') . '"';
        $personNodeAttrs .= ' tabindex="0" role="button" aria-label="Aksi untuk ' . h($personName) . '"';
    }
    echo "<li class=\"" . h($personItemClass) . "\" style=\"--tree-v2-depth:" . h((string) max(0, $depth)) . ";\">\n";
    echo "  <article class=\"" . h($personNodeClass) . "\"" . $personNodeAttrs . ">\n";
    echo "    <div class=\"tree-v2-node-head\">\n";
    echo "      <div class=\"tree-v2-name\">" . h($personName) . "</div>\n";
    if ($isRoot) {
        echo "      <span class=\"badge warning\">Akar</span>\n";
    } else {
        echo "      <span class=\"badge muted\">" . h($role) . "</span>\n";
    }
    echo "    </div>\n";
    echo "    <div class=\"tree-v2-meta\">" . h($personMetaLabel) . "</div>\n";
    echo "  </article>\n";

    if (count($groupBranches) > 0) {
        echo "  <ul class=\"tree-v2-children tree-v2-level-groups\">\n";
        foreach ($groupBranches as $groupBranch) {
            if (!is_array($groupBranch)) {
                continue;
            }
            $groupName = trim((string) ($groupBranch['name'] ?? 'Kelompok'));
            if ($groupName === '') {
                $groupName = 'Kelompok';
            }
            $groupProgress = trim((string) ($groupBranch['progress'] ?? '-'));
            if ($groupProgress === '') {
                $groupProgress = '-';
            }
            $groupId = trim((string) ($groupBranch['id'] ?? ''));
            $groupAssistantName = trim((string) ($groupBranch['assistant_name'] ?? ''));
            $groupAssistantId = trim((string) ($groupBranch['assistant_id'] ?? ''));
            $groupNotes = str_replace(["\r", "\n"], ' ', (string) ($groupBranch['notes'] ?? ''));
            $groupParentId = trim((string) ($groupBranch['parent_group_id'] ?? ''));
            $groupMemberIds = $groupBranch['member_ids'] ?? [];
            if (!is_array($groupMemberIds)) {
                $groupMemberIds = [];
            }
            $isUngrouped = !empty($groupBranch['is_ungrouped']);
            $isVirtualGroup = !empty($groupBranch['is_virtual']);
            $groupItemClass = 'tree-v2-item tree-v2-item-group' . ($isUngrouped ? ' is-ungrouped' : '');
            $groupProgressBadgeClass = $groupProgress !== '-' ? 'badge warning' : 'badge muted';
            $groupStatusBadgeClass = 'badge tree-v2-status-badge is-active';
            $groupMetaParts = [(string) count($groupMemberIds) . ' anggota'];
            if ($groupProgress !== '-') {
                $groupMetaParts[] = $groupProgress;
            }
            if ($groupAssistantName !== '') {
                $groupMetaParts[] = 'Pendamping: ' . $groupAssistantName;
            }
            $groupMetaLabel = implode(' • ', $groupMetaParts);

            $groupNodeClass = 'tree-v2-node tree-v2-group';
            if ($groupProgress !== '-') {
                $groupProgressToken = strtolower(str_replace([' ', '-'], '', $groupProgress));
                if ($groupProgressToken === 'dg1') {
                    $groupNodeClass .= ' is-dg1';
                } elseif ($groupProgressToken === 'dg2') {
                    $groupNodeClass .= ' is-dg2';
                } elseif ($groupProgressToken === 'dg3') {
                    $groupNodeClass .= ' is-dg3';
                }
            }
            $canGroupManage = $canManageTree && !$isUngrouped;
            if ($canGroupManage) {
                $groupNodeClass .= ' is-actionable';
            }
            $groupMembersCsv = implode(',', array_map('strval', $groupMemberIds));
            $groupNodeAttrs = '';
            if ($canGroupManage) {
                $groupNodeAttrs .= ' data-tree-v2-node-action="group"';
                $groupNodeAttrs .= ' data-group-id="' . h($groupId) . '"';
                $groupNodeAttrs .= ' data-leader-id="' . h($personId) . '"';
                $groupNodeAttrs .= ' data-leader-name="' . h($attrName) . '"';
                $groupNodeAttrs .= ' data-assistant-id="' . h($groupAssistantId) . '"';
                $groupNodeAttrs .= ' data-progress="' . h($groupProgress) . '"';
                $groupNodeAttrs .= ' data-notes="' . h($groupNotes) . '"';
                $groupNodeAttrs .= ' data-parent-group-id="' . h($groupParentId) . '"';
                $groupNodeAttrs .= ' data-members="' . h($groupMembersCsv) . '"';
                $groupNodeAttrs .= ' data-is-virtual="' . ($isVirtualGroup ? '1' : '0') . '"';
                $groupNodeAttrs .= ' data-is-ungrouped="' . ($isUngrouped ? '1' : '0') . '"';
                $groupNodeAttrs .= ' tabindex="0" role="button" aria-label="Aksi untuk ' . h($groupName) . '"';
            }

            echo "    <li class=\"" . h($groupItemClass) . "\">\n";
            echo "      <article class=\"" . h($groupNodeClass) . "\"" . $groupNodeAttrs . ">\n";
            echo "        <div class=\"tree-v2-node-head tree-v2-node-head-groups-only\">\n";
            echo "          <div class=\"tree-v2-node-badges\"><span class=\"" . h($groupProgressBadgeClass) . "\">" . h($groupProgress) . "</span><span class=\"" . h($groupStatusBadgeClass) . "\">Aktif</span></div>\n";
            echo "        </div>\n";
            echo "        <div class=\"tree-v2-meta\">" . h($groupMetaLabel) . "</div>\n";
            echo "      </article>\n";

            if (count($groupMemberIds) > 0) {
                echo "      <ul class=\"tree-v2-children tree-v2-level-members\">\n";
                foreach ($groupMemberIds as $memberId) {
                    render_people_tree_v2($memberId, $peopleById, $childrenMap, $groupsByLeader, $membersById, $rootLeaderId, $stack, $depth + 1, $canManageTree);
                }
                echo "      </ul>\n";
            } else {
                echo "      <div class=\"tree-v2-empty\">Belum ada anggota</div>\n";
            }
            echo "    </li>\n";
        }
        echo "  </ul>\n";
    }

    echo "</li>\n";
}
