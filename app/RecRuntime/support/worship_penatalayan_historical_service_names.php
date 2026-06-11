<?php

function worship_penatalayan_historical_service_names(array $schedules): array {
    $namesByLabel = [];
    foreach ($schedules as $schedule) {
        if (!is_array($schedule)) {
            continue;
        }
        foreach (worship_penatalayan_service_names($schedule) as $name) {
            $namesByLabel[$name] = true;
        }
    }

    $names = array_keys($namesByLabel);
    usort($names, static function (string $a, string $b): int {
        return strcasecmp($a, $b);
    });

    return $names;
}
