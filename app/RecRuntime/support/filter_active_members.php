<?php

function filter_active_members(array $members): array {
    return array_values(array_filter($members, function ($member) {
        return is_array($member) && is_member_active($member);
    }));
}
