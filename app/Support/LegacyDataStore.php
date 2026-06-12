<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LegacyDataStore
{
    private static ?bool $databaseReady = null;

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    private static array $recordCache = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $documentCache = [];

    /**
     * @var array<string, list<string>>
     */
    private static array $columnCache = [];

    /**
     * Runtime file root for uploads, templates, and generated files.
     * Application data itself is stored in the rec_* MySQL tables.
     */
    public static function runtimeRoot(): string
    {
        return storage_path('app/private/rec_runtime');
    }

    public static function prepareRuntime(): void
    {
        self::ensureRuntimeFilesystem();
    }

    public static function registerShutdownSync(): void
    {
        // Data writes are persisted directly to MySQL. No JSON shutdown sync is needed.
    }

    public static function ensureRuntimeFilesystem(): void
    {
        $root = self::runtimeRoot();

        foreach (['templates', 'uploads', 'assets'] as $directory) {
            File::ensureDirectoryExists($root . DIRECTORY_SEPARATOR . $directory);
        }
    }

    public static function databaseReady(): bool
    {
        if (self::$databaseReady !== null) {
            return self::$databaseReady;
        }

        try {
            DB::connection()->getPdo();

            foreach (self::tableNames() as $table) {
                if (! Schema::hasTable($table)) {
                    self::$databaseReady = false;

                    return false;
                }
            }

            self::$databaseReady = true;

            return true;
        } catch (Throwable) {
            self::$databaseReady = false;

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

    public static function hasDocument(string $name): bool
    {
        $definition = self::definition($name);

        return $definition !== null && self::databaseReady();
    }

    public static function readPath(string $path, mixed $default, ?bool &$found = null): mixed
    {
        $found = false;
        $name = self::managedNameFromPath($path);
        if ($name === null || ! self::databaseReady()) {
            return $default;
        }

        $found = true;

        return self::readData($name);
    }

    public static function writePath(string $path, mixed $data): bool
    {
        $name = self::managedNameFromPath($path);
        if ($name === null || ! self::databaseReady()) {
            return false;
        }

        return self::writeData($name, $data);
    }

    public static function readDocumentTable(string $name): array
    {
        $name = self::canonicalName($name);
        $definition = self::definition($name);
        if ($definition === null || $definition['shape'] !== 'document' || ! self::databaseReady()) {
            return self::defaultDocument($name);
        }

        if (isset(self::$documentCache[$name])) {
            return self::$documentCache[$name];
        }

        $records = self::recordsFromTable($name, $definition);
        $branches = self::branchesFromRecords($records);

        self::$documentCache[$name] = [
            'schema_version' => 1,
            'name' => $name,
            'updated_at' => self::latestRuntimeTimestamp($definition['table']),
            'branches' => $branches,
            'records' => $records,
        ];

        return self::$documentCache[$name];
    }

    public static function writeDocumentTable(string $name, array $table): bool
    {
        $name = self::canonicalName($name);
        $definition = self::definition($name);
        if ($definition === null || $definition['shape'] !== 'document' || ! self::databaseReady()) {
            return false;
        }

        return self::storeDocument($name, $definition, [
            'schema_version' => (int) ($table['schema_version'] ?? 1),
            'name' => $name,
            'updated_at' => (string) ($table['updated_at'] ?? ''),
            'branches' => is_array($table['branches'] ?? null) ? $table['branches'] : [],
            'records' => is_array($table['records'] ?? null) ? array_values($table['records']) : [],
        ]);
    }

    private static function readData(string $name): mixed
    {
        $name = self::canonicalName($name);
        $definition = self::definition($name);
        if ($definition === null) {
            return [];
        }

        if ($definition['shape'] === 'document') {
            return self::readDocumentTable($name);
        }

        $records = self::recordsFromTable($name, $definition);
        if ($definition['shape'] !== 'map') {
            return $records;
        }

        $map = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $key = trim((string) ($record['_map_key'] ?? ''));
            unset($record['_map_key']);
            if ($key !== '') {
                $map[$key] = $record;
            }
        }

        return $map;
    }

    private static function writeData(string $name, mixed $data): bool
    {
        $name = self::canonicalName($name);
        $definition = self::definition($name);
        if ($definition === null) {
            return false;
        }

        $payload = is_array($data) ? $data : [];

        if ($definition['shape'] === 'document') {
            return self::storeDocument($name, $definition, $payload);
        }

        return self::storeDocument($name, $definition, $payload);
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

    private static function definition(string $name): ?array
    {
        $name = self::canonicalName($name);

        return self::documentDefinitions()[$name] ?? null;
    }

    private static function canonicalName(string $name): string
    {
        $name = trim($name);

        return [
            'member_msk_unified' => 'people_registry',
            'groups_v2' => 'discipleship_groups',
        ][$name] ?? $name;
    }

    private static function managedNameFromPath(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        $path = explode('?', $path, 2)[0];

        if (! preg_match('~/data/(?:cabang/[^/]+/)?([^/]+)\.json$~', $path, $matches)) {
            return null;
        }

        $name = self::canonicalName($matches[1]);

        return self::definition($name) === null ? null : $name;
    }

    private static function storeDocument(string $name, array $definition, array $payload): bool
    {
        try {
            $rows = self::rowsForDatabase($name, $definition, $payload);
            $table = $definition['table'];
            $columns = self::columnsForTable($table);

            DB::transaction(static function () use ($table, $rows, $columns): void {
                DB::table($table)->delete();

                foreach (array_chunk($rows, 250) as $chunk) {
                    $filteredChunk = array_map(
                        static fn (array $row): array => array_intersect_key($row, array_flip($columns)),
                        $chunk,
                    );

                    if ($filteredChunk !== []) {
                        DB::table($table)->insert($filteredChunk);
                    }
                }
            });

            unset(self::$recordCache[$name], self::$documentCache[$name]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Failed to persist REC data to database.', [
                'name' => $name,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private static function rowsForDatabase(string $name, array $definition, array $payload): array
    {
        $shape = $definition['shape'];
        $now = now();

        if ($shape === 'document') {
            $records = is_array($payload['records'] ?? null) ? array_values($payload['records']) : [];

            return self::recordRows($name, $records, $now);
        }

        if ($shape === 'map') {
            $rows = [];
            foreach ($payload as $key => $record) {
                if (! is_array($record)) {
                    continue;
                }

                $rows[] = array_merge(
                    self::baseRow($record, $now),
                    self::specificColumns($name, $record, (string) $key),
                );
            }

            return $rows;
        }

        $records = array_is_list($payload) ? $payload : array_values($payload);

        return self::recordRows($name, $records, $now);
    }

    private static function recordRows(string $name, array $records, mixed $now): array
    {
        $rows = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $rows[] = array_merge(
                self::baseRow($record, $now),
                self::specificColumns($name, $record, ''),
            );
        }

        return $rows;
    }

    private static function recordsFromTable(string $name, array $definition): array
    {
        if (isset(self::$recordCache[$name])) {
            return self::$recordCache[$name];
        }

        if (! Schema::hasTable($definition['table'])) {
            return [];
        }

        self::$recordCache[$name] = DB::table($definition['table'])
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => self::recordFromRow($name, (array) $row))
            ->values()
            ->all();

        return self::$recordCache[$name];
    }

    private static function columnsForTable(string $table): array
    {
        if (! isset(self::$columnCache[$table])) {
            self::$columnCache[$table] = Schema::getColumnListing($table);
        }

        return self::$columnCache[$table];
    }

    private static function baseRow(array $record, mixed $now): array
    {
        return [
            'branch' => self::branchValue($record),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private static function specificColumns(string $name, array $record, string $mapKey): array
    {
        return match ($name) {
            'users' => [
                'username' => self::requiredString($record['username'] ?? '', 120),
                'password' => self::requiredString($record['password'] ?? '', 255),
                'cabang' => self::requiredString($record['cabang'] ?? 'kutisari', 40),
                'access_scope' => self::requiredString($record['access_scope'] ?? 'branch', 80),
                'last_login_at_legacy' => self::nullableString($record, 'last_login_at', 80),
            ],
            'church_files' => [
                'record_uid' => self::nullableString($record, 'id', 120),
                'title' => self::nullableString($record, 'title', 255),
                'category' => self::nullableString($record, 'category', 120),
                'description' => self::nullableString($record, 'description', 65535),
                'path' => self::nullableString($record, 'path', 500),
                'file_name' => self::nullableString($record, 'file_name', 255),
                'size' => max(0, (int) ($record['size'] ?? 0)),
                'mime' => self::nullableString($record, 'mime', 180),
                'uploaded_at_text' => self::nullableString($record, 'uploaded_at', 80),
                'updated_at_text' => self::nullableString($record, 'updated_at', 80),
            ],
            'people_registry' => [
                'record_uid' => self::nullableString($record, 'id', 120),
                'full_name' => self::nullableString($record, 'full_name', 255),
                'whatsapp' => self::nullableString($record, 'whatsapp', 80),
                'email' => self::nullableString($record, 'email', 255),
                'address' => self::nullableString($record, 'address', 65535),
                'birth_date' => self::nullableString($record, 'birth_date', 40),
                'birth_day_month' => self::nullableString($record, 'birth_day_month', 20),
                'birth_place' => self::nullableString($record, 'birth_place', 120),
                'gender' => self::nullableString($record, 'gender', 40),
                'membership_status' => self::nullableString($record, 'membership_status', 80),
                'left_at' => self::nullableString($record, 'left_at', 80),
                'left_reason' => self::nullableString($record, 'left_reason', 65535),
                'family_ids_json' => self::nullableJson($record, 'family_ids'),
                'photos_json' => self::nullableJson($record, 'photos'),
                'social_media' => self::nullableString($record, 'social_media', 65535),
                'msk_month' => self::nullableString($record, 'msk_month', 20),
                'msk_status' => self::nullableString($record, 'msk_status', 80),
                'msk_completed_at' => self::nullableString($record, 'msk_completed_at', 80),
                'msk_journey_bridge_status' => self::nullableString($record, 'msk_journey_bridge_status', 80),
                'msk_notes' => self::nullableString($record, 'msk_notes', 65535),
                'msk_session_numbers_json' => self::nullableJson($record, 'msk_session_numbers'),
                'dg_person_id' => self::nullableString($record, 'dg_person_id', 120),
                'dg_member_ref' => self::nullableString($record, 'dg_member_ref', 120),
                'dg_status' => self::nullableString($record, 'dg_status', 80),
                'dg_notes' => self::nullableString($record, 'dg_notes', 65535),
                'dg_created_at' => self::nullableString($record, 'dg_created_at', 80),
                'dg_updated_at' => self::nullableString($record, 'dg_updated_at', 80),
                'legacy_dg_person_id' => self::nullableString($record, 'legacy_dg_person_id', 120),
                'legacy_dg_role' => self::nullableString($record, 'legacy_dg_role', 80),
                'legacy_dg_parent_ids_json' => self::nullableJson($record, 'legacy_dg_parent_ids'),
                'legacy_dg_notes' => self::nullableString($record, 'legacy_dg_notes', 65535),
                'legacy_dg_created_at' => self::nullableString($record, 'legacy_dg_created_at', 80),
                'legacy_dg_updated_at' => self::nullableString($record, 'legacy_dg_updated_at', 80),
                'created_at_legacy' => self::nullableString($record, 'created_at', 80),
                'record_updated_at' => self::nullableString($record, 'updated_at', 80),
            ],
            'discipleship_groups' => [
                'record_uid' => self::nullableString($record, 'id', 120),
                'status' => self::nullableString($record, 'status', 80),
                'start_stage' => self::nullableString($record, 'start_stage', 80),
                'current_stage' => self::nullableString($record, 'current_stage', 80),
                'parent_group_id' => self::nullableString($record, 'parent_group_id', 120),
                'notes' => self::nullableString($record, 'notes', 65535),
                'created_at_legacy' => self::nullableString($record, 'created_at', 80),
                'record_updated_at' => self::nullableString($record, 'updated_at', 80),
            ],
            'discipleship_relationships' => [
                'record_uid' => self::nullableString($record, 'id', 120),
                'relationship_kind' => self::nullableString($record, 'relationship_kind', 80),
                'mentor_person_id' => self::nullableString($record, 'mentor_person_id', 120),
                'disciple_person_id' => self::nullableString($record, 'disciple_person_id', 120),
                'initiated_by_person_id' => self::nullableString($record, 'initiated_by_person_id', 120),
                'leader_person_id' => self::nullableString($record, 'leader_person_id', 120),
                'person_id' => self::nullableString($record, 'person_id', 120),
                'group_id' => self::nullableString($record, 'group_id', 120),
                'context_group_id' => self::nullableString($record, 'context_group_id', 120),
                'source_group_id' => self::nullableString($record, 'source_group_id', 120),
                'new_group_id' => self::nullableString($record, 'new_group_id', 120),
                'role' => self::nullableString($record, 'role', 80),
                'relation_type' => self::nullableString($record, 'relation_type', 80),
                'stage' => self::nullableString($record, 'stage', 80),
                'stage_at_start' => self::nullableString($record, 'stage_at_start', 80),
                'status' => self::nullableString($record, 'status', 80),
                'start_date' => self::nullableString($record, 'start_date', 40),
                'end_date' => self::nullableString($record, 'end_date', 40),
                'multiplication_date' => self::nullableString($record, 'multiplication_date', 40),
                'notes' => self::nullableString($record, 'notes', 65535),
                'reason_change' => self::nullableString($record, 'reason_change', 120),
                'reason_close' => self::nullableString($record, 'reason_close', 120),
                'reason_end' => self::nullableString($record, 'reason_end', 120),
                'record_created_at' => self::nullableString($record, 'created_at', 80),
                'record_updated_at' => self::nullableString($record, 'updated_at', 80),
            ],
            'dg_meeting_reports' => [
                'record_uid' => self::nullableString($record, 'id', 120),
                'leader_id' => self::nullableString($record, 'leader_id', 120),
                'group_id' => self::nullableString($record, 'group_id', 120),
                'meeting_date' => self::nullableString($record, 'meeting_date', 40),
                'material_topic' => self::nullableString($record, 'material_topic', 255),
                'group_progress' => self::nullableString($record, 'group_progress', 80),
                'absence_reason' => self::nullableString($record, 'absence_reason', 255),
                'absent_member_ids_json' => self::nullableJson($record, 'absent_member_ids'),
                'additional_notes' => self::nullableString($record, 'additional_notes', 65535),
                'meditation_min_times' => max(0, (int) ($record['meditation_min_times'] ?? 0)),
                'meditation_sharer_ids_json' => self::nullableJson($record, 'meditation_sharer_ids'),
                'meeting_photos_json' => self::nullableJson($record, 'meeting_photos'),
                'quality_pray' => self::nullableScalar($record, 'quality_pray', 80),
                'quality_prepare' => self::nullableScalar($record, 'quality_prepare', 80),
                'quality_relational' => self::nullableScalar($record, 'quality_relational', 80),
                'quality_share_meditation' => self::nullableScalar($record, 'quality_share_meditation', 80),
                'sharing_openness' => max(0, (int) ($record['sharing_openness'] ?? 0)),
                'source' => self::nullableString($record, 'source', 80),
                'created_at_legacy' => self::nullableString($record, 'created_at', 80),
                'record_updated_at' => self::nullableString($record, 'updated_at', 80),
            ],
            'dg_member_feedback_journals' => [
                'record_uid' => self::nullableString($record, 'id', 120),
                'branch_code' => self::nullableString($record, 'branch_code', 40),
                'feedback_session' => isset($record['feedback_session']) ? (int) $record['feedback_session'] : null,
                'leader_id' => self::nullableString($record, 'leader_id', 120),
                'leader_name' => self::nullableString($record, 'leader_name', 255),
                'group_id' => self::nullableString($record, 'group_id', 120),
                'group_label' => self::nullableString($record, 'group_label', 255),
                'group_name' => self::nullableString($record, 'group_name', 255),
                'respondent_person_id' => self::nullableString($record, 'respondent_person_id', 120),
                'respondent_name' => self::nullableString($record, 'respondent_name', 255),
                'group_progress' => self::nullableString($record, 'group_progress', 80),
                'notes_json' => self::nullableJson($record, 'notes'),
                'ratings_json' => self::nullableJson($record, 'ratings'),
                'source' => self::nullableString($record, 'source', 80),
                'record_created_at' => self::nullableString($record, 'created_at', 80),
                'record_updated_at' => self::nullableString($record, 'updated_at', 80),
            ],
            'discipleship_targets' => [
                'dg_total_people' => max(0, (int) ($record['dg_total_people'] ?? 0)),
                'msk_completed' => max(0, (int) ($record['msk_completed'] ?? 0)),
                'dg1_people' => max(0, (int) ($record['dg1_people'] ?? 0)),
                'dg2_people' => max(0, (int) ($record['dg2_people'] ?? 0)),
                'dg3_people' => max(0, (int) ($record['dg3_people'] ?? 0)),
            ],
            'worship_penatalayan' => [
                'month' => self::requiredString($record['month'] ?? '', 20),
                'title' => self::nullableString($record, 'title', 255),
                'update_note' => self::nullableString($record, 'update_note', 65535),
                'rows_payload' => self::nullableJson($record, 'rows') ?? '[]',
                'created_at_legacy' => self::nullableString($record, 'created_at', 80),
                'record_updated_at' => self::nullableString($record, 'updated_at', 80),
            ],
            'login_attempts' => [
                'attempt_key' => self::requiredString($mapKey, 120),
                'attempt_count' => max(0, (int) ($record['count'] ?? 0)),
                'window_start_epoch' => max(0, (int) ($record['window_start'] ?? 0)),
                'lock_until_epoch' => max(0, (int) ($record['lock_until'] ?? 0)),
                'last_epoch' => max(0, (int) ($record['last'] ?? 0)),
            ],
            'difficult_questions' => [
                'record_uid' => self::nullableString($record, 'id', 120),
                'asker_name' => self::nullableString($record, 'asker_name', 255),
                'question' => self::nullableString($record, 'question', 0),
                'password_hash' => self::nullableString($record, 'password_hash', 255),
                'password_lookup' => self::nullableString($record, 'password_lookup', 128),
                'status' => self::requiredString($record['status'] ?? 'pending', 80),
                'answer' => self::nullableString($record, 'answer', 0),
                'answered_by' => self::nullableString($record, 'answered_by', 120),
                'created_at_legacy' => self::nullableString($record, 'created_at', 80),
                'answered_at_legacy' => self::nullableString($record, 'answered_at', 80),
                'record_updated_at' => self::nullableString($record, 'updated_at', 80),
            ],
            default => [],
        };
    }

    private static function recordFromRow(string $name, array $row): array
    {
        $record = [];
        self::putIfPresent($record, 'cabang', self::value($row, 'branch'));

        match ($name) {
            'users' => self::appendValues($record, [
                'username' => self::value($row, 'username'),
                'password' => self::value($row, 'password'),
                'cabang' => self::value($row, 'cabang') ?? self::value($row, 'branch'),
                'access_scope' => self::value($row, 'access_scope'),
                'last_login_at' => self::value($row, 'last_login_at_legacy'),
            ]),
            'church_files' => self::appendValues($record, [
                'id' => self::value($row, 'record_uid'),
                'title' => self::value($row, 'title'),
                'category' => self::value($row, 'category'),
                'description' => self::value($row, 'description'),
                'path' => self::value($row, 'path'),
                'file_name' => self::value($row, 'file_name'),
                'size' => max(0, (int) ($row['size'] ?? 0)),
                'mime' => self::value($row, 'mime'),
                'uploaded_at' => self::value($row, 'uploaded_at_text'),
                'updated_at' => self::value($row, 'updated_at_text'),
            ]),
            'people_registry' => self::appendValues($record, [
                'id' => self::value($row, 'record_uid'),
                'full_name' => self::value($row, 'full_name'),
                'whatsapp' => self::value($row, 'whatsapp'),
                'email' => self::value($row, 'email'),
                'address' => self::value($row, 'address'),
                'birth_date' => self::value($row, 'birth_date'),
                'birth_day_month' => self::value($row, 'birth_day_month'),
                'birth_place' => self::value($row, 'birth_place'),
                'gender' => self::value($row, 'gender'),
                'membership_status' => self::value($row, 'membership_status'),
                'left_at' => self::value($row, 'left_at'),
                'left_reason' => self::value($row, 'left_reason'),
                'family_ids' => self::jsonValue($row, 'family_ids_json'),
                'photos' => self::jsonValue($row, 'photos_json'),
                'social_media' => self::value($row, 'social_media'),
                'msk_month' => self::value($row, 'msk_month'),
                'msk_status' => self::value($row, 'msk_status'),
                'msk_completed_at' => self::value($row, 'msk_completed_at'),
                'msk_journey_bridge_status' => self::value($row, 'msk_journey_bridge_status'),
                'msk_notes' => self::value($row, 'msk_notes'),
                'msk_session_numbers' => self::jsonValue($row, 'msk_session_numbers_json'),
                'dg_person_id' => self::value($row, 'dg_person_id'),
                'dg_member_ref' => self::value($row, 'dg_member_ref'),
                'dg_status' => self::value($row, 'dg_status'),
                'dg_notes' => self::value($row, 'dg_notes'),
                'dg_created_at' => self::value($row, 'dg_created_at'),
                'dg_updated_at' => self::value($row, 'dg_updated_at'),
                'legacy_dg_person_id' => self::value($row, 'legacy_dg_person_id'),
                'legacy_dg_role' => self::value($row, 'legacy_dg_role'),
                'legacy_dg_parent_ids' => self::jsonValue($row, 'legacy_dg_parent_ids_json'),
                'legacy_dg_notes' => self::value($row, 'legacy_dg_notes'),
                'legacy_dg_created_at' => self::value($row, 'legacy_dg_created_at'),
                'legacy_dg_updated_at' => self::value($row, 'legacy_dg_updated_at'),
                'created_at' => self::value($row, 'created_at_legacy'),
                'updated_at' => self::value($row, 'record_updated_at'),
            ]),
            'discipleship_groups' => self::appendValues($record, [
                'id' => self::value($row, 'record_uid'),
                'status' => self::value($row, 'status'),
                'start_stage' => self::value($row, 'start_stage'),
                'current_stage' => self::value($row, 'current_stage'),
                'parent_group_id' => self::value($row, 'parent_group_id'),
                'notes' => self::value($row, 'notes'),
                'created_at' => self::value($row, 'created_at_legacy'),
                'updated_at' => self::value($row, 'record_updated_at'),
            ]),
            'discipleship_relationships' => self::appendValues($record, [
                'relationship_kind' => self::value($row, 'relationship_kind'),
                'id' => self::value($row, 'record_uid'),
                'mentor_person_id' => self::value($row, 'mentor_person_id'),
                'disciple_person_id' => self::value($row, 'disciple_person_id'),
                'initiated_by_person_id' => self::value($row, 'initiated_by_person_id'),
                'leader_person_id' => self::value($row, 'leader_person_id'),
                'person_id' => self::value($row, 'person_id'),
                'group_id' => self::value($row, 'group_id'),
                'context_group_id' => self::value($row, 'context_group_id'),
                'source_group_id' => self::value($row, 'source_group_id'),
                'new_group_id' => self::value($row, 'new_group_id'),
                'role' => self::value($row, 'role'),
                'relation_type' => self::value($row, 'relation_type'),
                'stage' => self::value($row, 'stage'),
                'stage_at_start' => self::value($row, 'stage_at_start'),
                'status' => self::value($row, 'status'),
                'start_date' => self::value($row, 'start_date'),
                'end_date' => self::value($row, 'end_date'),
                'multiplication_date' => self::value($row, 'multiplication_date'),
                'notes' => self::value($row, 'notes'),
                'reason_change' => self::value($row, 'reason_change'),
                'reason_close' => self::value($row, 'reason_close'),
                'reason_end' => self::value($row, 'reason_end'),
                'created_at' => self::value($row, 'record_created_at'),
                'updated_at' => self::value($row, 'record_updated_at'),
            ]),
            'dg_meeting_reports' => self::appendValues($record, [
                'id' => self::value($row, 'record_uid'),
                'leader_id' => self::value($row, 'leader_id'),
                'group_id' => self::value($row, 'group_id'),
                'meeting_date' => self::value($row, 'meeting_date'),
                'material_topic' => self::value($row, 'material_topic'),
                'absent_member_ids' => self::jsonValue($row, 'absent_member_ids_json'),
                'absence_reason' => self::value($row, 'absence_reason'),
                'quality_prepare' => self::scalarValue($row, 'quality_prepare'),
                'quality_pray' => self::scalarValue($row, 'quality_pray'),
                'quality_share_meditation' => self::scalarValue($row, 'quality_share_meditation'),
                'quality_relational' => self::scalarValue($row, 'quality_relational'),
                'sharing_openness' => max(0, (int) ($row['sharing_openness'] ?? 0)),
                'meditation_sharer_ids' => self::jsonValue($row, 'meditation_sharer_ids_json'),
                'meditation_min_times' => max(0, (int) ($row['meditation_min_times'] ?? 0)),
                'group_progress' => self::value($row, 'group_progress'),
                'additional_notes' => self::value($row, 'additional_notes'),
                'meeting_photos' => self::jsonValue($row, 'meeting_photos_json'),
                'source' => self::value($row, 'source'),
                'created_at' => self::value($row, 'created_at_legacy'),
                'updated_at' => self::value($row, 'record_updated_at'),
            ]),
            'dg_member_feedback_journals' => self::appendValues($record, [
                'id' => self::value($row, 'record_uid'),
                'branch_code' => self::value($row, 'branch_code'),
                'feedback_session' => isset($row['feedback_session']) ? (int) $row['feedback_session'] : null,
                'leader_id' => self::value($row, 'leader_id'),
                'leader_name' => self::value($row, 'leader_name'),
                'group_id' => self::value($row, 'group_id'),
                'group_name' => self::value($row, 'group_name'),
                'group_label' => self::value($row, 'group_label'),
                'group_progress' => self::value($row, 'group_progress'),
                'respondent_person_id' => self::value($row, 'respondent_person_id'),
                'respondent_name' => self::value($row, 'respondent_name'),
                'ratings' => self::jsonValue($row, 'ratings_json'),
                'notes' => self::jsonValue($row, 'notes_json'),
                'source' => self::value($row, 'source'),
                'created_at' => self::value($row, 'record_created_at'),
                'updated_at' => self::value($row, 'record_updated_at'),
            ]),
            'discipleship_targets' => self::appendValues($record, [
                'dg_total_people' => max(0, (int) ($row['dg_total_people'] ?? 0)),
                'msk_completed' => max(0, (int) ($row['msk_completed'] ?? 0)),
                'dg1_people' => max(0, (int) ($row['dg1_people'] ?? 0)),
                'dg2_people' => max(0, (int) ($row['dg2_people'] ?? 0)),
                'dg3_people' => max(0, (int) ($row['dg3_people'] ?? 0)),
            ]),
            'worship_penatalayan' => self::appendValues($record, [
                'month' => self::value($row, 'month'),
                'title' => self::value($row, 'title'),
                'update_note' => self::value($row, 'update_note'),
                'rows' => self::jsonValue($row, 'rows_payload') ?? [],
                'created_at' => self::value($row, 'created_at_legacy'),
                'updated_at' => self::value($row, 'record_updated_at'),
            ]),
            'login_attempts' => self::appendValues($record, [
                '_map_key' => self::value($row, 'attempt_key'),
                'count' => max(0, (int) ($row['attempt_count'] ?? 0)),
                'window_start' => max(0, (int) ($row['window_start_epoch'] ?? 0)),
                'lock_until' => max(0, (int) ($row['lock_until_epoch'] ?? 0)),
                'last' => max(0, (int) ($row['last_epoch'] ?? 0)),
            ]),
            'difficult_questions' => self::appendValues($record, [
                'id' => self::value($row, 'record_uid'),
                'asker_name' => self::value($row, 'asker_name'),
                'question' => self::value($row, 'question'),
                'password_hash' => self::value($row, 'password_hash'),
                'password_lookup' => self::value($row, 'password_lookup'),
                'status' => self::value($row, 'status'),
                'answer' => self::value($row, 'answer'),
                'answered_by' => self::value($row, 'answered_by'),
                'created_at' => self::value($row, 'created_at_legacy'),
                'answered_at' => self::value($row, 'answered_at_legacy'),
                'updated_at' => self::value($row, 'record_updated_at'),
            ]),
            default => null,
        };

        return $record;
    }

    private static function defaultDocument(string $name): array
    {
        return [
            'schema_version' => 1,
            'name' => self::canonicalName($name),
            'updated_at' => '',
            'branches' => [],
            'records' => [],
        ];
    }

    private static function branchesFromRecords(array $records): array
    {
        $branches = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $branch = strtolower(trim((string) ($record['cabang'] ?? $record['branch_code'] ?? '')));
            if ($branch !== '') {
                $branches[] = $branch;
            }
        }

        $branches = array_values(array_unique($branches));
        sort($branches, SORT_STRING);

        return $branches;
    }

    private static function latestRuntimeTimestamp(string $table): string
    {
        $value = DB::table($table)->max('updated_at');

        return $value === null ? '' : (string) $value;
    }

    private static function branchValue(array $record): ?string
    {
        $branch = $record['cabang'] ?? $record['branch_code'] ?? null;
        if ($branch === null) {
            return null;
        }

        return self::requiredString($branch, 40);
    }

    private static function nullableString(array $record, string $key, int $maxLength): ?string
    {
        if (! array_key_exists($key, $record) || $record[$key] === null) {
            return null;
        }

        return self::requiredString($record[$key], $maxLength);
    }

    private static function nullableScalar(array $record, string $key, int $maxLength): ?string
    {
        if (! array_key_exists($key, $record) || $record[$key] === null) {
            return null;
        }

        $value = $record[$key];
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return self::requiredString($value, $maxLength);
    }

    private static function nullableJson(array $record, string $key): ?string
    {
        if (! array_key_exists($key, $record)) {
            return null;
        }

        return self::encodePayload($record[$key]);
    }

    private static function requiredString(mixed $value, int $maxLength): string
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

    private static function value(array $row, string $column): mixed
    {
        if (! array_key_exists($column, $row) || $row[$column] === null) {
            return null;
        }

        return $row[$column];
    }

    private static function jsonValue(array $row, string $column): mixed
    {
        $value = self::value($row, $column);
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function scalarValue(array $row, string $column): mixed
    {
        $value = self::value($row, $column);
        if ($value === null) {
            return null;
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        return $value;
    }

    private static function appendValues(array &$record, array $values): void
    {
        foreach ($values as $key => $value) {
            self::putIfPresent($record, $key, $value);
        }
    }

    private static function putIfPresent(array &$record, string $key, mixed $value): void
    {
        if ($value !== null) {
            $record[$key] = $value;
        }
    }
}
