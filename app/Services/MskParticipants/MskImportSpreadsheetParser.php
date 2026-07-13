<?php

namespace App\Services\MskParticipants;

use App\Exceptions\MskImportException;
use Throwable;
use XMLReader;
use ZipArchive;

final class MskImportSpreadsheetParser
{
    private const MAX_ROWS = 5000;

    private const MAX_COLUMNS = 128;

    private const MAX_CELL_BYTES = 65535;

    private const MAX_METADATA_ENTRY_BYTES = 2 * 1024 * 1024;

    private const MAX_SHARED_STRINGS_BYTES = 32 * 1024 * 1024;

    private const MAX_WORKSHEET_BYTES = 64 * 1024 * 1024;

    private const MAX_EXTRACTED_BYTES = 96 * 1024 * 1024;

    private const MAX_STAGED_BYTES = 32 * 1024 * 1024;

    public function __construct(private readonly MskImportRowNormalizer $normalizer) {}

    /**
     * @return array{
     *     total_rows:int,
     *     references:list<array{row_number:int,participant_id:int|null,identity_key:string}>
     * }
     */
    public function parse(string $sourcePath, string $stagedPath, ?float $deadline = null): array
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(XMLReader::class)) {
            throw new MskImportException('import_zip_unavailable');
        }

        $handle = @fopen($stagedPath, 'wb');
        if (! is_resource($handle)) {
            throw new MskImportException('import_stage_failed');
        }

        $zip = new ZipArchive;
        if ($zip->open($sourcePath) !== true) {
            fclose($handle);
            throw new MskImportException('import_invalid_excel');
        }

        $temporaryFiles = [];
        $sharedStrings = new XlsxSharedStringStore;
        $errors = [];
        $errorCount = 0;
        $totalRows = 0;
        $references = [];
        $seenParticipantIds = [];
        $seenIdentityKeys = [];
        $seenRowNumbers = [];
        $extractedBytes = 0;
        $stagedBytes = 0;
        $automaticRowNumber = 0;
        $maxRows = max(1, min(
            self::MAX_ROWS,
            (int) config('msk_import.max_rows', self::MAX_ROWS),
        ));

        try {
            $relationshipPath = $this->extractEntry(
                $zip,
                'xl/_rels/workbook.xml.rels',
                $temporaryFiles,
                self::MAX_METADATA_ENTRY_BYTES,
                $extractedBytes,
                $deadline,
            );
            $workbookPath = $this->extractEntry(
                $zip,
                'xl/workbook.xml',
                $temporaryFiles,
                self::MAX_METADATA_ENTRY_BYTES,
                $extractedBytes,
                $deadline,
            );
            if ($relationshipPath === null || $workbookPath === null) {
                throw new MskImportException('import_invalid_excel');
            }

            $relationships = import_xlsx_read_workbook_relationships($relationshipPath);
            $sheets = import_xlsx_read_workbook_sheets($workbookPath);
            $worksheetEntry = null;
            foreach ($sheets as $sheet) {
                if (import_sheet_name_key((string) ($sheet['name'] ?? '')) !== 'kelas msk') {
                    continue;
                }
                $worksheetEntry = $relationships[(string) ($sheet['relationship_id'] ?? '')] ?? null;
                break;
            }
            if (! is_string($worksheetEntry) || $worksheetEntry === '') {
                throw new MskImportException('import_missing_sheet');
            }

            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $sharedPath = $this->extractEntry(
                    $zip,
                    'xl/sharedStrings.xml',
                    $temporaryFiles,
                    self::MAX_SHARED_STRINGS_BYTES,
                    $extractedBytes,
                    $deadline,
                );
                if ($sharedPath === null) {
                    throw new MskImportException('import_invalid_excel');
                }
                $sharedStrings->build($sharedPath, $deadline);
            }

            $worksheetPath = $this->extractEntry(
                $zip,
                $worksheetEntry,
                $temporaryFiles,
                self::MAX_WORKSHEET_BYTES,
                $extractedBytes,
                $deadline,
            );
            if ($worksheetPath === null) {
                throw new MskImportException('import_invalid_excel');
            }

            $reader = import_xlsx_xml_reader($worksheetPath);
            $headers = null;
            try {
                while (($row = $this->nextRow($reader, $sharedStrings, $automaticRowNumber, $deadline)) !== null) {
                    if (import_is_blank_row($row['cells'])) {
                        continue;
                    }
                    if (isset($seenRowNumbers[$row['number']])) {
                        $this->addError($errors, $errorCount, [
                            'code' => 'duplicate_row_number',
                            'row' => $row['number'],
                            'message' => 'Nomor baris worksheet muncul lebih dari sekali.',
                        ]);

                        continue;
                    }
                    $seenRowNumbers[$row['number']] = true;
                    if ($headers === null) {
                        $headers = import_build_header_map($row['cells']);
                        foreach (['full_name', 'msk_month', 'session_numbers'] as $required) {
                            if (! isset($headers[$required])) {
                                $this->addError($errors, $errorCount, [
                                    'code' => 'missing_header',
                                    'row' => $row['number'],
                                    'message' => 'Kolom wajib "'.$required.'" tidak ditemukan.',
                                ]);
                            }
                        }

                        continue;
                    }

                    $totalRows++;
                    if ($totalRows > $maxRows) {
                        throw new MskImportException('import_too_many_rows', [
                            'row' => $row['number'],
                            'total_rows' => $totalRows,
                            'max_rows' => $maxRows,
                        ]);
                    }

                    $normalized = $this->normalizer->normalize($row['cells'], $headers, $row['number']);
                    if (is_array($normalized['error'] ?? null)) {
                        $this->addError($errors, $errorCount, $normalized['error']);

                        continue;
                    }
                    $data = $normalized['data'] ?? null;
                    if (! is_array($data)) {
                        $this->addError($errors, $errorCount, [
                            'code' => 'invalid_row',
                            'row' => $row['number'],
                            'message' => 'Baris tidak dapat dibaca.',
                        ]);

                        continue;
                    }

                    $participantId = isset($data['participant_id']) ? (int) $data['participant_id'] : null;
                    $identityKey = (string) ($data['identity_key'] ?? '');
                    $duplicate = false;
                    if ($participantId !== null) {
                        if (isset($seenParticipantIds[$participantId])) {
                            $this->addError($errors, $errorCount, [
                                'code' => 'duplicate_source_row',
                                'row' => (int) $data['row_number'],
                                'message' => 'participant_id muncul lebih dari sekali di file import.',
                            ]);
                            $duplicate = true;
                        }
                        $seenParticipantIds[$participantId] = true;
                    }
                    if ($participantId === null && $identityKey !== '') {
                        if (isset($seenIdentityKeys[$identityKey])) {
                            $this->addError($errors, $errorCount, [
                                'code' => 'duplicate_source_identity',
                                'row' => (int) $data['row_number'],
                                'message' => 'Kombinasi nama/WhatsApp muncul lebih dari sekali di file import.',
                            ]);
                            $duplicate = true;
                        }
                        $seenIdentityKeys[$identityKey] = true;
                    }
                    if ($duplicate) {
                        continue;
                    }

                    $line = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
                    $lineBytes = strlen($line);
                    if ($stagedBytes + $lineBytes > self::MAX_STAGED_BYTES) {
                        throw new MskImportException('import_stage_too_large', [
                            'row' => (int) $data['row_number'],
                            'max_bytes' => self::MAX_STAGED_BYTES,
                        ]);
                    }
                    if (! $this->writeAll($handle, $line)) {
                        throw new MskImportException('import_stage_failed');
                    }
                    $stagedBytes += $lineBytes;
                    $references[] = [
                        'row_number' => (int) $data['row_number'],
                        'participant_id' => $participantId,
                        'identity_key' => $participantId === null ? $identityKey : '',
                    ];
                }
            } finally {
                $reader->close();
            }

            if ($headers === null || $totalRows === 0) {
                throw new MskImportException('import_empty_sheet');
            }
            if (! fflush($handle)) {
                throw new MskImportException('import_stage_failed');
            }
            if ($errors !== []) {
                throw new MskImportException('import_validation_failed', [
                    'errors' => $errors,
                    'error_count' => $errorCount,
                    'total_rows' => $totalRows,
                ]);
            }

            return [
                'total_rows' => $totalRows,
                'references' => $references,
            ];
        } catch (MskImportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw new MskImportException('import_invalid_excel');
        } finally {
            fclose($handle);
            $zip->close();
            $sharedStrings->close();
            foreach ($temporaryFiles as $temporaryFile) {
                if (is_string($temporaryFile) && is_file($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }
        }
    }

    /** @return array{number:int,cells:array<int,string>}|null */
    private function nextRow(
        XMLReader $reader,
        XlsxSharedStringStore $sharedStrings,
        int &$automaticRowNumber,
        ?float $deadline,
    ): ?array {
        while ($reader->read()) {
            $this->assertWithinDeadline($deadline);
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $rawRowNumber = trim((string) $reader->getAttribute('r'));
            if ($rawRowNumber !== '' && preg_match('/^\d+$/', $rawRowNumber) !== 1) {
                throw new MskImportException('import_invalid_excel');
            }
            $rowNumber = $rawRowNumber !== '' ? (int) $rawRowNumber : $automaticRowNumber + 1;
            if ($rowNumber < 1 || $rowNumber > 1_048_576) {
                throw new MskImportException('import_invalid_excel');
            }
            $automaticRowNumber = max($automaticRowNumber, $rowNumber);
            if ($reader->isEmptyElement) {
                return ['number' => $rowNumber, 'cells' => []];
            }

            $depth = $reader->depth;
            $cells = [];
            $automaticColumn = 0;
            while ($reader->read()) {
                $this->assertWithinDeadline($deadline);
                if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth && $reader->localName === 'row') {
                    break;
                }
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') {
                    continue;
                }
                $reference = strtoupper(trim((string) $reader->getAttribute('r')));
                $column = $reference !== '' ? import_xlsx_column_index_from_ref($reference) : $automaticColumn;
                if ($column < 0) {
                    $column = $automaticColumn;
                }
                if ($column >= self::MAX_COLUMNS) {
                    throw new MskImportException('import_too_many_columns', [
                        'row' => $rowNumber,
                        'max_columns' => self::MAX_COLUMNS,
                    ]);
                }
                $automaticColumn = $column + 1;
                $value = $this->readCell($reader, $sharedStrings, $deadline);
                if (strlen($value) > self::MAX_CELL_BYTES) {
                    throw new MskImportException('import_cell_too_large', [
                        'row' => $rowNumber,
                        'column' => $column + 1,
                        'max_bytes' => self::MAX_CELL_BYTES,
                    ]);
                }
                $cells[$column] = trim($value);
            }

            if ($cells === []) {
                return ['number' => $rowNumber, 'cells' => []];
            }
            ksort($cells, SORT_NUMERIC);
            $result = [];
            for ($column = 0; $column <= max(array_keys($cells)); $column++) {
                $result[] = (string) ($cells[$column] ?? '');
            }
            while ($result !== [] && trim((string) end($result)) === '') {
                array_pop($result);
            }

            return ['number' => $rowNumber, 'cells' => $result];
        }

        return null;
    }

    private function readCell(XMLReader $reader, XlsxSharedStringStore $sharedStrings, ?float $deadline): string
    {
        $type = trim((string) $reader->getAttribute('t'));
        if ($reader->isEmptyElement) {
            return '';
        }
        $depth = $reader->depth;
        $raw = '';
        $inline = '';
        while ($reader->read()) {
            $this->assertWithinDeadline($deadline);
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth && $reader->localName === 'c') {
                break;
            }
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->localName === 'v') {
                $raw = $reader->readString();
            } elseif ($type === 'inlineStr' && $reader->localName === 't') {
                $inline .= $reader->readString();
            }
        }
        if ($type === 's') {
            $rawIndex = trim($raw);
            if ($rawIndex === '' || preg_match('/^(?:0|[1-9]\d*)$/', $rawIndex) !== 1) {
                throw new MskImportException('import_invalid_excel');
            }
            $index = filter_var($rawIndex, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0],
            ]);
            if (! is_int($index) || ! $sharedStrings->has($index)) {
                throw new MskImportException('import_invalid_excel');
            }

            return $sharedStrings->get($index);
        }

        return $type === 'inlineStr' ? $inline : $raw;
    }

    /** @param list<string> $temporaryFiles */
    private function extractEntry(
        ZipArchive $zip,
        string $entryName,
        array &$temporaryFiles,
        int $entryLimit,
        int &$extractedBytes,
        ?float $deadline,
    ): ?string {
        $this->assertWithinDeadline($deadline);
        $stat = $zip->statName($entryName);
        $declaredSize = is_array($stat) ? max(0, (int) ($stat['size'] ?? 0)) : 0;
        if ($declaredSize > $entryLimit || $extractedBytes + $declaredSize > self::MAX_EXTRACTED_BYTES) {
            throw new MskImportException('import_archive_too_large');
        }

        $source = $zip->getStream($entryName);
        if (! is_resource($source)) {
            return null;
        }
        $path = tempnam(sys_get_temp_dir(), 'rec_msk_xlsx_');
        if (! is_string($path) || $path === '') {
            fclose($source);

            return null;
        }
        @chmod($path, 0600);
        $temporaryFiles[] = $path;
        $destination = @fopen($path, 'wb');
        if (! is_resource($destination)) {
            fclose($source);

            return null;
        }

        $written = 0;
        try {
            while (! feof($source)) {
                $this->assertWithinDeadline($deadline);
                $chunk = fread($source, 8192);
                if (! is_string($chunk)) {
                    return null;
                }
                if ($chunk === '') {
                    if (feof($source)) {
                        break;
                    }

                    return null;
                }
                $written += strlen($chunk);
                if ($written > $entryLimit || $extractedBytes + $written > self::MAX_EXTRACTED_BYTES) {
                    throw new MskImportException('import_archive_too_large');
                }
                if (! $this->writeAll($destination, $chunk)) {
                    return null;
                }
            }
            $extractedBytes += $written;

            return $path;
        } finally {
            fclose($destination);
            fclose($source);
        }
    }

    private function assertWithinDeadline(?float $deadline): void
    {
        if ($deadline !== null && microtime(true) >= $deadline) {
            throw new MskImportException('import_timeout');
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $errors
     * @param  array<string,mixed>  $error
     */
    private function addError(array &$errors, int &$errorCount, array $error): void
    {
        $errorCount++;
        if (count($errors) < (int) config('msk_import.max_errors', 100)) {
            $errors[] = $error;
        }
    }

    /** @param resource $handle */
    private function writeAll($handle, string $contents): bool
    {
        $offset = 0;
        $length = strlen($contents);
        while ($offset < $length) {
            $written = fwrite($handle, substr($contents, $offset));
            if ($written === false || $written === 0) {
                return false;
            }
            $offset += $written;
        }

        return true;
    }
}
