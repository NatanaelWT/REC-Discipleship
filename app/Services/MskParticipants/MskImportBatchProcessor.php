<?php

namespace App\Services\MskParticipants;

use App\Models\MskImportJob;
use App\Models\Person;
use App\Services\Mutation\MutationLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MskImportBatchProcessor
{
    public function __construct(private readonly MutationLifecycle $lifecycle) {}

    /** @return array<string,mixed> */
    public function process(MskImportJob $requestedJob, string $batchToken): array
    {
        $batchToken = trim($batchToken) ?: (string) Str::ulid();
        $job = MskImportJob::query()->findOrFail($requestedJob->getKey());
        $recorded = $this->recordedBatchResult($job, $batchToken);
        if ($recorded !== null) {
            return $recorded;
        }
        if ($job->last_batch_token === $batchToken && is_array($job->last_batch_result)) {
            return $job->last_batch_result;
        }
        if ($job->isTerminal()) {
            return $this->status($job);
        }

        $lockToken = (string) Str::ulid();
        $claimed = DB::transaction(function () use ($job, $lockToken, $batchToken): ?MskImportJob {
            $locked = MskImportJob::query()->lockForUpdate()->findOrFail($job->getKey());
            if ($this->recordedBatchResult($locked, $batchToken) !== null) {
                return $locked;
            }
            if ($locked->last_batch_token === $batchToken && is_array($locked->last_batch_result)) {
                return $locked;
            }
            if ($locked->isTerminal()) {
                return $locked;
            }
            $cutoff = now()->subSeconds((int) config('msk_import.lock_seconds', 60));
            if ($locked->lock_token !== null && $locked->locked_at !== null && $locked->locked_at->greaterThan($cutoff)) {
                return null;
            }
            $locked->forceFill([
                'status' => 'running',
                'lock_token' => $lockToken,
                'locked_at' => now(),
                'started_at' => $locked->started_at ?? now(),
            ])->save();

            return $locked;
        });

        if (! $claimed instanceof MskImportJob) {
            return $this->status($job->fresh(), true);
        }
        $recorded = $this->recordedBatchResult($claimed, $batchToken);
        if ($recorded !== null) {
            return $recorded;
        }
        if ($claimed->last_batch_token === $batchToken && is_array($claimed->last_batch_result)) {
            return $claimed->last_batch_result;
        }
        if ($claimed->isTerminal()) {
            return $this->status($claimed);
        }

        try {
            [$rows, $nextByteCursor, $eof] = $this->readRows($claimed);
            $result = DB::transaction(function () use ($claimed, $rows, $nextByteCursor, $eof, $lockToken, $batchToken): array {
                $job = MskImportJob::query()->lockForUpdate()->findOrFail($claimed->getKey());
                $recorded = $this->recordedBatchResult($job, $batchToken);
                if ($recorded !== null) {
                    return $recorded;
                }
                if ($job->last_batch_token === $batchToken && is_array($job->last_batch_result)) {
                    return $job->last_batch_result;
                }
                if ($job->lock_token !== $lockToken) {
                    return $this->status($job, true);
                }

                [$inserted, $updated, $touchedIds] = $this->persistRows($job, $rows);
                if ($touchedIds !== []) {
                    DB::table('msk_import_existing_people')
                        ->where('job_id', $job->getKey())
                        ->whereIn('person_id', $touchedIds)
                        ->update(['touched_at' => now()]);
                }

                $processed = (int) $job->processed_rows + count($rows);
                $completed = $eof || $processed >= (int) $job->total_rows;
                if ($completed) {
                    Person::query()
                        ->where('branch_id', $job->branch_id)
                        ->whereIn('id', DB::table('msk_import_existing_people')
                            ->where('job_id', $job->getKey())
                            ->whereNull('touched_at')
                            ->select('person_id'))
                        ->delete();
                }

                $lastRow = $rows !== [] ? end($rows) : null;
                $job->forceFill([
                    'status' => $completed ? 'completed' : 'running',
                    'active_branch_id' => $completed ? null : (int) $job->branch_id,
                    'staged_byte_cursor' => $nextByteCursor,
                    'row_cursor' => is_array($lastRow) ? (int) ($lastRow['row_number'] ?? $job->row_cursor) : (int) $job->row_cursor,
                    'processed_rows' => $processed,
                    'inserted_rows' => (int) $job->inserted_rows + $inserted,
                    'updated_rows' => (int) $job->updated_rows + $updated,
                    'completed_at' => $completed ? now() : null,
                    'lock_token' => null,
                    'locked_at' => null,
                    'last_batch_token' => $batchToken,
                ]);
                $result = $this->status($job);
                $job->last_batch_result = $result;
                $job->save();
                DB::table('msk_import_batches')->insert([
                    'job_id' => $job->getKey(),
                    'batch_token' => $batchToken,
                    'byte_cursor_before' => (int) $claimed->staged_byte_cursor,
                    'byte_cursor_after' => $nextByteCursor,
                    'row_count' => count($rows),
                    'result' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $result;
            });

            if (($result['status'] ?? '') === 'completed') {
                $this->scheduleFileCleanup($claimed);
            }

            return $result;
        } catch (Throwable $exception) {
            $failed = $this->fail($claimed, $lockToken, $batchToken, $exception);
            $this->scheduleFileCleanup($failed);

            return $this->status($failed);
        }
    }

    /** @return array{0:array<int,array<string,mixed>>,1:int,2:bool} */
    private function readRows(MskImportJob $job): array
    {
        $disk = Storage::disk((string) config('msk_import.disk', 'local'));
        $path = trim((string) $job->staged_path);
        if ($path === '' || ! $disk->exists($path)) {
            throw new \RuntimeException('Staged import file is missing.');
        }
        $absolutePath = $disk->path($path);
        $handle = fopen($absolutePath, 'rb');
        if (! is_resource($handle) || fseek($handle, (int) $job->staged_byte_cursor) !== 0) {
            throw new \RuntimeException('Staged import cursor is invalid.');
        }

        $rows = [];
        $limit = (int) config('msk_import.batch_size', 500);
        $deadline = microtime(true) + (int) config('msk_import.batch_seconds', 8);
        try {
            while (count($rows) < $limit && microtime(true) < $deadline) {
                $line = fgets($handle);
                if ($line === false) {
                    break;
                }
                $row = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($row)) {
                    throw new \RuntimeException('Staged import row is invalid.');
                }
                $rows[] = $row;
            }
            $cursor = ftell($handle);
            if ($cursor === false) {
                throw new \RuntimeException('Staged import cursor could not be read.');
            }
            $eof = $cursor >= (int) filesize($absolutePath);
        } finally {
            fclose($handle);
        }

        return [$rows, $cursor, $eof];
    }

    /** @param array<int,array<string,mixed>> $rows @return array{0:int,1:int,2:array<int,int>} */
    private function persistRows(MskImportJob $job, array $rows): array
    {
        if ($rows === []) {
            return [0, 0, []];
        }
        $participantIds = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) ($row['participant_id'] ?? 0), $rows))));
        $identityKeys = array_values(array_unique(array_filter(array_map(static fn (array $row): string => (string) ($row['identity_key'] ?? ''), $rows))));

        $matchedById = DB::table('msk_import_existing_people')->where('job_id', $job->getKey())->whereIn('person_id', $participantIds)->pluck('person_id', 'person_id');
        $matchedByIdentity = $identityKeys === [] ? collect() : DB::table('msk_import_existing_people')->where('job_id', $job->getKey())->whereIn('identity_key', $identityKeys)->pluck('person_id', 'identity_key');
        $matchedIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row['participant_id'] ?? 0);
            if ($id > 0 && $matchedById->has($id)) {
                $matchedIds[] = $id;
            } elseif (($identity = (string) ($row['identity_key'] ?? '')) !== '' && $matchedByIdentity->has($identity)) {
                $matchedIds[] = (int) $matchedByIdentity->get($identity);
            }
        }
        $existing = Person::query()->where('branch_id', $job->branch_id)->whereIn('id', array_values(array_unique($matchedIds)))->get()->keyBy('id');

        $updates = [];
        $inserts = [];
        $touched = [];
        foreach ($rows as $row) {
            $id = (int) ($row['participant_id'] ?? 0);
            if ($id < 1 && ($identity = (string) ($row['identity_key'] ?? '')) !== '') {
                $id = (int) ($matchedByIdentity->get($identity) ?? 0);
            }
            $person = $id > 0 ? $existing->get($id) : null;
            $attributes = $this->attributes((int) $job->branch_id, $row, $person instanceof Person ? $person : null);
            if ($person instanceof Person) {
                $attributes['id'] = (int) $person->getKey();
                $updates[] = $attributes;
                $touched[] = (int) $person->getKey();
            } else {
                $inserts[] = $attributes;
            }
        }

        if ($updates !== []) {
            DB::table('orang')->upsert($updates, ['id'], [
                'full_name', 'gender', 'birth_date', 'birth_place', 'address', 'email', 'whatsapp',
                'batch_month', 'notes', 'session_numbers', 'updated_at',
            ]);
        }
        if ($inserts !== []) {
            DB::table('orang')->insert($inserts);
        }

        return [count($inserts), count($updates), array_values(array_unique($touched))];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function attributes(int $branchId, array $row, ?Person $existing): array
    {
        $now = now();

        return [
            'branch_id' => $branchId,
            'full_name' => trim((string) ($row['full_name'] ?? '')) ?: null,
            'gender' => trim((string) ($row['gender'] ?? '')) ?: null,
            'birth_date' => trim((string) ($row['birth_date'] ?? '')) ?: null,
            'birth_place' => trim((string) ($row['birth_place'] ?? '')) ?: null,
            'address' => trim((string) ($row['address'] ?? '')) ?: null,
            'email' => trim((string) ($row['email'] ?? '')) ?: null,
            'whatsapp' => trim((string) ($row['whatsapp'] ?? '')) ?: null,
            'batch_month' => trim((string) ($row['batch_month'] ?? '')) ?: null,
            'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
            'completed_at' => $existing?->getRawOriginal('completed_at'),
            'journey_bridge_status' => $existing !== null ? normalize_journey_bridge_status((string) $existing->journey_bridge_status) : 'belum',
            'status' => $existing !== null ? normalize_msk_participant_status((string) $existing->status) : 'active',
            'session_numbers' => json_encode(normalize_msk_session_numbers($row['session_numbers'] ?? []), JSON_THROW_ON_ERROR),
            'photos' => json_encode($existing !== null ? extract_msk_participant_photos($existing->toViewArray()) : [], JSON_THROW_ON_ERROR),
            'created_at' => $existing?->getRawOriginal('created_at') ?? $now,
            'updated_at' => $now,
        ];
    }

    private function fail(MskImportJob $job, string $lockToken, string $batchToken, Throwable $exception): MskImportJob
    {
        return DB::transaction(function () use ($job, $lockToken, $batchToken, $exception): MskImportJob {
            $locked = MskImportJob::query()->lockForUpdate()->findOrFail($job->getKey());
            if ($locked->lock_token !== $lockToken && $locked->lock_token !== null) {
                return $locked;
            }
            $locked->forceFill([
                'status' => 'failed',
                'active_branch_id' => null,
                'errors' => [[
                    'code' => 'batch_failed',
                    'row' => (int) $locked->row_cursor,
                    'message' => 'Batch import gagal dan dapat diperiksa dari status import.',
                ]],
                'completed_at' => now(),
                'lock_token' => null,
                'locked_at' => null,
                'last_batch_token' => $batchToken,
            ]);
            $result = $this->status($locked);
            $locked->last_batch_result = $result;
            $locked->save();
            DB::table('msk_import_batches')->insertOrIgnore([
                'job_id' => $locked->getKey(),
                'batch_token' => $batchToken,
                'byte_cursor_before' => (int) $locked->staged_byte_cursor,
                'byte_cursor_after' => (int) $locked->staged_byte_cursor,
                'row_count' => 0,
                'result' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            report($exception);

            return $locked;
        });
    }

    /** @return array<string,mixed> */
    public function status(?MskImportJob $job, bool $busy = false): array
    {
        if (! $job instanceof MskImportJob) {
            return ['status' => 'failed', 'busy' => false, 'errors' => [['code' => 'job_missing', 'message' => 'Import tidak ditemukan.']]];
        }
        $total = max(0, (int) $job->total_rows);
        $processed = min($total, max(0, (int) $job->processed_rows));

        return [
            'id' => (string) $job->getKey(),
            'status' => (string) $job->status,
            'busy' => $busy,
            'progress' => $total > 0 ? (int) floor(($processed / $total) * 100) : ($job->isTerminal() ? 100 : 0),
            'cursor' => (int) $job->row_cursor,
            'total' => $total,
            'processed' => $processed,
            'inserted' => (int) $job->inserted_rows,
            'updated' => (int) $job->updated_rows,
            'errors' => is_array($job->errors) ? $job->errors : [],
            'terminal' => $job->isTerminal(),
        ];
    }

    private function cleanupFiles(MskImportJob $job): void
    {
        $disk = Storage::disk((string) config('msk_import.disk', 'local'));
        foreach ([$job->source_path, $job->staged_path] as $path) {
            $path = trim((string) $path);
            if ($path !== '') {
                $disk->delete($path);
            }
        }
    }

    private function scheduleFileCleanup(MskImportJob $job): void
    {
        if (! $this->lifecycle->active()) {
            $this->cleanupFiles($job);

            return;
        }

        $this->lifecycle->onCommit(fn () => $this->cleanupFiles($job));
    }

    /** @return array<string,mixed>|null */
    private function recordedBatchResult(MskImportJob $job, string $batchToken): ?array
    {
        $value = DB::table('msk_import_batches')
            ->where('job_id', $job->getKey())
            ->where('batch_token', $batchToken)
            ->value('result');
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
