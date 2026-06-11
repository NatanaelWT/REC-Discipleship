<?php

function append_body_classes(array &$bodyClasses, string $classes): void {
    if ($classes === '') {
        return;
    }
    $extraClasses = preg_split('/\s+/', trim($classes));
    if (!is_array($extraClasses)) {
        return;
    }
    foreach ($extraClasses as $extraClass) {
        $extraClass = trim((string) $extraClass);
        if ($extraClass === '') {
            continue;
        }
        $bodyClasses[] = $extraClass;
    }
}
