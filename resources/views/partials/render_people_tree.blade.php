<?php

function render_people_tree(
    string $personId,
    array $peopleById,
    array $childrenMap,
    array $groupsByLeader,
    array $leaderPalettes,
    array $peopleSorted,
    string $rootLeaderId,
    string $editId = '',
    array $stack = [],
    bool $renderChildren = true
): void {
    if ($personId === '' || !isset($peopleById[$personId]) || in_array($personId, $stack, true)) {
        return;
    }
    $canManageTree = !is_effective_central_discipleship_readonly();
    $stack[] = $personId;
    $person = $peopleById[$personId];
    $role = trim((string) ($person['role'] ?? ''));
    if ($role === '') {
        $role = 'Anggota';
    }
    $isRoot = $personId === $rootLeaderId;
    $attrName = str_replace(["\r", "\n"], ' ', (string) ($person['name'] ?? ''));
    $attrPhone = str_replace(["\r", "\n"], ' ', (string) ($person['phone'] ?? ''));
    $attrNotes = str_replace(["\r", "\n"], ' ', (string) ($person['notes'] ?? ''));
    $attrKampus = str_replace(["\r", "\n"], ' ', (string) ($person['kampus'] ?? ''));
    $attrJurusan = str_replace(["\r", "\n"], ' ', (string) ($person['jurusan'] ?? ''));
    $attrPekerjaan = str_replace(["\r", "\n"], ' ', (string) ($person['pekerjaan'] ?? ''));
    $attrMemberId = trim((string) ($person['member_id'] ?? ''));
    $leaderIds = get_parent_ids($person);
    $leader1 = $leaderIds[0] ?? '';
    $nodeClassParts = ['tree-node'];
    if ($editId !== '' && $editId === $personId) {
        $nodeClassParts[] = 'is-selected';
    }
    if ($isRoot) {
        $nodeClassParts[] = 'is-root';
    }
    $nodeClass = implode(' ', $nodeClassParts);

    $children = $childrenMap[$personId] ?? [];
    $leaderGroups = $groupsByLeader[$personId] ?? [];
    usort($leaderGroups, function ($a, $b) {
        $aTime = (string) ($a['created_at'] ?? '');
        $bTime = (string) ($b['created_at'] ?? '');
        if ($aTime !== $bTime) {
            return strcmp($aTime, $bTime);
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });
    $hasChildren = count($children) > 0;
    $hasGroups = count($leaderGroups) > 0;
    $hasDescendants = $renderChildren && ($hasChildren || $hasGroups);
    $rowClassParts = ['tree-row'];
    if ($hasDescendants) {
        $rowClassParts[] = 'has-children';
    }
    if ($hasGroups && !$isRoot) {
        $rowClassParts[] = 'is-group-leader';
    }
    $rowClass = implode(' ', $rowClassParts);
    echo "<li class=\"" . h($rowClass) . "\">\n";
    echo "  <div class=\"" . h($nodeClass) . "\">\n";
    echo "    <div class=\"tree-title\">" . h($person['name'] ?? '') . "</div>\n";
    echo "    <div class=\"tree-meta\">" . h($role) . "</div>\n";
    echo "    <div class=\"tree-actions\">\n";
    if ($isRoot) {
        echo "      <span class=\"badge muted\">Leader Utama</span>\n";
    } else {
        if ($canManageTree) {
            echo "      <button class=\"btn tiny secondary icon-btn\" type=\"button\" data-group-open=\"add\" data-leader-id=\"" . h($personId) . "\" data-leader-name=\"" . h($attrName) . "\" aria-label=\"Tambah Kelompok\" title=\"Tambah Kelompok\">" . icon_svg('plus') . "</button>\n";
            echo "      <button class=\"btn tiny icon-btn\" type=\"button\" data-modal-open=\"edit\" data-person-id=\"" . h($personId) . "\" data-member-id=\"" . h($attrMemberId) . "\" data-name=\"" . h($attrName) . "\" data-phone=\"" . h($attrPhone) . "\" data-notes=\"" . h($attrNotes) . "\" data-kampus=\"" . h($attrKampus) . "\" data-jurusan=\"" . h($attrJurusan) . "\" data-pekerjaan=\"" . h($attrPekerjaan) . "\" data-leader1-id=\"" . h($leader1) . "\" data-is-root=\"" . ($isRoot ? '1' : '0') . "\" aria-label=\"Edit\" title=\"Edit\">" . icon_svg('edit') . "</button>\n";
            echo "      <form method=\"post\" class=\"inline\" onsubmit=\"return confirm('Hapus orang ini?');\">\n";
            echo "        <input type=\"hidden\" name=\"action\" value=\"delete_person\">\n";
            echo "        <input type=\"hidden\" name=\"id\" value=\"" . h($personId) . "\">\n";
            echo "        <button class=\"btn tiny danger icon-btn\" type=\"submit\" aria-label=\"Hapus\" title=\"Hapus\">" . icon_svg('trash') . "</button>\n";
            echo "      </form>\n";
        }
    }
    echo "    </div>\n";
    echo "  </div>\n";

    if ($hasDescendants) {
        usort($children, function ($a, $b) {
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
        $assigned = [];
        foreach ($leaderGroups as $grp) {
            $memberIds = $grp['member_ids'] ?? [];
            if (!is_array($memberIds)) {
                $memberIds = [];
            }
            foreach ($memberIds as $mid) {
                $assigned[(string) $mid] = true;
            }
        }
        $ungrouped = [];
        foreach ($children as $child) {
            $cid = (string) ($child['id'] ?? '');
            if ($cid === '') {
                continue;
            }
            if (!isset($assigned[$cid])) {
                $ungrouped[] = $child;
            }
        }

        echo "  <ul class=\"group-list\">\n";
        foreach ($leaderGroups as $grp) {
            $memberIds = $grp['member_ids'] ?? [];
            if (!is_array($memberIds)) {
                $memberIds = [];
            }
            $isVirtualGroup = !empty($grp['virtual']);
            $assistantId = (string) ($grp['assistant_id'] ?? '');
            $progress = trim((string) ($grp['progress'] ?? ''));
            $groupNotes = str_replace(["\r", "\n"], ' ', (string) ($grp['notes'] ?? ''));
            $assistantName = $assistantId !== '' ? person_label($peopleById, $assistantId, '') : '';
            $memberPeople = [];
            foreach ($memberIds as $mid) {
                if (isset($peopleById[$mid])) {
                    $memberPeople[] = $peopleById[$mid];
                }
            }
            usort($memberPeople, function ($a, $b) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });
        echo "    <li class=\"group-node\">\n";
            $membersCsv = implode(',', array_map('strval', $memberIds));
            $palette = $leaderPalettes[$personId] ?? [
                'bg1' => '#fef3c7',
                'bg2' => '#fef9e7',
                'border' => 'rgba(245, 158, 11, 0.35)',
                'accent' => '#f59e0b',
                'accent2' => '#fbbf24',
            ];
            $style = " style=\"--group-bg1: " . h($palette['bg1']) . "; --group-bg2: " . h($palette['bg2']) . "; --group-border: " . h($palette['border']) . "; --group-accent: " . h($palette['accent']) . "; --group-accent-2: " . h($palette['accent2']) . ";\"";
            echo "      <div class=\"group-card\"" . $style . ">\n";
            echo "        <div class=\"group-title\">Kelompok</div>\n";
            if (!$isVirtualGroup) {
                $groupLeaderName = trim((string) ($grp['leader_name'] ?? ''));
                if ($groupLeaderName === '') {
                    $groupLeaderName = $attrName;
                }
                if ($groupLeaderName !== '') {
                    echo "        <div class=\"group-meta\">Dibina: " . h($groupLeaderName) . "</div>\n";
                }
            }
            if (!$isVirtualGroup && $assistantName !== '') {
                echo "        <div class=\"group-meta\">Pendamping: " . h($assistantName) . "</div>\n";
            }
            if (!$isVirtualGroup) {
                $progressLabel = $progress !== '' ? $progress : 'Belum ada';
                $progressPercent = 0;
                if (preg_match('/DG\\s*(\\d+)/i', $progress, $match)) {
                    $step = (int) $match[1];
                    if ($step >= 1 && $step <= 3) {
                        $progressPercent = (int) round($step * 100 / 3);
                    }
                }
                $progressClass = $progress !== '' ? 'group-progress' : 'group-progress muted';
                echo "        <div class=\"" . h($progressClass) . "\">\n";
                echo "          <div>Progress: " . h($progressLabel) . "</div>\n";
                echo "          <div class=\"progress-track\"><div class=\"progress-bar\" style=\"width:" . h((string) $progressPercent) . "%\"></div></div>\n";
                echo "        </div>\n";
            }
            echo "        <div class=\"group-actions\">\n";
            if ($canManageTree) {
                echo "          <button class=\"btn tiny ghost\" type=\"button\" data-modal-open=\"add\" data-parent-id=\"" . h($personId) . "\" data-parent-name=\"" . h($attrName) . "\" data-group-id=\"" . h($grp['id'] ?? '') . "\">Tambah Anggota</button>\n";
                if (!$isVirtualGroup) {
                    echo "          <button class=\"btn tiny icon-btn\" type=\"button\" data-group-open=\"edit\" data-group-id=\"" . h($grp['id'] ?? '') . "\" data-leader-id=\"" . h($personId) . "\" data-leader-name=\"" . h($attrName) . "\" data-assistant-id=\"" . h($assistantId) . "\" data-progress=\"" . h($progress) . "\" data-notes=\"" . h($groupNotes) . "\" data-members=\"" . h($membersCsv) . "\" aria-label=\"Edit Kelompok\" title=\"Edit Kelompok\">" . icon_svg('edit') . "</button>\n";
                    echo "          <form method=\"post\" class=\"inline\" onsubmit=\"return confirm('Hapus kelompok ini?');\">\n";
                    echo "            <input type=\"hidden\" name=\"action\" value=\"delete_group\">\n";
                    echo "            <input type=\"hidden\" name=\"id\" value=\"" . h($grp['id'] ?? '') . "\">\n";
                    echo "            <button class=\"btn tiny danger icon-btn\" type=\"submit\" aria-label=\"Hapus Kelompok\" title=\"Hapus Kelompok\">" . icon_svg('trash') . "</button>\n";
                    echo "          </form>\n";
                }
            }
            echo "        </div>\n";
            if (count($memberPeople) > 0) {
                echo "        <ul class=\"group-members\">\n";
                foreach ($memberPeople as $child) {
                    render_people_tree((string) ($child['id'] ?? ''), $peopleById, $childrenMap, $groupsByLeader, $leaderPalettes, $peopleSorted, $rootLeaderId, $editId, $stack, true);
                }
                echo "        </ul>\n";
            } else {
                echo "        <div class=\"group-meta muted\">Belum ada anggota</div>\n";
            }
            echo "      </div>\n";
            echo "    </li>\n";
        }

        if (count($ungrouped) > 0) {
            echo "    <li class=\"group-node is-ungrouped\">\n";
            echo "      <div class=\"group-card\">\n";
            echo "        <div class=\"group-title\">Tanpa Kelompok</div>\n";
            echo "        <div class=\"group-meta muted\">Anggota belum dikelompokkan</div>\n";
            echo "        <ul class=\"group-members\">\n";
            foreach ($ungrouped as $child) {
                render_people_tree((string) ($child['id'] ?? ''), $peopleById, $childrenMap, $groupsByLeader, $leaderPalettes, $peopleSorted, $rootLeaderId, $editId, $stack, true);
            }
            echo "        </ul>\n";
            echo "      </div>\n";
            echo "    </li>\n";
        }
        echo "  </ul>\n";
    }

    echo "</li>\n";
}
