<?php

function pohon_dot_quote(string $value): string {
    return '"' . strtr($value, [
        '\\' => '\\\\',
        '"' => '\\"',
        "\r" => '',
        "\n" => '\\n',
    ]) . '"';
}
