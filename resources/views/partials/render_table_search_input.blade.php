<?php

function render_table_search_input(
    string $filterId,
    string $placeholder,
    string $class = 'search',
    string $ariaLabel = '',
    string $indent = ''
): void {
    $attrs = 'class="' . h($class !== '' ? $class : 'search') . '" type="search" placeholder="' . h($placeholder) . '"';
    if ($ariaLabel !== '') {
        $attrs .= ' aria-label="' . h($ariaLabel) . '"';
    }
    $attrs .= ' data-filter="' . h($filterId) . '"';
    echo $indent . "<input " . $attrs . ">\n";
}
