<?php

function import_read_xlsx_sheets(string $xlsxPath, string &$errorCode): array
{
    $errorCode = '';
    if (! class_exists('ZipArchive')) {
        $errorCode = 'zip_unavailable';

        return [];
    }
    if (! class_exists('XMLReader')) {
        $errorCode = 'invalid_excel';

        return [];
    }

    $zip = new ZipArchive;
    if ($zip->open($xlsxPath) !== true) {
        $errorCode = 'invalid_excel';

        return [];
    }

    $temporaryFiles = [];
    try {
        $relationshipPath = import_xlsx_extract_entry($zip, 'xl/_rels/workbook.xml.rels', $temporaryFiles);
        $workbookPath = import_xlsx_extract_entry($zip, 'xl/workbook.xml', $temporaryFiles);
        if ($relationshipPath === null || $workbookPath === null) {
            throw new RuntimeException('Workbook metadata is missing.');
        }

        $targetsByRelationship = import_xlsx_read_workbook_relationships($relationshipPath);
        @unlink($relationshipPath);
        $workbookSheets = import_xlsx_read_workbook_sheets($workbookPath);
        @unlink($workbookPath);
        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $sharedPath = import_xlsx_extract_entry($zip, 'xl/sharedStrings.xml', $temporaryFiles);
            if ($sharedPath === null) {
                throw new RuntimeException('Shared strings could not be read.');
            }
            $sharedStrings = import_xlsx_read_shared_strings($sharedPath);
            @unlink($sharedPath);
        }

        $result = [];
        foreach ($workbookSheets as $sheet) {
            $sheetName = trim((string) ($sheet['name'] ?? ''));
            $relationshipId = trim((string) ($sheet['relationship_id'] ?? ''));
            $entryName = $targetsByRelationship[$relationshipId] ?? null;
            if ($sheetName === '' || ! is_string($entryName) || $entryName === '') {
                continue;
            }

            $worksheetPath = import_xlsx_extract_entry($zip, $entryName, $temporaryFiles);
            if ($worksheetPath === null) {
                continue;
            }
            $result[$sheetName] = import_xlsx_read_worksheet_rows($worksheetPath, $sharedStrings);
            @unlink($worksheetPath);
        }

        return $result;
    } catch (Throwable) {
        $errorCode = 'invalid_excel';

        return [];
    } finally {
        $zip->close();
        foreach ($temporaryFiles as $temporaryFile) {
            if (is_string($temporaryFile) && is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }
}

/** @param array<int, string> $temporaryFiles */
function import_xlsx_extract_entry(ZipArchive $zip, string $entryName, array &$temporaryFiles): ?string
{
    $stream = $zip->getStream($entryName);
    if (! is_resource($stream)) {
        return null;
    }

    $temporaryPath = tempnam(sys_get_temp_dir(), 'xlsx_xml_');
    if ($temporaryPath === false) {
        fclose($stream);

        return null;
    }
    $temporaryFiles[] = $temporaryPath;
    $destination = @fopen($temporaryPath, 'wb');
    if (! is_resource($destination)) {
        fclose($stream);

        return null;
    }

    try {
        return stream_copy_to_stream($stream, $destination) !== false ? $temporaryPath : null;
    } finally {
        fclose($destination);
        fclose($stream);
    }
}

function import_xlsx_xml_reader(string $path): XMLReader
{
    $reader = new XMLReader;
    if (! @$reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
        throw new RuntimeException('XML entry could not be opened.');
    }

    return $reader;
}

/** @return array<string, string> */
function import_xlsx_read_workbook_relationships(string $path): array
{
    $reader = import_xlsx_xml_reader($path);
    $targets = [];
    try {
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'Relationship') {
                continue;
            }
            if (strcasecmp(trim((string) $reader->getAttribute('TargetMode')), 'External') === 0) {
                continue;
            }
            $id = trim((string) $reader->getAttribute('Id'));
            $target = import_xlsx_normalize_entry_name((string) $reader->getAttribute('Target'));
            if ($id !== '' && $target !== '') {
                $targets[$id] = $target;
            }
        }
    } finally {
        $reader->close();
    }

    return $targets;
}

function import_xlsx_normalize_entry_name(string $target): string
{
    $target = str_replace('\\', '/', trim($target));
    if ($target === '') {
        return '';
    }
    if (str_starts_with($target, '/')) {
        $target = ltrim($target, '/');
    } elseif (! str_starts_with($target, 'xl/')) {
        $target = 'xl/'.ltrim($target, '/');
    }

    $segments = [];
    foreach (explode('/', $target) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);

            continue;
        }
        $segments[] = $segment;
    }

    return ($segments[0] ?? '') === 'xl' ? implode('/', $segments) : '';
}

