<?php

function unified_compact_profile_owned_payloads(array &$profile, ?array &$discipleshipPayload, ?array &$discipleshipPersonPayload): void {
    if (is_array($discipleshipPayload)) {
        unified_move_profile_field_from_payload($profile, $discipleshipPayload, 'name', 'full_name');
        unified_move_profile_field_from_payload($profile, $discipleshipPayload, 'phone', 'whatsapp');
    }
    if (is_array($discipleshipPersonPayload)) {
        unified_move_profile_field_from_payload($profile, $discipleshipPersonPayload, 'full_name', 'full_name');
        unified_move_profile_field_from_payload($profile, $discipleshipPersonPayload, 'phone', 'whatsapp');
        unified_move_profile_field_from_payload($profile, $discipleshipPersonPayload, 'gender', 'gender');
    }
}
