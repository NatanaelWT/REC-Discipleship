<?php

function discipleship_person_sources_by_id(array $members, array $mskClasses): array {
    return index_by_id(discipleship_person_sources($members, $mskClasses));
}
