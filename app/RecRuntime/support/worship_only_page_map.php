<?php

function worship_only_page_map(): array {
    static $pages = null;
    if ($pages === null) {
        $pages = worship_page_map();
        foreach ([
            'public_links',
            'secure_file',
            'settings',
            'login',
        ] as $page) {
            $pages[$page] = true;
        }
    }
    return $pages;
}
