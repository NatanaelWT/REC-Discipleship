<?php

function discipleship_relationship_set_branch_value(string $branch, string $name, $data): bool {
    $kind = discipleship_relationship_kind_for_table($name);
    if ($kind === '') {
        return false;
    }

    $branch = normalize_public_branch_code($branch);
    $path = discipleship_table_path(DISCIPLESHIP_RELATIONSHIPS_DATA_NAME);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }

    flock($fp, LOCK_EX);
    rewind($fp);
    $raw = stream_get_contents($fp);
    $database = null;
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $database = $decoded;
        }
    }
    if (!is_array($database)) {
        $database = discipleship_table_default(DISCIPLESHIP_RELATIONSHIPS_DATA_NAME);
    }

    $database['schema_version'] = (int) ($database['schema_version'] ?? 1);
    $database['name'] = DISCIPLESHIP_RELATIONSHIPS_DATA_NAME;
    $branches = is_array($database['branches'] ?? null) ? $database['branches'] : [];
    if (!in_array($branch, $branches, true)) {
        $branches[] = $branch;
    }
    $branches = array_values(array_unique(array_filter(array_map(static function ($value) {
        $branch = strtolower(trim((string) $value));
        return is_known_public_branch_code($branch) ? normalize_public_branch_code($branch) : '';
    }, $branches))));
    sort($branches, SORT_STRING);
    $database['branches'] = $branches;

    $records = [];
    foreach ((is_array($database['records'] ?? null) ? $database['records'] : []) as $record) {
        if (!is_array($record)) {
            continue;
        }
        $sameBranch = discipleship_table_branch_from_record($record) === $branch;
        $sameKind = trim((string) ($record['relationship_kind'] ?? '')) === $kind;
        if ($sameBranch && $sameKind) {
            continue;
        }
        $records[] = $record;
    }

    if (!is_array($data)) {
        $data = [];
    }
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        unset($row['cabang'], $row['relationship_kind']);
        $records[] = array_merge([
            'cabang' => $branch,
            'relationship_kind' => $kind,
        ], $row);
    }

    $database['records'] = array_values($records);
    $database['updated_at'] = discipleship_table_now_iso();

    $json = json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
    if (is_string($raw) && $raw === $json) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    rewind($fp);
    ftruncate($fp, 0);
    $bytes = fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $bytes !== false;
}
