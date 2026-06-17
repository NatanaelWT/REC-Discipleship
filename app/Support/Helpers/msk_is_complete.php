<?php

function msk_is_complete(array $participant): bool {
    return count(normalize_msk_session_numbers($participant['session_numbers'] ?? [])) === 12;
}
