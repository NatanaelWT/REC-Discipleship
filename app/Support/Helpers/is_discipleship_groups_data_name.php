<?php

function is_discipleship_groups_data_name(string $name): bool {
    return canonical_data_name($name) === DISCIPLESHIP_GROUPS_DATA_NAME;
}
