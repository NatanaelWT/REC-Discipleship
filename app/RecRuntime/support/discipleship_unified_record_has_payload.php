<?php

function discipleship_unified_record_has_payload(array $record): bool {
    return is_array($record['member'] ?? null)
        || is_array($record['msk'] ?? null)
        || is_array($record['discipleship'] ?? null)
        || is_array($record['discipleship_person'] ?? null);
}
