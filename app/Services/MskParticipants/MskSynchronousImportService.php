<?php

namespace App\Services\MskParticipants;

use App\Exceptions\MskImportException;
use App\Http\Requests\MskParticipants\ImportMskParticipantsRequest;
use App\Models\Person;
use App\Support\PersonNameNormalizer;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MskSynchronousImportService
{
    private const LOCK_SECONDS = 300;

    private const PROCESSING_SECONDS = 240;

    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const WRITE_CHUNK_SIZE = 500;

    private const WRITE_CHUNK_BYTES = 8 * 1024 * 1024;

    /** @var list<string> */
    private const IMPORT_COLUMNS = [
        'full_name',
        'gender',
        'birth_date',
        'birth_place',
        'address',
        'email',
        'whatsapp',
        'batch_month',
        'notes',
        'session_numbers',
    ];

    public function __construct(private readonly MskImportSpreadsheetParser $parser) {}

    /**
     * @return array{
     *     total:int,
     *     inserted:int,
     *     updated:int,
     *     unchanged:int,
     *     no_op:bool
     * }
     */
    public function run(ImportMskParticipantsRequest $request): array
    {
        $branchId = (int) current_user_branch_id();
        if ($branchId < 1) {
            throw new MskImportException('access_denied');
        }

        $file = $request->file('import_pemuridan_excel');
        if ($file === null) {
            throw new MskImportException('import_missing_file');
        }
        if (! $file->isValid()) {
            throw new MskImportException('import_upload_failed');
        }
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new MskImportException('import_invalid_file_type');
        }
        $size = (int) ($file->getSize() ?? 0);
        $configuredLimit = (int) config('msk_import.max_file_bytes', self::MAX_FILE_BYTES);
        $maxFileBytes = max(1024, min(self::MAX_FILE_BYTES, $configuredLimit));
        if ($size < 1 || $size > $maxFileBytes) {
            throw new MskImportException('import_file_too_large', [
                'size_bytes' => $size,
                'max_bytes' => $maxFileBytes,
            ]);
        }
        $sourcePath = $file->getRealPath();
        if (! is_string($sourcePath) || $sourcePath === '' || ! is_file($sourcePath)) {
            throw new MskImportException('import_upload_failed');
        }

        $lockSeconds = $this->lockSeconds();
        $lock = $this->branchLock($branchId, $lockSeconds);
        try {
            $acquired = $lock->get();
        } catch (Throwable $exception) {
            report($exception);
            throw new MskImportException('import_lock_failed');
        }
        if (! $acquired) {
            throw new MskImportException('import_in_progress');
        }

        $stagedPath = null;
        $deadline = microtime(true) + min(self::PROCESSING_SECONDS, max(1, $lockSeconds - 30));
        try {
            $stagedPath = $this->temporaryJsonlPath();
            $parsed = $this->parser->parse($sourcePath, $stagedPath, $deadline);
            $matches = $this->preflightMatches($branchId, $parsed['references'], $parsed['total_rows'], $deadline);

            return $this->persist($branchId, $stagedPath, $matches, $parsed['total_rows'], $deadline);
        } finally {
            if (is_string($stagedPath) && is_file($stagedPath)) {
                @unlink($stagedPath);
            }
            try {
                $lock->release();
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function branchLock(int $branchId, int $seconds): Lock
    {
        try {
            return Cache::lock('msk-import:branch:'.$branchId, $seconds);
        } catch (Throwable $exception) {
            report($exception);
            throw new MskImportException('import_lock_failed');
        }
    }

    private function lockSeconds(): int
    {
        return max(60, min(
            self::LOCK_SECONDS,
            (int) config('msk_import.lock_seconds', self::LOCK_SECONDS),
        ));
    }

    private function temporaryJsonlPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rec_msk_import_');
        if (! is_string($path) || $path === '') {
            throw new MskImportException('import_stage_failed');
        }
        @chmod($path, 0600);

        return $path;
    }

    /**
     * @param  list<array{row_number:int,participant_id:int|null,identity_key:string}>  $references
     * @return array<int,int|null>
     */
    private function preflightMatches(int $branchId, array $references, int $totalRows, float $deadline): array
    {
        $participantIds = [];
        $wantedIdentities = [];
        foreach ($references as $reference) {
            $this->assertWithinDeadline($deadline);
            if ($reference['participant_id'] !== null) {
                $participantIds[$reference['participant_id']] = $reference['participant_id'];
            }
            if ($reference['identity_key'] !== '') {
                $wantedIdentities[$reference['identity_key']] = true;
            }
        }

        $existingIds = [];
        foreach (array_chunk(array_values($participantIds), self::WRITE_CHUNK_SIZE) as $ids) {
            $this->assertWithinDeadline($deadline);
            foreach (Person::query()
                ->where('branch_id', $branchId)
                ->whereIn('id', $ids)
                ->pluck('id') as $id) {
                $existingIds[(int) $id] = true;
            }
        }

        /** @var array<string,list<int>> $identityCandidates */
        $identityCandidates = [];
        if ($wantedIdentities !== []) {
            foreach (Person::query()
                ->where('branch_id', $branchId)
                ->select(['id', 'full_name', 'whatsapp'])
                ->lazyById(self::WRITE_CHUNK_SIZE) as $person) {
                $this->assertWithinDeadline($deadline);
                $identity = discipleship_unified_identity_key(
                    (string) $person->full_name,
                    (string) $person->whatsapp,
                );
                if ($identity === '') {
                    continue;
                }
                $identityKey = hash('sha256', $identity);
                if (! isset($wantedIdentities[$identityKey])) {
                    continue;
                }
                $identityCandidates[$identityKey] ??= [];
                $identityCandidates[$identityKey][] = (int) $person->getKey();
            }
        }

        $matches = [];
        $resolvedPeople = [];
        $errors = [];
        $errorCount = 0;
        foreach ($references as $reference) {
            $this->assertWithinDeadline($deadline);
            $rowNumber = $reference['row_number'];
            $requestedId = $reference['participant_id'];
            $candidates = $reference['identity_key'] !== ''
                ? array_values(array_unique($identityCandidates[$reference['identity_key']] ?? []))
                : [];
            $matchedId = null;

            if ($requestedId !== null) {
                if (! isset($existingIds[$requestedId])) {
                    $this->addPreflightError($errors, $errorCount, [
                        'code' => 'participant_not_in_branch',
                        'row' => $rowNumber,
                        'message' => 'participant_id tidak ditemukan pada cabang ini.',
                    ]);

                    continue;
                }
                $conflicts = array_values(array_filter($candidates, static fn (int $id): bool => $id !== $requestedId));
                if ($conflicts !== []) {
                    $this->addPreflightError($errors, $errorCount, [
                        'code' => 'identity_conflict',
                        'row' => $rowNumber,
                        'message' => 'Nama/WhatsApp mengarah ke peserta lain pada cabang ini.',
                    ]);

                    continue;
                }
                $matchedId = $requestedId;
            } elseif (count($candidates) > 1) {
                $this->addPreflightError($errors, $errorCount, [
                    'code' => 'ambiguous_identity',
                    'row' => $rowNumber,
                    'message' => 'Nama/WhatsApp cocok dengan lebih dari satu peserta. Gunakan participant_id dari export.',
                ]);

                continue;
            } elseif ($candidates !== []) {
                $matchedId = $candidates[0];
            }

            if ($matchedId !== null && isset($resolvedPeople[$matchedId])) {
                $this->addPreflightError($errors, $errorCount, [
                    'code' => 'duplicate_source_person',
                    'row' => $rowNumber,
                    'message' => 'Lebih dari satu baris import mengarah ke peserta yang sama.',
                ]);

                continue;
            }
            if ($matchedId !== null) {
                $resolvedPeople[$matchedId] = true;
            }
            $matches[$rowNumber] = $matchedId;
        }

        if ($errors !== []) {
            throw new MskImportException('import_validation_failed', [
                'errors' => $errors,
                'error_count' => $errorCount,
                'total_rows' => $totalRows,
            ]);
        }

        return $matches;
    }

    /**
     * @param  array<int,int|null>  $matches
     * @return array{total:int,inserted:int,updated:int,unchanged:int,no_op:bool}
     */
    private function persist(int $branchId, string $stagedPath, array $matches, int $totalRows, float $deadline): array
    {
        try {
            return DB::transaction(function () use ($branchId, $stagedPath, $matches, $totalRows, $deadline): array {
                $this->assertWithinDeadline($deadline);
                $matchedIds = array_values(array_unique(array_filter(
                    $matches,
                    static fn (?int $id): bool => $id !== null,
                )));
                $existing = [];
                foreach (array_chunk($matchedIds, self::WRITE_CHUNK_SIZE) as $ids) {
                    $this->assertWithinDeadline($deadline);
                    foreach (DB::table('orang')
                        ->where('branch_id', $branchId)
                        ->whereIn('id', $ids)
                        ->lockForUpdate()
                        ->get(['id', ...self::IMPORT_COLUMNS]) as $person) {
                        $existing[(int) $person->id] = $person;
                    }
                }

                $handle = @fopen($stagedPath, 'rb');
                if (! is_resource($handle)) {
                    throw new MskImportException('import_stage_failed');
                }

                $inserted = 0;
                $updated = 0;
                $unchanged = 0;
                $chunk = [];
                $chunkBytes = 0;
                try {
                    while (($line = fgets($handle)) !== false) {
                        $this->assertWithinDeadline($deadline);
                        $row = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
                        if (! is_array($row)) {
                            throw new MskImportException('import_stage_failed');
                        }
                        $chunk[] = $row;
                        $chunkBytes += strlen($line);
                        if (count($chunk) < self::WRITE_CHUNK_SIZE && $chunkBytes < self::WRITE_CHUNK_BYTES) {
                            continue;
                        }
                        [$chunkInserted, $chunkUpdated, $chunkUnchanged] = $this->persistChunk($branchId, $chunk, $matches, $existing, $deadline);
                        $inserted += $chunkInserted;
                        $updated += $chunkUpdated;
                        $unchanged += $chunkUnchanged;
                        $chunk = [];
                        $chunkBytes = 0;
                    }
                    if (! feof($handle)) {
                        throw new MskImportException('import_stage_failed');
                    }
                    if ($chunk !== []) {
                        [$chunkInserted, $chunkUpdated, $chunkUnchanged] = $this->persistChunk($branchId, $chunk, $matches, $existing, $deadline);
                        $inserted += $chunkInserted;
                        $updated += $chunkUpdated;
                        $unchanged += $chunkUnchanged;
                    }
                } finally {
                    fclose($handle);
                }

                if (($inserted + $updated + $unchanged) !== $totalRows) {
                    throw new MskImportException('import_stage_failed');
                }

                return [
                    'total' => $totalRows,
                    'inserted' => $inserted,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'no_op' => $inserted === 0 && $updated === 0,
                ];
            });
        } catch (MskImportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw new MskImportException('import_failed');
        }
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  array<int,int|null>  $matches
     * @param  array<int,object>  $existing
     * @return array{0:int,1:int,2:int}
     */
    private function persistChunk(
        int $branchId,
        array $rows,
        array $matches,
        array $existing,
        float $deadline,
    ): array {
        $this->assertWithinDeadline($deadline);
        $now = now();
        $inserts = [];
        $updates = [];
        $unchanged = 0;
        foreach ($rows as $row) {
            $this->assertWithinDeadline($deadline);
            $rowNumber = (int) ($row['row_number'] ?? 0);
            if ($rowNumber < 1 || ! array_key_exists($rowNumber, $matches)) {
                throw new MskImportException('import_stage_failed');
            }
            $matchedId = $matches[$rowNumber];
            $attributes = $this->importAttributes($row);
            if ($matchedId === null) {
                $inserts[] = [
                    'branch_id' => $branchId,
                    ...$attributes,
                    'completed_at' => null,
                    'journey_bridge_status' => 'belum',
                    'status' => 'active',
                    'photos' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                continue;
            }

            $person = $existing[$matchedId] ?? null;
            if (! is_object($person)) {
                throw new MskImportException('import_conflict', [
                    'row' => $rowNumber,
                    'participant_id' => $matchedId,
                ]);
            }
            if ($this->attributesAreUnchanged($person, $attributes)) {
                $unchanged++;

                continue;
            }
            $updates[] = [
                'id' => $matchedId,
                // SQLite/MySQL validate the INSERT side of UPSERT before the
                // primary-key conflict is applied. Supply the required branch
                // without allowing it to be changed by the update clause.
                'branch_id' => $branchId,
                ...$attributes,
                'updated_at' => $now,
            ];
        }

        if ($updates !== []) {
            DB::table('orang')->upsert($updates, ['id'], [...self::IMPORT_COLUMNS, 'updated_at']);
        }
        if ($inserts !== []) {
            DB::table('orang')->insert($inserts);
        }

        return [count($inserts), count($updates), $unchanged];
    }

    private function assertWithinDeadline(float $deadline): void
    {
        if (microtime(true) >= $deadline) {
            throw new MskImportException('import_timeout');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function importAttributes(array $row): array
    {
        return [
            'full_name' => PersonNameNormalizer::normalize(
                isset($row['full_name']) ? (string) $row['full_name'] : null,
            ),
            'gender' => $this->nullableString($row['gender'] ?? null),
            'birth_date' => $this->nullableString($row['birth_date'] ?? null),
            'birth_place' => $this->nullableString($row['birth_place'] ?? null),
            'address' => $this->nullableString($row['address'] ?? null),
            'email' => $this->nullableString($row['email'] ?? null),
            'whatsapp' => $this->nullableString($row['whatsapp'] ?? null),
            'batch_month' => $this->nullableString($row['batch_month'] ?? null),
            'notes' => $this->nullableString($row['notes'] ?? null),
            'session_numbers' => json_encode(
                normalize_msk_session_numbers($row['session_numbers'] ?? []),
                JSON_THROW_ON_ERROR,
            ),
        ];
    }

    /** @param array<string,mixed> $attributes */
    private function attributesAreUnchanged(object $person, array $attributes): bool
    {
        foreach (self::IMPORT_COLUMNS as $column) {
            if ($column === 'session_numbers') {
                $current = json_decode((string) ($person->{$column} ?? '[]'), true);
                if (normalize_msk_session_numbers(is_array($current) ? $current : [])
                    !== normalize_msk_session_numbers(json_decode((string) $attributes[$column], true) ?: [])) {
                    return false;
                }

                continue;
            }
            if ($this->nullableString($person->{$column} ?? null) !== $attributes[$column]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int,array<string,mixed>>  $errors
     * @param  array<string,mixed>  $error
     */
    private function addPreflightError(array &$errors, int &$errorCount, array $error): void
    {
        $errorCount++;
        if (count($errors) < (int) config('msk_import.max_errors', 100)) {
            $errors[] = $error;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
