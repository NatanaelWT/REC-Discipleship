<?php

function is_worship_page(string $page): bool {
    $page = trim($page);
    return $page !== '' && isset(worship_page_map()[$page]);
}
