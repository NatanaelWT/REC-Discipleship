<?php

namespace App\Services\MskParticipants;

use App\Exceptions\MskImportException;
use App\Models\MskImportJob;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use XMLReader;
use ZipArchive;

class MskImportSpreadsheetStager
{
    public function __construct(private readonly MskImportRowNormalizer $normalizer) {}

    /** @return array{staged_path:string,total_rows:int,errors:array<int,array<string,mixed>>} */
    public function stage(MskImportJob $job, string $sourcePath): array
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(XMLReader::class)) {
            throw new MskImportException('import_zip_unavailable');
        }

        $disk = Storage::disk((string) config('msk_import.disk', 'local'));
        $stagedPath = 'imports/msk/'.$job->getKey().'/rows.jsonl';
        $disk->makeDirectory(dirname($stagedPath));
        $absoluteStagedPath = $disk->path($stagedPath);
        $handle = @fopen($absoluteStagedPath, 'wb');
        if (! is_resource($handle)) {
            throw new MskImportException('import_stage_failed');
        }

        $zip = new ZipArchive;
        if ($zip->open($sourcePath) !== true) {
            fclose($handle);
            @unlink($absoluteStagedPath);
            throw new MskImportException('import_invalid_excel');
        }

        $temporaryFiles = [];
        $sharedStrings = new XlsxSharedStringStore;
        $errors = [];
        $totalRows = 0;
        $keyBuffer = [];

        try {
            $relationshipPath = import_xlsx_extract_entry($zip, 'xl/_rels/workbook.xml.rels', $temporaryFiles);
            $workbookPath = import_xlsx_extract_entry($zip, 'xl/workbook.xml', $temporaryFiles);
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
                $sharedPath = import_xlsx_extract_entry($zip, 'xl/sharedStrings.xml', $temporaryFiles);
                if ($sharedPath === null) {
                    throw new MskImportException('import_invalid_excel');
                }
                $sharedStrings->build($sharedPath);
            }

            $worksheetPath = import_xlsx_extract_entry($zip, $worksheetEntry, $temporaryFiles);
            if ($worksheetPath === null) {
                throw new MskImportException('import_invalid_excel');
            }

            $reader = import_xlsx_xml_reader($worksheetPath);
            $headers = null;
            try {
                while (($row = $this->nextRow($reader, $sharedStrings)) !== null) {
                    if (import_is_blank_row($row['cells'])) {
                        continue;
                    }
                    if ($headers === null) {
                        $headers = import_build_header_map($row['cells']);
                        foreach (['full_name', 'msk_month', 'session_numbers'] as $required) {
                            if (! isset($headers[$required])) {
                                $this->addError($errors, [
                                    'code' => 'missing_header',
                                    'row' => $row['number'],
                                    'message' => 'Kolom wajib "'.$required.'" tidak ditemukan.',
                                ]);
                            }
                        }

                        continue;
                    }

                    $totalRows++;
                    $normalized = $this->normalizer->normalize($row['cells'], $headers, $row['number']);
                    if (is_array($normalized['error'] ?? null)) {
                        $this->addError($errors, $normalized['error']);

                        continue;
                    }
                    $data = $normalized['data'] ?? null;
                    if (! is_array($data)) {
                        $this->addError($errors, ['code' => 'invalid_row', 'row' => $row['number'], 'message' => 'Baris tidak dapat dibaca.']);

                        continue;
                    }

                    $line = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
                    if (! $this->writeAll($handle, $line)) {
                        throw new MskImportException('import_stage_failed');
                    }

                    $keyBuffer[] = [
                        'job_id' => $job->getKey(),
                        'row_number' => (int) $data['row_number'],
                        'match_type' => $data['participant_id'] !== null ? 'participant' : 'identity',
                        'match_key' => $data['participant_id'] !== null ? (string) $data['participant_id'] : (string) $data['identity_key'],
                    ];
                    if (count($keyBuffer) >= 500) {
                        $this->flushSourceKeys($job, $keyBuffer, $errors);
                    }
                }
            } finally {
                $reader->close();
            }

            $this->flushSourceKeys($job, $keyBuffer, $errors);
            if ($headers === null || $totalRows === 0) {
                $this->addError($errors, ['code' => 'empty_sheet', 'row' => 0, 'message' => 'Sheet Kelas MSK tidak memiliki baris data.']);
            }
            if (! fflush($handle)) {
                throw new MskImportException('import_stage_failed');
            }

            if ($errors === []) {
                $this->snapshotExistingPeople($job);
                $this->validateMatches($job, $errors);
            }

            return ['staged_path' => $stagedPath, 'total_rows' => $totalRows, 'errors' => $errors];
        } catch (MskImportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MskImportException('import_invalid_excel', ['reason' => $exception->getMessage()]);
        } finally {
            fclose($handle);
            $zip->close();
            $sharedStrings->close();
            foreach ($temporaryFiles as $temporaryFile) {
                if (is_string($temporaryFile) && is_file($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }
            if ($errors !== [] && is_file($absoluteStagedPath)) {
                @unlink($absoluteStagedPath);
            }
        }
    }

    /** @return array{number:int,cells:array<int,string>}|null */
    private function nextRow(XMLReader $reader, XlsxSharedStringStore $sharedStrings): ?array
    {
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $rowNumber = max(1, (int) $reader->getAttribute('r'));
            if ($reader->isEmptyElement) {
                return ['number' => $rowNumber, 'cells' => []];
            }

            $depth = $reader->depth;
            $cells = [];
            $automaticColumn = 0;
            while ($reader->read()) {
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
                $automaticColumn = $column + 1;
                $cells[$column] = trim($this->readCell($reader, $sharedStrings));
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

    private function readCell(XMLReader $reader, XlsxSharedStringStore $sharedStrings): string
    {
        $type = trim((string) $reader->getAttribute('t'));
        if ($reader->isEmptyElement) {
            return '';
        }
        $depth = $reader->depth;
        $raw = '';
        $inline = '';
        while ($reader->read()) {
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
            return $sharedStrings->get((int) trim($raw));
        }

        return $type === 'inlineStr' ? $inline : $raw;
    }

    /** @param array<int,array<string,mixed>> $buffer @param array<int,array<string,mixed>> $errors */
    private function flushSourceKeys(MskImportJob $job, array &$buffer, array &$errors): void
    {
        if ($buffer === []) {
            return;
        }
        $existing = [];
        foreach (['participant', 'identity'] as $type) {
            $keys = array_values(array_unique(array_column(array_filter($buffer, static fn (array $row): bool => $row['match_type'] === $type), 'match_key')));
            if ($keys === []) {
                continue;
            }
            foreach (DB::table('msk_import_source_keys')->where('job_id', $job->getKey())->where('match_type', $type)->whereIn('match_key', $keys)->pluck('match_key') as $key) {
                $existing[$type.':'.$key] = true;
            }
        }

        $insert = [];
        foreach ($buffer as $row) {
            $key = $row['match_type'].':'.$row['match_key'];
            if (isset($existing[$key])) {
                $this->addError($errors, [
                    'code' => 'duplicate_source_row',
                    'row' => (int) $row['row_number'],
                    'message' => 'Peserta muncul lebih dari sekali di file import.',
                ]);

                continue;
            }
            $existing[$key] = true;
            $insert[] = $row;
        }
        if ($insert !== []) {
            DB::table('msk_import_source_keys')->insert($insert);
        }
        $buffer = [];
    }

    private function snapshotExistingPeople(MskImportJob $job): void
    {
        foreach (Person::query()->where('branch_id', $job->branch_id)->select(['id', 'full_name', 'whatsapp'])->lazyById(500)->chunk(500) as $people) {
            $rows = [];
            foreach ($people as $person) {
                $identity = discipleship_unified_identity_key((string) $person->full_name, (string) $person->whatsapp);
                $rows[] = [
                    'job_id' => $job->getKey(),
                    'person_id' => (int) $person->getKey(),
                    'identity_key' => $identity !== '' ? hash('sha256', $identity) : null,
                    'touched_at' => null,
                ];
            }
            if ($rows !== []) {
                DB::table('msk_import_existing_people')->insert($rows);
            }
        }
    }

    /** @param array<int,array<string,mixed>> $errors */
    private function validateMatches(MskImportJob $job, array &$errors): void
    {
        $ambiguous = DB::table('msk_import_source_keys as source')
            ->join('msk_import_existing_people as existing', function ($join): void {
                $join->on('existing.job_id', '=', 'source.job_id')->on('existing.identity_key', '=', 'source.match_key');
            })
            ->where('source.job_id', $job->getKey())
            ->where('source.match_type', 'identity')
            ->groupBy('source.id', 'source.row_number')
            ->havingRaw('COUNT(existing.id) > 1')
            ->get(['source.row_number']);
        foreach ($ambiguous as $row) {
            $this->addError($errors, [
                'code' => 'ambiguous_identity',
                'row' => (int) $row->row_number,
                'message' => 'Nama/WhatsApp cocok dengan lebih dari satu peserta. Gunakan participant_id dari export.',
            ]);
        }

        DB::table('msk_import_source_keys')
            ->where('job_id', $job->getKey())
            ->where('match_type', 'participant')
            ->orderBy('id')
            ->chunkById(500, function ($sourceRows) use ($job, &$errors): void {
                $ids = $sourceRows->pluck('match_key')->map(static fn ($id): int => (int) $id)->all();
                $found = DB::table('msk_import_existing_people')->where('job_id', $job->getKey())->whereIn('person_id', $ids)->pluck('person_id')->map(static fn ($id): int => (int) $id)->flip();
                foreach ($sourceRows as $row) {
                    if (! $found->has((int) $row->match_key)) {
                        $this->addError($errors, [
                            'code' => 'participant_not_in_branch',
                            'row' => (int) $row->row_number,
                            'message' => 'participant_id tidak ditemukan pada cabang ini.',
                        ]);
                    }
                }
            }, 'id');
    }

    /** @param array<int,array<string,mixed>> $errors @param array<string,mixed> $error */
    private function addError(array &$errors, array $error): void
    {
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
