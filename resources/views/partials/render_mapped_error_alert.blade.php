<?php

function render_mapped_error_alert(string $errorCode, array $errorMessages, string $tone = 'danger'): bool {
    if ($errorCode === '' || !isset($errorMessages[$errorCode])) {
        return false;
    }
    render_alert($tone, (string) $errorMessages[$errorCode]);
    return true;
}
