<?php

function discipleship_person_sources(array $members, array $mskClasses): array {
    return filter_active_members($members);
}
