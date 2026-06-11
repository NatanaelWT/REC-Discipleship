<?php

function discipleship_table_has_logical_source(string $name): bool {
    if ($name === 'discipleship_persons') {
        return discipleship_persons_are_unified() || is_file(discipleship_table_path($name));
    }
    if (isset(discipleship_embedded_relation_table_names()[$name])) {
        return discipleship_relationship_database_exists()
            || discipleship_persons_are_unified()
            || is_file(discipleship_table_path($name));
    }
    return is_file(discipleship_table_path($name));
}
