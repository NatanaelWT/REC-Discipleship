<?php

function discipleship_unified_record_aliases(array $record): array {
    $aliases = [];
    $recordId = trim((string) ($record['id'] ?? ''));
    if ($recordId !== '') {
        $aliases[] = $recordId;
    }
    if (is_array($record['member'] ?? null)) {
        $memberId = trim((string) ($record['member']['member_id'] ?? ''));
        if ($memberId !== '') {
            $aliases[] = $memberId;
        }
    }
    if (is_array($record['msk'] ?? null)) {
        foreach (['participant_id', 'member_id'] as $field) {
            $value = trim((string) ($record['msk'][$field] ?? ''));
            if ($value !== '') {
                $aliases[] = $value;
            }
        }
    }
    if (is_array($record['discipleship'] ?? null)) {
        foreach (['person_id', 'member_id'] as $field) {
            $value = trim((string) ($record['discipleship'][$field] ?? ''));
            if ($value !== '') {
                $aliases[] = $value;
            }
        }
    }
    if (is_array($record['discipleship_person'] ?? null)) {
        foreach (['person_id', 'member_id'] as $field) {
            $value = trim((string) ($record['discipleship_person'][$field] ?? ''));
            if ($value !== '') {
                $aliases[] = $value;
            }
        }
    }
    return array_values(array_unique($aliases));
}
