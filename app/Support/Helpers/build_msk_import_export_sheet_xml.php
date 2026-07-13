<?php

function build_msk_import_export_sheet_xml(array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 1;
        if (! is_array($row)) {
            $row = [normalize_sheet_cell_value($row)];
        }
        $xml .= '<row r="'.$excelRow.'">';
        foreach (array_values($row) as $colIndex => $cellValue) {
            $cellRef = export_xlsx_column_ref((int) $colIndex).$excelRow;
            $xml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t xml:space="preserve">'
                .export_xlsx_inline_text((string) $cellValue)
                .'</t></is></c>';
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData></worksheet>';

    return $xml;
}

function write_msk_import_export_sheet_file(iterable $participants, string &$errorCode): ?string
{
    $errorCode = '';
    $worksheetPath = tempnam(sys_get_temp_dir(), 'msk_sheet_');
    if ($worksheetPath === false) {
        $errorCode = 'export_failed';

        return null;
    }

    $handle = @fopen($worksheetPath, 'wb');
    if ($handle === false) {
        @unlink($worksheetPath);
        $errorCode = 'export_failed';

        return null;
    }

    $success = false;

    try {
        if (! write_msk_import_export_sheet_chunk(
            $handle,
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        )) {
            $errorCode = 'export_failed';

            return null;
        }

        $excelRow = 1;
        if (! write_msk_import_export_sheet_row($handle, msk_import_export_header_row(), $excelRow)) {
            $errorCode = 'export_failed';

            return null;
        }

        foreach ($participants as $participant) {
            if (! is_array($participant)) {
                continue;
            }

            $excelRow++;
            if (! write_msk_import_export_sheet_row(
                $handle,
                build_msk_import_export_participant_row($participant),
                $excelRow
            )) {
                $errorCode = 'export_failed';

                return null;
            }
        }

        if (! write_msk_import_export_sheet_chunk($handle, '</sheetData></worksheet>') || ! fflush($handle)) {
            $errorCode = 'export_failed';

            return null;
        }

        $success = true;

        return $worksheetPath;
    } catch (Throwable) {
        $errorCode = 'export_failed';

        return null;
    } finally {
        fclose($handle);
        if (! $success && is_file($worksheetPath)) {
            @unlink($worksheetPath);
        }
    }
}

/**
 * @param  resource  $handle
 */
function write_msk_import_export_sheet_row($handle, array $row, int $excelRow): bool
{
    if (! write_msk_import_export_sheet_chunk($handle, '<row r="'.$excelRow.'">')) {
        return false;
    }

    foreach (array_values($row) as $colIndex => $cellValue) {
        $cellRef = export_xlsx_column_ref((int) $colIndex).$excelRow;
        if (! write_msk_import_export_sheet_chunk(
            $handle,
            '<c r="'.$cellRef.'" t="inlineStr"><is><t xml:space="preserve">'
                .export_xlsx_inline_text((string) $cellValue)
                .'</t></is></c>'
        )) {
            return false;
        }
    }

    return write_msk_import_export_sheet_chunk($handle, '</row>');
}

/**
 * @param  resource  $handle
 */
function write_msk_import_export_sheet_chunk($handle, string $contents): bool
{
    $length = strlen($contents);
    $offset = 0;

    while ($offset < $length) {
        $written = fwrite($handle, substr($contents, $offset));
        if ($written === false || $written === 0) {
            return false;
        }
        $offset += $written;
    }

    return true;
}
