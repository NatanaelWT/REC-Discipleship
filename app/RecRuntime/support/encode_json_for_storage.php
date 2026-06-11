<?php

function encode_json_for_storage($data, string $eol = "\n"): ?string {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return null;
    }
    if ($eol !== "\n") {
        $json = str_replace("\n", $eol, $json);
    }
    return $json;
}
