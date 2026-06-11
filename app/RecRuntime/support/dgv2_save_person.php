<?php

function dgv2_save_person(array &$model, array $payload, array $members, array $mskClasses): array {
    $id = trim((string) ($payload['id'] ?? ''));
    if ($id !== '') {
        return dgv2_save_person_single($model, $payload, $members, $mskClasses);
    }

    $memberIds = dgv2_payload_member_ids($payload);
    if (count($memberIds) === 0) {
        return dgv2_save_person_external($model, $payload);
    }
    if (count($memberIds) === 1) {
        $singlePayload = $payload;
        $singlePayload['member_id'] = $memberIds[0];
        unset($singlePayload['member_ids']);
        return dgv2_save_person_single($model, $singlePayload, $members, $mskClasses);
    }

    $createdPersonIds = [];
    $firstError = '';
    $skippedOnlyExists = true;
    foreach ($memberIds as $memberId) {
        $singlePayload = $payload;
        $singlePayload['member_id'] = $memberId;
        unset($singlePayload['member_ids']);
        $result = dgv2_save_person_single($model, $singlePayload, $members, $mskClasses);
        if (!empty($result['ok'])) {
            $createdPersonIds[] = trim((string) ($result['person_id'] ?? ''));
            continue;
        }
        $errorCode = trim((string) ($result['error'] ?? 'save_failed'));
        if ($errorCode !== 'member_exists') {
            $skippedOnlyExists = false;
        }
        if ($firstError === '') {
            $firstError = $errorCode;
        }
    }

    if (count($createdPersonIds) > 0) {
        return [
            'ok' => true,
            'person_ids' => array_values(array_filter($createdPersonIds)),
        ];
    }

    if ($skippedOnlyExists) {
        return ['ok' => false, 'error' => 'member_exists'];
    }

    return ['ok' => false, 'error' => ($firstError !== '' ? $firstError : 'save_failed')];
}
