<?php

function sync_member_family_links(array &$members): bool {
    $changed = false;
    $membersById = index_by_id($members);

    foreach ($members as &$member) {
        $id = trim((string) ($member['id'] ?? ''));
        $familyIds = $member['family_ids'] ?? [];
        if (!is_array($familyIds)) {
            $familyIds = [];
            $changed = true;
        }
        $cleanFamilyIds = [];
        foreach ($familyIds as $familyId) {
            $familyId = trim((string) $familyId);
            if ($familyId === '' || $familyId === $id || !isset($membersById[$familyId])) {
                if ($familyId !== '') {
                    $changed = true;
                }
                continue;
            }
            $cleanFamilyIds[] = $familyId;
        }
        $cleanFamilyIds = array_values(array_unique($cleanFamilyIds));
        if (($member['family_ids'] ?? null) !== $cleanFamilyIds) {
            $member['family_ids'] = $cleanFamilyIds;
            $changed = true;
        }
    }
    unset($member);

    $indexById = [];
    $orderedIds = [];
    foreach ($members as $index => $member) {
        $memberId = trim((string) ($member['id'] ?? ''));
        if ($memberId !== '') {
            $indexById[$memberId] = $index;
            $orderedIds[] = $memberId;
        }
    }

    $adjacency = [];
    foreach ($orderedIds as $memberId) {
        $adjacency[$memberId] = [];
    }

    foreach ($members as $member) {
        $memberId = trim((string) ($member['id'] ?? ''));
        if ($memberId === '' || !isset($adjacency[$memberId])) {
            continue;
        }
        $familyIds = $member['family_ids'] ?? [];
        if (!is_array($familyIds)) {
            continue;
        }
        foreach ($familyIds as $familyId) {
            $familyId = trim((string) $familyId);
            if ($familyId === '' || !isset($adjacency[$familyId])) {
                continue;
            }
            $adjacency[$memberId][$familyId] = true;
            $adjacency[$familyId][$memberId] = true;
        }
    }

    $visited = [];
    foreach ($orderedIds as $startId) {
        if (isset($visited[$startId])) {
            continue;
        }

        $queue = [$startId];
        $component = [];
        while (count($queue) > 0) {
            $currentId = array_shift($queue);
            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;
            $component[] = $currentId;

            $neighbors = array_keys($adjacency[$currentId] ?? []);
            foreach ($neighbors as $neighborId) {
                if (!isset($visited[$neighborId])) {
                    $queue[] = $neighborId;
                }
            }
        }

        $componentMap = array_fill_keys($component, true);
        $componentOrdered = [];
        foreach ($orderedIds as $candidateId) {
            if (isset($componentMap[$candidateId])) {
                $componentOrdered[] = $candidateId;
            }
        }

        foreach ($componentOrdered as $memberId) {
            if (!isset($indexById[$memberId])) {
                continue;
            }
            $targetFamilyIds = [];
            foreach ($componentOrdered as $relatedId) {
                if ($relatedId === $memberId) {
                    continue;
                }
                $targetFamilyIds[] = $relatedId;
            }
            $targetIndex = $indexById[$memberId];
            $currentFamilyIds = $members[$targetIndex]['family_ids'] ?? [];
            if (!is_array($currentFamilyIds)) {
                $currentFamilyIds = [];
            }
            if ($currentFamilyIds !== $targetFamilyIds) {
                $members[$targetIndex]['family_ids'] = $targetFamilyIds;
                $changed = true;
            }
        }
    }

    return $changed;
}
