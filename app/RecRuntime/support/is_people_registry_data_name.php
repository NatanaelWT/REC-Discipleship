<?php

function is_people_registry_data_name(string $name): bool {
    return canonical_data_name($name) === PEOPLE_REGISTRY_DATA_NAME;
}
