<?php

function import_is_blank_row(array $row): bool {
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }
    return true;
}
