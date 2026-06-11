<?php

function dgv2_load_or_migrate(string $branch, array $legacyPeople, array $legacyGroups, array $legacyReports, array $members, array $mskClasses): array {
    if (!dgv2_model_exists($branch)) {
        $model = dgv2_migrate_from_legacy($branch, $legacyPeople, $legacyGroups, $legacyReports, $members, $mskClasses);
        dgv2_write_model($branch, $model);
        return $model;
    }
    return dgv2_normalize_model(dgv2_read_model($branch));
}
