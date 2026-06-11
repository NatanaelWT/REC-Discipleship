<?php

if ($page === 'people_tree_v2') {
    $redirectParams = $_GET;
    if (!is_array($redirectParams)) {
        $redirectParams = [];
    }
    unset($redirectParams['page']);
    redirect_to('people_tree', $redirectParams);
}
