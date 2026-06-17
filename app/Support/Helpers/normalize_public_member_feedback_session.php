<?php

function normalize_public_member_feedback_session($value): int {
    $text = trim((string) $value);
    if ($text === '3' || $text === '12') {
        return (int) $text;
    }
    return 0;
}
