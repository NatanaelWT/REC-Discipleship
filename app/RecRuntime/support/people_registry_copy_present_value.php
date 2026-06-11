<?php

function people_registry_copy_present_value(array &$target, string $targetKey, $value): void {
    if (!people_registry_value_is_present($value)) {
        return;
    }
    $target[$targetKey] = $value;
}
