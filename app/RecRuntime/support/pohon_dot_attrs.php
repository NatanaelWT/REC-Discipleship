<?php

function pohon_dot_attrs(array $attrs): string {
    $parts = [];
    foreach ($attrs as $key => $value) {
        $parts[] = $key . '=' . $value;
    }
    return implode(', ', $parts);
}
