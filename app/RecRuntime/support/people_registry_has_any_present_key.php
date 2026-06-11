<?php

function people_registry_has_any_present_key(array $record, array $keys): bool {
    foreach ($keys as $key) {
        if (array_key_exists($key, $record) && people_registry_value_is_present($record[$key])) {
            return true;
        }
    }
    return false;
}
