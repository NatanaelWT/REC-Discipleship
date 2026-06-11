<?php

function import_xlsx_shared_string_text(\SimpleXMLElement $si): string {
    if (isset($si->t)) {
        return (string) $si->t;
    }
    $text = '';
    if (isset($si->r)) {
        foreach ($si->r as $run) {
            $text .= (string) ($run->t ?? '');
        }
    }
    return $text;
}
