<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LegacyDataStore
{
    public static function runtimeRoot(): string
    {
        return storage_path('app/rec_legacy');
    }

    public static function prepareRuntime(): void
    {
        self::ensureRuntimeFilesystem();

        if (! self::databaseReady()) {
            return;
        }

        if (self::allSourceTablesAreEmpty()) {
            self::syncFilesToDatabase();
        }

        self::syncDatabaseToFiles();
    }

    public static function registerShutdownSync(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;

        register_shutdown_function(static function (): void {
            self::syncFilesToDatabaseQuietly();
        });
    }

    public static function ensureRuntimeFilesystem(): void
    {
        $root = self::runtimeRoot();

        foreach (['data', 'templates', 'uploads', 'assets'] as $directory) {
            File::ensureDirectoryExists($root . DIRECTORY_SEPARATOR . $directory);
        }
    }

    public static function syncFilesToDatabase(): int
    {
        if (! self::databaseReady()) {
            return 0;
        }

        self::ensureRuntimeFilesystem();

        $count = 0;
        foreach (self::documentDefinitions() as $name => $definition) {
            $path = self::runtimeDataPath($name);
            if (! is_file($path)) {
                continue;
            }

            $raw = File::get($path);
            $payload = trim($raw) === '' ? [] : json_decode($raw, true);
            if (! is_array($payload)) {
                continue;
            }

            self::storeDocument($name, $definition, $payload, (int) filemtime($path));
            $count++;
        }

        return $count;
    }

    public static function syncDatabaseToFiles(): int
    {
        if (! self::databaseReady()) {
            return 0;
        }

        self::ensureRuntimeFilesystem();

        $count = 0;
        foreach (self::documentDefinitions() as $name => $definition) {
            $payload = self::readDocumentFromTable($name, $definition);
            if ($payload === null) {
                continue;
            }

            $json = self::encodeJson($payload);
            $path = self::runtimeDataPath($name);

            if (is_file($path) && sha1((string) File::get($path)) === sha1($json)) {
                continue;
            }

            File::put($path, $json);
            $count++;
        }

        return $count;
    }

    public static function syncFilesToDatabaseQuietly(): void
    {
        try {
            self::syncFilesToDatabase();
        } catch (Throwable $exception) {
            Log::warning('Failed to sync REC legacy files to source tables.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public static function databaseReady(): bool
    {
        try {
            DB::connection()->getPdo();

            foreach (self::tableNames() as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function tableNames(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $definition): string => $definition['table'],
            self::documentDefinitions(),
        )));
    }

    private static function documentDefinitions(): array
    {
        return [
            'users' => ['table' => 'rec_users', 'shape' => 'list'],
            'church_files' => ['table' => 'rec_church_files', 'shape' => 'list'],
            'people_registry' => ['table' => 'rec_people_registry', 'shape' => 'document'],
            'discipleship_groups' => ['table' => 'rec_discipleship_groups', 'shape' => 'document'],
            'discipleship_relationships' => ['table' => 'rec_discipleship_relationships', 'shape' => 'document'],
            'dg_meeting_reports' => ['table' => 'rec_dg_meeting_reports', 'shape' => 'document'],
            'dg_member_feedback_journals' => ['table' => 'rec_dg_member_feedback_journals', 'shape' => 'document'],
            'discipleship_targets' => ['table' => 'rec_discipleship_targets', 'shape' => 'document'],
            'worship_penatalayan' => ['table' => 'rec_worship_penatalayan_schedules', 'shape' => 'list'],
            'login_attempts' => ['table' => 'rec_login_attempts', 'shape' => 'map'],
            'difficult_questions' => ['table' => 'rec_difficult_questions', 'shape' => 'list'],
        ];
    }

    private static function storeDocument(string $name, array $definition, array $payload, int $mtime): void
    {
        $rows = self::rowsForDatabase($name, $definition, $payload, $mtime);
        $table = $definition['table'];

        DB::transaction(static function () use ($table, $rows): void {
            DB::table($table)->delete();

            foreach (array_chunk($rows, 250) as $chunk) {
                if ($chunk !== []) {
                    DB::table($table)->insert($chunk);
                }
            }
        });
    }

    private static function rowsForDatabase(string $name, array $definition, array $payload, int $mtime): array
    {
        $shape = $definition['shape'];
        $sourceUpdatedAt = date('Y-m-d H:i:s', $mtime);
        $now = now();

        if ($shape === 'document') {
            $records = is_array($payload['records'] ?? null) ? array_values($payload['records']) : [];
            $branches = is_array($payload['branches'] ?? null) ? $payload['branches'] : [];
            $meta = [
                'document_schema_version' => isset($payload['schema_version']) ? (int) $payload['schema_version'] : null,
                'document_name' => self::stringValue($payload['name'] ?? $name, 120),
                'document_updated_at' => self::stringValue($payload['updated_at'] ?? '', 80),
                'document_branches' => self::encodePayload($branches),
            ];

            $rows = [];
            foreach ($records as $index => $record) {
                if (! is_array($record)) {
                    continue;
                }

                $rows[] = array_merge(
                    self::baseRow($record, $index, $sourceUpdatedAt, $now),
                    $meta,
                    self::specificColumns($name, $record, ''),
                );
            }

            return $rows;
        }

        if ($shape === 'map') {
            $rows = [];
            foreach ($payload as $key => $record) {
                if (! is_array($record)) {
                    continue;
                }

                $rows[] = array_merge(
                    self::baseRow($record, count($rows), $sourceUpdatedAt, $now, (string) $key),
                    self::specificColumns($name, $record, (string) $key),
                );
            }

            return $rows;
        }

        $records = array_is_list($payload) ? $payload : array_values($payload);
        $rows = [];
        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                continue;
            }

            $rows[] = array_merge(
                self::baseRow($record, $index, $sourceUpdatedAt, $now),
                self::specificColumns($name, $record, ''),
            );
        }

        return $rows;
    }

    private static function readDocumentFromTable(string $name, array $definition): ?array
    {
        $rows = DB::table($definition['table'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return self::emptyPayloadFor($name, $definition);
        }

        if ($definition['shape'] === 'document') {
            $first = $rows->first();
            $records = [];
            foreach ($rows as $row) {
                $decoded = self::decodePayload((string) $row->payload);
                if (is_array($decoded)) {
                    $records[] = $decoded;
                }
            }

            return [
                'schema_version' => isset($first->document_schema_version) ? (int) $first->document_schema_version : 1,
                'name' => (string) ($first->document_name ?? $name),
                'updated_at' => (string) ($first->document_updated_at ?? ''),
                'branches' => self::decodePayload((string) ($first->document_branches ?? '[]')),
                'records' => $records,
            ];
        }

        if ($definition['shape'] === 'map') {
            $records = [];
            foreach ($rows as $row) {
                $decoded = self::decodePayload((string) $row->payload);
                if (is_array($decoded)) {
                    $records[(string) $row->attempt_key] = $decoded;
                }
            }

            return $records;
        }

        $records = [];
        foreach ($rows as $row) {
            $decoded = self::decodePayload((string) $row->payload);
            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return $records;
    }

    private static function emptyPayloadFor(string $name, array $definition): array
    {
        if ($definition['shape'] === 'document') {
            return [
                'schema_version' => 1,
                'name' => $name,
                'updated_at' => '',
                'branches' => [],
                'records' => [],
            ];
        }

        return [];
    }

    private static function baseRow(array $record, int $sortOrder, string $sourceUpdatedAt, mixed $now, string $mapKey = ''): array
    {
        $legacyId = self::legacyId($record, $mapKey);

        return [
            'sort_order' => $sortOrder,
            'legacy_id' => $legacyId,
            'branch' => self::branchValue($record),
            'payload' => self::encodePayload($record),
            'payload_checksum' => sha1(self::encodePayload($record)),
            'source_updated_at' => $sourceUpdatedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private static function specificColumns(string $name, array $record, string $mapKey): array
    {
        return match ($name) {
            'users' => [
                'username' => self::stringValue($record['username'] ?? '', 120),
                'password' => self::stringValue($record['password'] ?? '', 255),
                'cabang' => self::stringValue($record['cabang'] ?? 'kutisari', 40),
                'access_scope' => self::stringValue($record['access_scope'] ?? 'branch', 80),
                'last_login_at_legacy' => self::stringValue($record['last_login_at'] ?? '', 80),
            ],
            'church_files' => [
                'title' => self::stringValue($record['title'] ?? '', 255),
                'category' => self::stringValue($record['category'] ?? '', 120),
                'description' => self::stringValue($record['description'] ?? '', 65535),
                'path' => self::stringValue($record['path'] ?? '', 500),
                'file_name' => self::stringValue($record['file_name'] ?? '', 255),
                'size' => max(0, (int) ($record['size'] ?? 0)),
                'mime' => self::stringValue($record['mime'] ?? '', 180),
                'uploaded_at_legacy' => self::stringValue($record['uploaded_at'] ?? '', 80),
                'updated_at_legacy' => self::stringValue($record['updated_at'] ?? '', 80),
            ],
            'people_registry' => [
                'full_name' => self::stringValue($record['full_name'] ?? '', 255),
                'whatsapp' => self::stringValue($record['whatsapp'] ?? '', 80),
                'email' => self::stringValue($record['email'] ?? '', 255),
                'membership_status' => self::stringValue($record['membership_status'] ?? '', 80),
                'msk_month' => self::stringValue($record['msk_month'] ?? '', 20),
                'created_at_legacy' => self::stringValue($record['created_at'] ?? '', 80),
                'updated_at_legacy' => self::stringValue($record['updated_at'] ?? '', 80),
            ],
            'discipleship_groups' => [
                'status' => self::stringValue($record['status'] ?? '', 80),
                'start_stage' => self::stringValue($record['start_stage'] ?? '', 80),
                'current_stage' => self::stringValue($record['current_stage'] ?? '', 80),
                'parent_group_id' => self::stringValue($record['parent_group_id'] ?? '', 120),
                'notes' => self::stringValue($record['notes'] ?? '', 65535),
                'created_at_legacy' => self::stringValue($record['created_at'] ?? '', 80),
                'updated_at_legacy' => self::stringValue($record['updated_at'] ?? '', 80),
            ],
            'discipleship_relationships' => [
                'relationship_kind' => self::stringValue($record['relationship_kind'] ?? '', 80),
                'mentor_person_id' => self::stringValue($record['mentor_person_id'] ?? '', 120),
                'disciple_person_id' => self::stringValue($record['disciple_person_id'] ?? '', 120),
                'person_id' => self::stringValue($record['person_id'] ?? '', 120),
                'group_id' => self::stringValue($record['group_id'] ?? '', 120),
                'context_group_id' => self::stringValue($record['context_group_id'] ?? '', 120),
                'role' => self::stringValue($record['role'] ?? '', 80),
                'stage' => self::stringValue($record['stage'] ?? $record['stage_at_start'] ?? '', 80),
                'status' => self::stringValue($record['status'] ?? '', 80),
                'start_date' => self::stringValue($record['start_date'] ?? '', 40),
                'end_date' => self::stringValue($record['end_date'] ?? '', 40),
            ],
            'dg_meeting_reports' => [
                'leader_id' => self::stringValue($record['leader_id'] ?? '', 120),
                'group_id' => self::stringValue($record['group_id'] ?? '', 120),
                'meeting_date' => self::stringValue($record['meeting_date'] ?? '', 40),
                'material_topic' => self::stringValue($record['material_topic'] ?? '', 255),
                'group_progress' => self::stringValue($record['group_progress'] ?? '', 80),
                'source' => self::stringValue($record['source'] ?? '', 80),
                'created_at_legacy' => self::stringValue($record['created_at'] ?? '', 80),
                'updated_at_legacy' => self::stringValue($record['updated_at'] ?? '', 80),
            ],
            'dg_member_feedback_journals' => [
                'branch_code' => self::stringValue($record['branch_code'] ?? $record['cabang'] ?? '', 40),
                'feedback_session' => isset($record['feedback_session']) ? (int) $record['feedback_session'] : null,
                'leader_id' => self::stringValue($record['leader_id'] ?? '', 120),
                'group_id' => self::stringValue($record['group_id'] ?? '', 120),
                'respondent_person_id' => self::stringValue($record['respondent_person_id'] ?? '', 120),
                'respondent_name' => self::stringValue($record['respondent_name'] ?? '', 255),
                'group_progress' => self::stringValue($record['group_progress'] ?? '', 80),
                'source' => self::stringValue($record['source'] ?? '', 80),
            ],
            'discipleship_targets' => [
                'dg_total_people' => max(0, (int) ($record['dg_total_people'] ?? 0)),
                'msk_completed' => max(0, (int) ($record['msk_completed'] ?? 0)),
                'dg1_people' => max(0, (int) ($record['dg1_people'] ?? 0)),
                'dg2_people' => max(0, (int) ($record['dg2_people'] ?? 0)),
                'dg3_people' => max(0, (int) ($record['dg3_people'] ?? 0)),
            ],
            'worship_penatalayan' => [
                'month' => self::stringValue($record['month'] ?? '', 20),
                'title' => self::stringValue($record['title'] ?? '', 255),
                'update_note' => self::stringValue($record['update_note'] ?? '', 65535),
                'rows_payload' => self::encodePayload(is_array($record['rows'] ?? null) ? $record['rows'] : []),
                'created_at_legacy' => self::stringValue($record['created_at'] ?? '', 80),
                'updated_at_legacy' => self::stringValue($record['updated_at'] ?? '', 80),
            ],
            'login_attempts' => [
                'attempt_key' => self::stringValue($mapKey, 120),
                'attempt_count' => max(0, (int) ($record['count'] ?? 0)),
                'window_start_epoch' => max(0, (int) ($record['window_start'] ?? 0)),
                'lock_until_epoch' => max(0, (int) ($record['lock_until'] ?? 0)),
                'last_epoch' => max(0, (int) ($record['last'] ?? 0)),
            ],
            'difficult_questions' => [
                'asker_name' => self::stringValue($record['asker_name'] ?? '', 255),
                'password_lookup' => self::stringValue($record['password_lookup'] ?? '', 128),
                'status' => self::stringValue($record['status'] ?? 'pending', 80),
                'answered_by' => self::stringValue($record['answered_by'] ?? '', 120),
                'created_at_legacy' => self::stringValue($record['created_at'] ?? '', 80),
                'answered_at_legacy' => self::stringValue($record['answered_at'] ?? '', 80),
                'updated_at_legacy' => self::stringValue($record['updated_at'] ?? '', 80),
            ],
            default => [],
        };
    }

    private static function allSourceTablesAreEmpty(): bool
    {
        foreach (self::tableNames() as $table) {
            if (DB::table($table)->limit(1)->exists()) {
                return false;
            }
        }

        return true;
    }

    private static function runtimeDataPath(string $name): string
    {
        return self::runtimeRoot() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $name . '.json';
    }

    private static function legacyId(array $record, string $mapKey): string
    {
        if ($mapKey !== '') {
            return self::stringValue($mapKey, 120);
        }

        foreach (['id', 'username', 'month', 'path'] as $key) {
            $value = self::stringValue($record[$key] ?? '', 120);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function branchValue(array $record): string
    {
        return self::stringValue($record['cabang'] ?? $record['branch_code'] ?? '', 40);
    }

    private static function stringValue(mixed $value, int $maxLength): string
    {
        if (is_array($value) || is_object($value)) {
            $value = self::encodePayload((array) $value);
        }

        $text = trim((string) $value);
        if ($maxLength > 0 && strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength);
        }

        return $text;
    }

    private static function encodePayload(mixed $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '[]';
    }

    private static function decodePayload(string $payload): mixed
    {
        if (trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function copyDirectoryIfEmpty(string $source, string $target): void
    {
        if (! is_dir($source)) {
            return;
        }

        File::ensureDirectoryExists($target);

        if (count(File::allFiles($target)) > 0) {
            return;
        }

        File::copyDirectory($source, $target);
    }

    private static function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json . PHP_EOL : '[]' . PHP_EOL;
    }
}
