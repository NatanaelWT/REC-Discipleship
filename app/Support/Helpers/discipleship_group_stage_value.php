<?php

function discipleship_group_stage_value(mixed $groupRow): string
{
    $value = null;
    foreach (['stage', 'progress', 'current_stage', 'start_stage'] as $key) {
        if (is_array($groupRow) && array_key_exists($key, $groupRow)) {
            $value = $groupRow[$key];
        } elseif (is_object($groupRow) && isset($groupRow->{$key})) {
            $value = $groupRow->{$key};
        } else {
            continue;
        }

        $stage = normalize_dg_progress_value((string) $value);
        if ($stage !== '') {
            return $stage;
        }
    }

    return '';
}
