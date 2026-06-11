<?php

function discipleship_relationship_kind_for_table(string $name): string {
    return discipleship_relationship_kind_map()[$name] ?? '';
}
