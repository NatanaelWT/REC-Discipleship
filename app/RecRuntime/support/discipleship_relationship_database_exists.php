<?php

function discipleship_relationship_database_exists(): bool {
    return \App\Support\LegacyDataStore::hasDocument(DISCIPLESHIP_RELATIONSHIPS_DATA_NAME);
}
