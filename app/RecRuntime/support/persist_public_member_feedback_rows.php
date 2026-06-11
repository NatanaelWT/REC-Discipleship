<?php

function persist_public_member_feedback_rows(array $rows, string $branch): bool {
    return write_json_unrestricted(scoped_data_path('dg_member_feedback_journals', normalize_public_branch_code($branch)), array_values($rows));
}
