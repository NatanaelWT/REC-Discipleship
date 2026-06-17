<?php

function update_roles_based_on_children(array &$people, string $rootLeaderId): bool {
    $childCount = [];
    foreach ($people as $p) {
        $parentIds = get_parent_ids($p);
        foreach ($parentIds as $pid) {
            if ($pid === '') {
                continue;
            }
            $childCount[$pid] = ($childCount[$pid] ?? 0) + 1;
        }
    }

    $changed = false;
    foreach ($people as &$p) {
        $pid = $p['id'] ?? '';
        if ($pid === '') {
            continue;
        }
        if ($pid === $rootLeaderId) {
            if (($p['role'] ?? '') !== 'Leader') {
                $p['role'] = 'Leader';
                $changed = true;
            }
            continue;
        }
        $role = ($childCount[$pid] ?? 0) > 0 ? 'Pemimpin' : 'Anggota';
        if (($p['role'] ?? '') !== $role) {
            $p['role'] = $role;
            $changed = true;
        }
    }
    unset($p);

    return $changed;
}
