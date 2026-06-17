<?php

function is_worship_action(string $action): bool {
    $action = trim($action);
    return $action !== '' && isset(worship_action_map()[$action]);
}
