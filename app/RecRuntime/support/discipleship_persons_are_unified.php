<?php

function discipleship_persons_are_unified(): bool {
    return is_file(discipleship_table_path(PEOPLE_REGISTRY_DATA_NAME));
}
