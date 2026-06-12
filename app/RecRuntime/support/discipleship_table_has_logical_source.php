<?php

function discipleship_table_has_logical_source(string $name): bool {
    if ($name === 'discipleship_persons') {
        return discipleship_persons_are_unified();
    }
    if (isset(discipleship_embedded_relation_table_names()[$name])) {
        return discipleship_relationship_database_exists()
            || discipleship_persons_are_unified();
    }
    return \App\Support\LegacyDataStore::hasDocument($name);
}