/** @return array<int, array{name:string,relationship_id:string}> */
function import_xlsx_read_workbook_sheets(string $path): array
{
    $reader = import_xlsx_xml_reader($path);
    $sheets = [];
    try {
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'sheet') {
                continue;
            }
            $name = trim((string) $reader->getAttribute('name'));
            $relationshipId = trim((string) $reader->getAttributeNs(
                'id',
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            ));
            if ($relationshipId === '') {
                $relationshipId = trim((string) $reader->getAttribute('r:id'));
            }
            if ($name !== '' && $relationshipId !== '') {
                $sheets[] = ['name' => $name, 'relationship_id' => $relationshipId];
            }
        }
    } finally {
        $reader->close();
    }

    return $sheets;
}

/** @return array<int, string> */
function import_xlsx_read_shared_strings(string $path): array
{
    $reader = import_xlsx_xml_reader($path);
    $strings = [];
    try {
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $strings[] = import_xlsx_shared_string_text($reader);
            }
        }
    } finally {
        $reader->close();
    }

    return $strings;
}

/** @param array<int, string> $sharedStrings @return array<int, array<int, string>> */
function import_xlsx_read_worksheet_rows(string $path, array $sharedStrings): array
{
    $reader = import_xlsx_xml_reader($path);
    $rows = [];
    try {
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            if ($reader->isEmptyElement) {
                $rows[] = [];

                continue;
            }

            $rowDepth = $reader->depth;
            $cells = [];
            $automaticColumn = 0;
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::END_ELEMENT
                    && $reader->depth === $rowDepth
                    && $reader->localName === 'row') {
                    break;
                }
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') {
                    continue;
                }

                $cellReference = strtoupper(trim((string) $reader->getAttribute('r')));
                $column = $cellReference !== ''
                    ? import_xlsx_column_index_from_ref($cellReference)
                    : $automaticColumn;
                if ($column < 0) {
                    $column = $automaticColumn;
                }
                $automaticColumn = $column + 1;
                $cells[$column] = trim(import_xlsx_read_cell_value($reader, $sharedStrings));
            }

            if ($cells === []) {
                $rows[] = [];

                continue;
            }
            ksort($cells, SORT_NUMERIC);
            $lastColumn = max(array_keys($cells));
            $row = [];
            for ($column = 0; $column <= $lastColumn; $column++) {
                $row[] = isset($cells[$column]) ? (string) $cells[$column] : '';
            }
            while ($row !== [] && trim((string) $row[count($row) - 1]) === '') {
                array_pop($row);
            }
            $rows[] = $row;
        }
    } finally {
        $reader->close();
    }

    return $rows;
}

/** @param array<int, string> $sharedStrings */
function import_xlsx_read_cell_value(XMLReader $reader, array $sharedStrings): string
{
    $type = trim((string) $reader->getAttribute('t'));
    if ($reader->isEmptyElement) {
        return '';
    }

    $cellDepth = $reader->depth;
    $rawValue = '';
    $inlineValue = '';
    $phoneticDepth = null;
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::END_ELEMENT
            && $reader->depth === $cellDepth
            && $reader->localName === 'c') {
            break;
        }
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'rPh') {
            $phoneticDepth = $reader->depth;

            continue;
        }
        if ($reader->nodeType === XMLReader::END_ELEMENT
            && $phoneticDepth !== null
            && $reader->depth === $phoneticDepth
            && $reader->localName === 'rPh') {
            $phoneticDepth = null;

            continue;
        }
        if ($reader->nodeType !== XMLReader::ELEMENT) {
            continue;
        }
        if ($reader->localName === 'v') {
            $rawValue = $reader->readString();
        } elseif ($type === 'inlineStr' && $reader->localName === 't' && $phoneticDepth === null) {
            $inlineValue .= $reader->readString();
        }
    }

    if ($type === 's') {
        $sharedIndex = filter_var(trim($rawValue), FILTER_VALIDATE_INT);

        return $sharedIndex !== false && isset($sharedStrings[$sharedIndex])
            ? (string) $sharedStrings[$sharedIndex]
            : '';
    }

    return $type === 'inlineStr' ? $inlineValue : $rawValue;
}
