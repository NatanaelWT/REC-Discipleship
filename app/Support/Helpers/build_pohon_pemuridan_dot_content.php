<?php

function build_pohon_pemuridan_dot_content(string $branch, array $model): string {
    $branch = normalize_public_branch_code($branch);

    $peopleRows = is_array($model['discipleship_persons'] ?? null) ? array_values($model['discipleship_persons']) : [];
    $relationRows = is_array($model['discipleship_relations'] ?? null) ? array_values($model['discipleship_relations']) : [];
    $groupRows = is_array($model['discipleship_groups'] ?? null) ? array_values($model['discipleship_groups']) : [];
    $membershipRows = is_array($model['group_memberships'] ?? null) ? array_values($model['group_memberships']) : [];
    $leadershipRows = is_array($model['group_leaderships'] ?? null) ? array_values($model['group_leaderships']) : [];

    $peopleById = [];
    foreach ($peopleRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $personId = trim((string) ($row['id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $peopleById[$personId] = [
            'id' => $personId,
            'name' => trim((string) ($row['full_name'] ?? $row['name'] ?? $personId)),
            'member_id' => trim((string) ($row['member_id'] ?? '')),
            'status' => strtolower(trim((string) ($row['status'] ?? 'active'))),
        ];
    }

    $groupsById = [];
    foreach ($groupRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $groupId = trim((string) ($row['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $groupsById[$groupId] = [
            'id' => $groupId,
            'status' => strtolower(trim((string) ($row['status'] ?? 'active'))),
            'stage' => trim((string) ($row['current_stage'] ?? $row['start_stage'] ?? '')),
        ];
    }

    $activeRelations = array_values(array_filter($relationRows, static fn ($row): bool => is_array($row) && dgv2_is_current_period($row)));
    $activeMemberships = array_values(array_filter($membershipRows, static fn ($row): bool => is_array($row) && dgv2_is_current_period($row)));
    $activeLeaderships = array_values(array_filter($leadershipRows, static fn ($row): bool => is_array($row) && dgv2_is_current_period($row)));

    $relevantPersonIds = [];
    $activeGroupIds = [];
    $childrenByMentor = [];
    $incomingMentorsByDisciple = [];
    $leadershipsByPerson = [];
    $leadershipsByGroup = [];
    $membershipsByPerson = [];
    $membershipsByGroup = [];
    $virtualRootChildren = [];

    foreach ($activeRelations as $row) {
        $mentorId = trim((string) ($row['mentor_person_id'] ?? ''));
        $discipleId = trim((string) ($row['disciple_person_id'] ?? ''));
        if ($discipleId === '') {
            continue;
        }

        $relevantPersonIds[$discipleId] = true;
        if ($mentorId !== '' && $mentorId !== 'virtual_injil') {
            $relevantPersonIds[$mentorId] = true;
            $childrenByMentor[$mentorId][] = $discipleId;
        } elseif ($mentorId === 'virtual_injil') {
            $virtualRootChildren[$discipleId] = true;
        }

        $incomingMentorsByDisciple[$discipleId][] = $mentorId;
    }

    foreach ($activeLeaderships as $row) {
        $personId = trim((string) ($row['leader_person_id'] ?? ''));
        $groupId = trim((string) ($row['group_id'] ?? ''));
        if ($personId !== '') {
            $relevantPersonIds[$personId] = true;
            $leadershipsByPerson[$personId][] = $row;
        }
        if ($groupId !== '') {
            $activeGroupIds[$groupId] = true;
            $leadershipsByGroup[$groupId][] = $row;
        }
    }

    foreach ($activeMemberships as $row) {
        $personId = trim((string) ($row['person_id'] ?? ''));
        $groupId = trim((string) ($row['group_id'] ?? ''));
        if ($personId !== '') {
            $relevantPersonIds[$personId] = true;
            $membershipsByPerson[$personId][] = $row;
        }
        if ($groupId !== '') {
            $activeGroupIds[$groupId] = true;
            $membershipsByGroup[$groupId][] = $row;
        }
    }

    $personIds = array_keys($relevantPersonIds);
    usort(
        $personIds,
        static fn (string $a, string $b): int => strcasecmp(pohon_dot_person_name($peopleById, $a), pohon_dot_person_name($peopleById, $b))
    );

    $groupIds = array_keys($activeGroupIds);
    usort(
        $groupIds,
        static function (string $a, string $b) use ($groupsById, $leadershipsByGroup, $peopleById): int {
            $leaderA = pohon_dot_primary_group_leader_name($a, $leadershipsByGroup, $peopleById);
            $leaderB = pohon_dot_primary_group_leader_name($b, $leadershipsByGroup, $peopleById);
            $cmp = strcasecmp($leaderA, $leaderB);
            if ($cmp !== 0) {
                return $cmp;
            }

            $stageA = pohon_dot_group_stage($groupsById[$a] ?? []);
            $stageB = pohon_dot_group_stage($groupsById[$b] ?? []);
            $cmp = strcasecmp($stageA, $stageB);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a, $b);
        }
    );

    $rootAttachmentIds = [];
    foreach ($personIds as $personId) {
        $hasIncomingMentor = isset($incomingMentorsByDisciple[$personId]);
        $hasIncomingGroup = !empty($membershipsByPerson[$personId]);
        if ($hasIncomingMentor || $hasIncomingGroup) {
            continue;
        }
        $rootAttachmentIds[] = $personId;
    }

    $lines = [];
    $graphName = 'discipleship_' . preg_replace('/[^a-z0-9_]+/', '_', strtolower($branch));
    $lines[] = "digraph {$graphName} {";
    $lines[] = '  graph [rankdir=TB, splines=true, overlap=false, pad="0.4", nodesep="0.45", ranksep="0.7", labelloc="t", label=' . pohon_dot_quote('Pohon Pemuridan Cabang ' . strtoupper($branch) . "\nRelasi mentor aktif + kelompok DG aktif") . '];';
    $lines[] = '  node [shape=box, style="rounded,filled", fontname="Helvetica", fontsize=10, color="#334155", penwidth=1.0, margin="0.14,0.10"];';
    $lines[] = '  edge [fontname="Helvetica", fontsize=9, color="#475467", arrowsize=0.7, penwidth=1.2];';
    $lines[] = '';
    $lines[] = '  // Generated by scripts/export_pohon_pemuridan_dot.php';
    $lines[] = '  // Active persons: ' . count($personIds) . ', active mentor edges: ' . count($activeRelations) . ', active groups: ' . count($groupIds);
    $lines[] = '';
    $lines[] = '  "branch_root_' . $branch . '" [label=' . pohon_dot_quote('Cabang ' . strtoupper($branch)) . ', shape=oval, fillcolor="#0f766e", fontcolor="white", color="#0f766e", penwidth=1.4];';
    $lines[] = '  "virtual_injil" [label=' . pohon_dot_quote('Injil') . ', shape=oval, fillcolor="#dc2626", fontcolor="white", color="#dc2626", penwidth=1.4];';
    $lines[] = '  "branch_root_' . $branch . '" -> "virtual_injil" [color="#dc2626", penwidth=1.5];';
    $lines[] = '';

    foreach ($personIds as $personId) {
        $hasChildren = !empty($childrenByMentor[$personId]);
        $hasLeadership = !empty($leadershipsByPerson[$personId]);
        $hasMembership = !empty($membershipsByPerson[$personId]);
        $hasVirtualRoot = isset($virtualRootChildren[$personId]);

        $fillColor = '#f8fafc';
        if ($hasChildren && $hasLeadership) {
            $fillColor = '#fde68a';
        } elseif ($hasChildren) {
            $fillColor = '#fef3c7';
        } elseif ($hasLeadership) {
            $fillColor = '#dbeafe';
        } elseif ($hasMembership) {
            $fillColor = '#ecfdf3';
        }

        $attrs = [
            'label' => pohon_dot_quote(pohon_dot_person_label($personId, $peopleById, $childrenByMentor, $incomingMentorsByDisciple, $leadershipsByPerson, $membershipsByPerson, $groupsById)),
            'fillcolor' => pohon_dot_quote($fillColor),
        ];
        if ($hasVirtualRoot) {
            $attrs['peripheries'] = '2';
        }
        $lines[] = '  ' . pohon_dot_id($personId) . ' [' . pohon_dot_attrs($attrs) . '];';
    }

    $lines[] = '';

    foreach ($groupIds as $groupId) {
        $lines[] = '  ' . pohon_dot_id(pohon_dot_group_node_id($groupId)) . ' [shape=folder, label=' . pohon_dot_quote(pohon_dot_group_label($groupId, $groupsById, $leadershipsByGroup, $membershipsByGroup, $peopleById)) . ', fillcolor="#e0f2fe", color="#2563eb", penwidth=1.1];';
    }

    $lines[] = '';

    $mentorEdges = [];
    foreach ($activeRelations as $row) {
        $mentorId = trim((string) ($row['mentor_person_id'] ?? ''));
        $discipleId = trim((string) ($row['disciple_person_id'] ?? ''));
        if ($discipleId === '') {
            continue;
        }

        $fromId = $mentorId === 'virtual_injil' ? 'virtual_injil' : $mentorId;
        if ($fromId === '') {
            continue;
        }
        $mentorEdges[] = [
            'from' => $fromId,
            'to' => $discipleId,
            'color' => $mentorId === 'virtual_injil' ? '#dc2626' : '#475467',
            'style' => 'solid',
            'penwidth' => $mentorId === 'virtual_injil' ? '1.6' : '1.2',
        ];
    }

    usort(
        $mentorEdges,
        static fn (array $a, array $b): int => strcmp($a['from'] . '|' . $a['to'], $b['from'] . '|' . $b['to'])
    );

    foreach ($mentorEdges as $edge) {
        $lines[] = '  ' . pohon_dot_id($edge['from']) . ' -> ' . pohon_dot_id($edge['to']) . ' [' . pohon_dot_attrs([
            'color' => pohon_dot_quote($edge['color']),
            'style' => pohon_dot_quote($edge['style']),
            'penwidth' => $edge['penwidth'],
        ]) . '];';
    }

    $lines[] = '';

    foreach ($activeLeaderships as $row) {
        $leaderId = trim((string) ($row['leader_person_id'] ?? ''));
        $groupId = trim((string) ($row['group_id'] ?? ''));
        if ($leaderId === '' || $groupId === '') {
            continue;
        }
        $lines[] = '  ' . pohon_dot_id($leaderId) . ' -> ' . pohon_dot_id(pohon_dot_group_node_id($groupId)) . ' [' . pohon_dot_attrs([
            'color' => pohon_dot_quote('#2563eb'),
            'penwidth' => '1.4',
        ]) . '];';
    }

    $lines[] = '';

    foreach ($activeMemberships as $row) {
        $personId = trim((string) ($row['person_id'] ?? ''));
        $groupId = trim((string) ($row['group_id'] ?? ''));
        if ($personId === '' || $groupId === '') {
            continue;
        }
        $lines[] = '  ' . pohon_dot_id(pohon_dot_group_node_id($groupId)) . ' -> ' . pohon_dot_id($personId) . ' [' . pohon_dot_attrs([
            'color' => pohon_dot_quote('#60a5fa'),
            'style' => pohon_dot_quote('dashed'),
            'arrowhead' => pohon_dot_quote('none'),
        ]) . '];';
    }

    $lines[] = '';

    foreach ($rootAttachmentIds as $personId) {
        if (isset($virtualRootChildren[$personId])) {
            continue;
        }
        $lines[] = '  "branch_root_' . $branch . '" -> ' . pohon_dot_id($personId) . ' [' . pohon_dot_attrs([
            'color' => pohon_dot_quote('#94a3b8'),
            'style' => pohon_dot_quote('dotted'),
            'label' => pohon_dot_quote('top level'),
        ]) . '];';
    }

    $lines[] = '}';
    return implode("\n", $lines) . "\n";
}
