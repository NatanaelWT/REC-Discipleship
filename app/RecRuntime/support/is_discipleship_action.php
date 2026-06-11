<?php

function is_discipleship_action(string $action): bool {
    $action = trim($action);
    return $action !== '' && isset(discipleship_action_map()[$action]);
}
