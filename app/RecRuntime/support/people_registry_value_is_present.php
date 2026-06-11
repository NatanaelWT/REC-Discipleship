<?php

function people_registry_value_is_present($value): bool {
    if ($value === null) {
        return false;
    }
    if (is_string($value)) {
        return trim($value) !== '';
    }
    if (is_array($value)) {
        return count($value) > 0;
    }
    return true;
}
