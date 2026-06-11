<?php

function render_sidebar_nav_link(string $label, string $href, bool $active, string $indent = '        '): void {
    $class = $active ? 'nav-item active' : 'nav-item';
    echo $indent . "<a class=\"" . h($class) . "\" href=\"" . h($href) . "\">" . h($label) . "</a>\n";
}
