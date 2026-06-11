<?php

function build_msk_import_export_sheet_xml(array $rows): string {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 1;
        if (!is_array($row)) {
            $row = [normalize_sheet_cell_value($row)];
        }
        $xml .= '<row r="' . $excelRow . '">';
        foreach (array_values($row) as $colIndex => $cellValue) {
            $cellRef = export_xlsx_column_ref((int) $colIndex) . $excelRow;
            $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t xml:space="preserve">'
                . export_xlsx_inline_text((string) $cellValue)
                . '</t></is></c>';
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}
