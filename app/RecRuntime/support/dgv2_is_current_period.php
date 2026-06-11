<?php

function dgv2_is_current_period(array $row): bool {
    if (!dgv2_is_active_row($row)) {
        return false;
    }
    return dgv2_effective_end_date($row) === '';
}
