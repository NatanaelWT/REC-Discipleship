<?php

function dgv2_effective_end_date(array $row): string {
    return normalize_ymd_date((string) ($row['end_date'] ?? ''));
}
