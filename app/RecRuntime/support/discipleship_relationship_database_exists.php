<?php

function discipleship_relationship_database_exists(): bool {
    return is_file(discipleship_table_path(DISCIPLESHIP_RELATIONSHIPS_DATA_NAME));
}
