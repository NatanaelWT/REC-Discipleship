<?php

function append_branch_suffix(string $value, string $branchLabel): string {
    $value = trim($value);
    $branchLabel = trim($branchLabel);
    if ($branchLabel === '') {
        return $value;
    }
    if ($value === '') {
        return $branchLabel;
    }
    $suffix = ' (' . $branchLabel . ')';
    if (substr($value, -strlen($suffix)) === $suffix) {
        return $value;
    }
    return $value . $suffix;
}
