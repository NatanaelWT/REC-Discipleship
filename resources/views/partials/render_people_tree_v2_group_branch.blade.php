<?php

function render_people_tree_v2_group_branch(
    array $groupBranch,
    array $peopleById,
    array $childrenMap,
    array $groupsByLeader,
    array $membersById,
    string $rootLeaderId,
    array $stack,
    int $depth,
    bool $canManageTree,
    string $leaderPersonId,
    string $leaderName
): void {
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
    $groupChildBranches = $groupBranch['child_groups'] ?? [];
    if (!is_array($groupChildBranches)) {
        $groupChildBranches = [];
    }
    $isUngrouped = !empty($groupBranch['is_ungrouped']);
    $isVirtualGroup = !empty($groupBranch['is_virtual']);
    $groupStatus = strtolower(trim((string) ($groupBranch['status'] ?? 'active')));
    $groupItemClass = 'tree-v2-item tree-v2-item-group' . ($isUngrouped ? ' is-ungrouped' : '');
    $groupProgressBadgeClass = $groupProgress !== '-' ? 'badge warning' : 'badge muted';
    $groupStatusLabel = $groupStatus === 'active' ? 'Aktif' : 'Selesai';
    $groupStatusBadgeClass = $groupStatus === 'active' ? 'badge tree-v2-status-badge is-active' : 'badge tree-v2-status-badge is-inactive';
    $groupMetaParts = [(string) count($groupMemberIds) . ' anggota'];
    if ($groupProgress !== '-') {
        $groupMetaParts[] = $groupProgress;
    }
    if ($groupAssistantName !== '') {
        $groupMetaParts[] = 'Pendamping: ' . $groupAssistantName;
    }
    $groupMetaLabel = implode(' â€¢ ', $groupMetaParts);

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
    $canGroupManage = $canManageTree && !$isUngrouped && $groupStatus === 'active';
    if ($canGroupManage) {
        $groupNodeClass .= ' is-actionable';
    }
    $groupMembersCsv = implode(',', array_map('strval', $groupMemberIds));
    $groupNodeAttrs = '';
    if ($canGroupManage) {
        $groupNodeAttrs .= ' data-tree-v2-node-action="group"';
        $groupNodeAttrs .= ' data-group-id="' . h($groupId) . '"';
        $groupNodeAttrs .= ' data-leader-id="' . h($leaderPersonId) . '"';
        $groupNodeAttrs .= ' data-leader-name="' . h($leaderName) . '"';
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
    echo "          <div class=\"tree-v2-node-badges\"><span class=\"" . h($groupProgressBadgeClass) . "\">" . h($groupProgress) . "</span><span class=\"" . h($groupStatusBadgeClass) . "\">" . h($groupStatusLabel) . "</span></div>\n";
    echo "        </div>\n";
    echo "        <div class=\"tree-v2-meta\">" . h($groupMetaLabel) . "</div>\n";
    echo "      </article>\n";

    if (count($groupMemberIds) > 0 || count($groupChildBranches) > 0) {
        echo "      <ul class=\"tree-v2-children tree-v2-level-members\">\n";
        foreach ($groupChildBranches as $childGroupBranch) {
            if (!is_array($childGroupBranch)) {
                continue;
            }
            render_people_tree_v2_group_branch($childGroupBranch, $peopleById, $childrenMap, $groupsByLeader, $membersById, $rootLeaderId, $stack, $depth + 1, $canManageTree, $leaderPersonId, $leaderName);
        }
        foreach ($groupMemberIds as $memberId) {
            render_people_tree_v2($memberId, $peopleById, $childrenMap, $groupsByLeader, $membersById, $rootLeaderId, $stack, $depth + 1, $canManageTree);
        }
        echo "      </ul>\n";
    } else {
        echo "      <div class=\"tree-v2-empty\">Belum ada anggota</div>\n";
    }
    echo "    </li>\n";
}
