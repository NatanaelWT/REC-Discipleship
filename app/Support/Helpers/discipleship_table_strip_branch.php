<?php

function discipleship_table_strip_branch($record): array {
    if (!is_array($record)) {
        return [];
    }
    unset($record['cabang']);
    return $record;
}
