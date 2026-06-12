<?php

function discipleship_persons_are_unified(): bool {
    return \App\Support\LegacyDataStore::hasDocument(PEOPLE_REGISTRY_DATA_NAME);
}
