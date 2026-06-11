<?php

function index_active_members_by_id(array $members): array {
    return index_by_id(filter_active_members($members));
}
