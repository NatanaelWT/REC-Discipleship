<?php

function dgv2_find_identity(array $identityById, string $memberId): array {
    if ($memberId !== '' && isset($identityById[$memberId]) && is_array($identityById[$memberId])) {
        return $identityById[$memberId];
    }
    return [
        'id' => $memberId,
        'full_name' => '',
        'whatsapp' => '',
        'gender' => '',
        'completed_msk' => false,
    ];
}
