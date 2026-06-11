<?php

function detect_preferred_json_eol($existingJson): string {
    if (is_string($existingJson) && strpos($existingJson, "\r\n") !== false) {
        return "\r\n";
    }
    return "\n";
}
