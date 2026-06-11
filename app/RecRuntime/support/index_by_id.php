<?php

function index_by_id(array $items): array {
    $map = [];
    foreach ($items as $item) {
        if (isset($item['id'])) {
            $map[$item['id']] = $item;
        }
    }
    return $map;
}
