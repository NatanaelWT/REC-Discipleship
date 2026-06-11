<?php

function worship_penatalayan_service_counts(array $schedule): array {
    $counts = [];
    foreach (worship_penatalayan_service_names($schedule) as $name) {
        $counts[$name] = (int) ($counts[$name] ?? 0) + 1;
    }

    if ($counts === []) {
        return [];
    }

    uksort($counts, static function (string $a, string $b) use ($counts): int {
        $countCompare = ((int) ($counts[$b] ?? 0)) <=> ((int) ($counts[$a] ?? 0));
        if ($countCompare !== 0) {
            return $countCompare;
        }
        return strcasecmp($a, $b);
    });

    return $counts;
}
