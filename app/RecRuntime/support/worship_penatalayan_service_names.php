<?php

function worship_penatalayan_service_names(array $schedule): array {
    $serviceNames = [];
    $rows = is_array($schedule['rows'] ?? null) ? $schedule['rows'] : [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $roleLabel = strtolower(trim((string) ($row['role'] ?? '')));
        if ($roleLabel === '' || $roleLabel === 'jadwal latihan') {
            continue;
        }
        $assignments = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
        foreach ($assignments as $cellValueRaw) {
            $cellValue = trim((string) $cellValueRaw);
            if ($cellValue === '') {
                continue;
            }
            $nameLines = preg_split("/\r\n?|\n/", $cellValue) ?: [];
            foreach ($nameLines as $nameRaw) {
                $name = trim((string) $nameRaw);
                if ($name === '') {
                    continue;
                }
                $serviceNames[] = $name;
            }
        }
    }

    return $serviceNames;
}
