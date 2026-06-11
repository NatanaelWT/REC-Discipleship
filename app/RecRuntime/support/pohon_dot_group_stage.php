<?php

function pohon_dot_group_stage(array $group): string {
    return trim((string) ($group['stage'] ?? $group['current_stage'] ?? $group['start_stage'] ?? ''));
}
