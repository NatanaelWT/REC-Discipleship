<?php

function worship_page_map(): array {
    static $pages = null;
    if ($pages === null) {
        $pages = [
            'worship_penatalayan' => true,
            'worship_penatalayan_image' => true,
        ];
    }
    return $pages;
}
