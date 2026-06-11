<?php

function people_registry_record_has_nested_payload(array $record): bool {
    return is_array($record['profile'] ?? null)
        || is_array($record['member'] ?? null)
        || is_array($record['msk'] ?? null)
        || is_array($record['discipleship'] ?? null)
        || is_array($record['discipleship_person'] ?? null)
        || is_array($record['person'] ?? null)
        || is_array($record['discipleship_v2_person'] ?? null);
}
