<?php

function export_xlsx_inline_text(string $value): string {
    return htmlspecialchars(normalize_sheet_cell_value($value), ENT_XML1 | ENT_COMPAT, 'UTF-8');
}
