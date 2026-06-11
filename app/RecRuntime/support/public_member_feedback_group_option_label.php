<?php

function public_member_feedback_group_option_label(array $groupRow): string {
    $label = public_member_feedback_group_title($groupRow);

    $memberNames = [];
    $members = $groupRow['members'] ?? [];
    if (is_array($members)) {
        foreach ($members as $memberRow) {
            if (!is_array($memberRow)) {
                continue;
            }
            $memberName = trim((string) ($memberRow['name'] ?? ''));
            if ($memberName !== '') {
                $memberNames[] = $memberName;
            }
        }
    }
    if (count($memberNames) > 0) {
        $preview = array_slice($memberNames, 0, 4);
        $label .= ' - ' . implode(', ', $preview);
        if (count($memberNames) > count($preview)) {
            $label .= ' +' . (string) (count($memberNames) - count($preview));
        }
    }
    return $label;
}
