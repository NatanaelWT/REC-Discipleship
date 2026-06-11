<?php

function unified_msk_payload(array $source, string $defaultRecordId, string $defaultMemberId = '', array $fallback = []): array {
    $participantId = unified_pick_string($source, $fallback, ['participant_id', 'id'], '');
    if ($participantId === '') {
        if (strpos($defaultRecordId, 'msk_') === 0) {
            $participantId = $defaultRecordId;
        } else {
            $participantId = generate_id('msk');
        }
    }

    $memberId = unified_pick_string($source, $fallback, ['member_id'], '');
    if ($memberId === '' && $defaultMemberId !== '') {
        $memberId = $defaultMemberId;
    }

    $createdAt = normalize_iso_datetime_to_jakarta(unified_pick_string($source, $fallback, ['created_at'], ''));
    if ($createdAt === '') {
        $createdAt = now_iso();
    }
    $updatedAt = normalize_iso_datetime_to_jakarta(unified_pick_string($source, $fallback, ['updated_at'], ''));
    if ($updatedAt === '') {
        $updatedAt = $createdAt;
    }

    $mskMonthInput = unified_pick_string($source, $fallback, ['msk_month', 'msk_period'], '');
    if ($mskMonthInput === '') {
        $fallbackDate = normalize_ymd_date($createdAt);
        if ($fallbackDate !== '') {
            $mskMonthInput = substr($fallbackDate, 0, 7);
        }
    }
    $mskMonth = normalize_month_value($mskMonthInput !== '' ? $mskMonthInput : date('Y-m'));

    $sessionNumbersInput = $source['session_numbers'] ?? ($source['sessions'] ?? ($fallback['session_numbers'] ?? []));
    $sessionNumbers = normalize_msk_session_numbers($sessionNumbersInput);
    $notes = unified_pick_string($source, $fallback, ['notes'], '');
    $completedAt = unified_pick_string($source, $fallback, ['completed_at'], '');
    if (count($sessionNumbers) === 12 && $completedAt === '') {
        $completedAt = $updatedAt;
    }
    $journeyBridgeStatus = normalize_journey_bridge_status(unified_pick_string($source, $fallback, ['journey_bridge_status'], 'belum'));
    $status = normalize_msk_participant_status(unified_pick_string($source, $fallback, ['status'], 'active'));

    return [
        'is_participant' => true,
        'participant_id' => $participantId,
        'member_id' => $memberId,
        'msk_month' => $mskMonth,
        'session_numbers' => $sessionNumbers,
        'notes' => $notes,
        'completed_at' => $completedAt,
        'journey_bridge_status' => $journeyBridgeStatus,
        'status' => $status,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ];
}
