<?php

function dgv2_is_active_row(array $row): bool {
    $status = strtolower(trim((string) ($row['status'] ?? 'active')));
    if ($status === 'inactive' || $status === 'archived' || $status === 'closed' || $status === 'completed') {
        return false;
    }
    $endedAt = trim((string) ($row['end_at'] ?? $row['ended_at'] ?? ''));
    return $endedAt === '';
}
