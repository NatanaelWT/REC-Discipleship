<?php

function import_read_xlsx_sheets(string $xlsxPath, string &$errorCode): array {
    $errorCode = '';
    if (!class_exists('ZipArchive')) {
        $errorCode = 'zip_unavailable';
        return [];
    }

    $zip = new ZipArchive();
    $openResult = $zip->open($xlsxPath);
    if ($openResult !== true) {
        $errorCode = 'invalid_excel';
        return [];
    }

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $workbookRelsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if (!is_string($workbookXml) || !is_string($workbookRelsXml)) {
        $zip->close();
        $errorCode = 'invalid_excel';
        return [];
    }

    $workbook = @simplexml_load_string($workbookXml);
    $workbookRels = @simplexml_load_string($workbookRelsXml);
    if ($workbook === false || $workbookRels === false) {
        $zip->close();
        $errorCode = 'invalid_excel';
        return [];
    }

    $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $workbookRels->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $targetsByRid = [];
    $relationships = $workbookRels->xpath('//p:Relationship');
    if (is_array($relationships)) {
        foreach ($relationships as $relationship) {
            $id = trim((string) ($relationship['Id'] ?? ''));
            $target = trim((string) ($relationship['Target'] ?? ''));
            if ($id === '' || $target === '') {
                continue;
            }
            $target = str_replace('\\', '/', $target);
            if (strpos($target, '/xl/') === 0) {
                $target = ltrim($target, '/');
            } elseif (strpos($target, 'xl/') !== 0) {
                $target = 'xl/' . ltrim($target, '/');
            }
            $targetsByRid[$id] = $target;
        }
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if (is_string($sharedXml)) {
        $shared = @simplexml_load_string($sharedXml);
        if ($shared !== false) {
            $shared->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $items = $shared->xpath('//m:si');
            if (is_array($items)) {
                foreach ($items as $si) {
                    if ($si instanceof \SimpleXMLElement) {
                        $sharedStrings[] = import_xlsx_shared_string_text($si);
                    }
                }
            }
        }
    }

    $result = [];
    $sheetNodes = $workbook->xpath('//m:sheets/m:sheet');
    if (is_array($sheetNodes)) {
        foreach ($sheetNodes as $sheetNode) {
            if (!$sheetNode instanceof \SimpleXMLElement) {
                continue;
            }
            $sheetName = trim((string) ($sheetNode['name'] ?? ''));
            $ridAttr = $sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = trim((string) ($ridAttr['id'] ?? ''));
            if ($sheetName === '' || $rid === '' || !isset($targetsByRid[$rid])) {
                continue;
            }

            $sheetXml = $zip->getFromName($targetsByRid[$rid]);
            if (!is_string($sheetXml)) {
                continue;
            }
            $sheet = @simplexml_load_string($sheetXml);
            if ($sheet === false) {
                continue;
            }
            $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];
            $rowNodes = $sheet->xpath('//m:sheetData/m:row');
            if (is_array($rowNodes)) {
                foreach ($rowNodes as $rowNode) {
                    if (!$rowNode instanceof \SimpleXMLElement) {
                        continue;
                    }
                    $cells = [];
                    $autoCol = 0;
                    foreach ($rowNode->c as $cell) {
                        $cellRef = strtoupper(trim((string) ($cell['r'] ?? '')));
                        $colIndex = $cellRef !== '' ? import_xlsx_column_index_from_ref($cellRef) : $autoCol;
                        if ($colIndex < 0) {
                            $colIndex = $autoCol;
                        }
                        $autoCol = $colIndex + 1;

                        $cellType = trim((string) ($cell['t'] ?? ''));
                        $value = '';
                        if ($cellType === 's') {
                            $sharedIndex = (int) ((string) ($cell->v ?? '0'));
                            if (isset($sharedStrings[$sharedIndex])) {
                                $value = (string) $sharedStrings[$sharedIndex];
                            }
                        } elseif ($cellType === 'inlineStr') {
                            $value = (string) ($cell->is->t ?? '');
                            if ($value === '' && isset($cell->is->r)) {
                                foreach ($cell->is->r as $run) {
                                    $value .= (string) ($run->t ?? '');
                                }
                            }
                        } else {
                            $value = (string) ($cell->v ?? '');
                        }
                        $cells[$colIndex] = trim($value);
                    }
                    if (count($cells) === 0) {
                        $rows[] = [];
                        continue;
                    }
                    ksort($cells, SORT_NUMERIC);
                    $maxIdx = max(array_keys($cells));
                    $row = [];
                    for ($i = 0; $i <= $maxIdx; $i++) {
                        $row[] = isset($cells[$i]) ? (string) $cells[$i] : '';
                    }
                    while (count($row) > 0 && trim((string) $row[count($row) - 1]) === '') {
                        array_pop($row);
                    }
                    $rows[] = $row;
                }
            }
            $result[$sheetName] = $rows;
        }
    }

    $zip->close();
    return $result;
}
