<?php

function normalize_worship_penatalayan_rows($rows, int $weekCount): array {
    $normalizedRows = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $role = trim((string) ($row['role'] ?? ''));
            if ($role === '') {
                continue;
            }
            $assignmentsRaw = $row['assignments'] ?? [];
            if (!is_array($assignmentsRaw)) {
                $assignmentsRaw = [];
            }
            $assignments = [];
            for ($weekIndex = 0; $weekIndex < $weekCount; $weekIndex++) {
                $cellValue = preg_replace("/\r\n?/", "\n", (string) ($assignmentsRaw[$weekIndex] ?? ''));
                $assignments[] = trim($cellValue ?? '');
            }
            $normalizedRow = [
                'role' => $role,
                'assignments' => $assignments,
            ];
            $normalizedRows[] = $normalizedRow;
        }
    }

    // Ensure default roles (in the configured order) are present. Preserve any existing custom roles.
    $existingMap = [];
    foreach ($normalizedRows as $r) {
        $existingMap[strtolower((string) ($r['role'] ?? ''))] = $r;
    }

    $finalRows = [];
    $seenDefaultRoles = [];
    foreach (worship_penatalayan_default_roles() as $defaultRole) {
        $key = strtolower($defaultRole);
        if (isset($seenDefaultRoles[$key])) {
            continue;
        }
        $seenDefaultRoles[$key] = true;
        if (isset($existingMap[$key])) {
            $finalRows[] = $existingMap[$key];
            unset($existingMap[$key]);
        } else {
            $finalRows[] = [
                'role' => $defaultRole,
                'assignments' => array_fill(0, max(0, $weekCount), ''),
            ];
        }
    }
    foreach ($normalizedRows as $row) {
        $key = strtolower((string) ($row['role'] ?? ''));
        if (isset($seenDefaultRoles[$key])) {
            continue;
        }
        $finalRows[] = $row;
    }

    return $finalRows;
}
