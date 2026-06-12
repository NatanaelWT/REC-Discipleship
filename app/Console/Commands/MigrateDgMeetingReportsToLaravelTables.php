<?php

namespace App\Console\Commands;

use App\Support\LegacyRuntimeBootstrap;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MigrateDgMeetingReportsToLaravelTables extends Command
{
    protected $signature = 'rec:migrate-dg-meeting-reports {--dry-run : Count rows without writing}';

    protected $description = 'Migrate public DG meeting reports into normalized Laravel tables.';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $runtimeByBranch = [];

    public function handle(): int
    {
        LegacyRuntimeBootstrap::load();

        foreach ([
            'rec_dg_meeting_reports',
            'discipleship_meeting_reports',
            'discipleship_meeting_report_absences',
            'discipleship_meeting_report_meditation_sharers',
            'discipleship_meeting_report_photos',
        ] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                $this->error("Required table {$tableName} does not exist. Run migrations first.");

                return self::FAILURE;
            }
        }

        $rows = DB::table('rec_dg_meeting_reports')->orderBy('id')->get();
        if ($this->option('dry-run')) {
            $this->info('DG meeting reports ready to migrate: ' . $rows->count());

            return self::SUCCESS;
        }

        $personIdsByPublicId = $this->personIdsByPublicId();
        $groupIdsByPublicId = $this->groupIdsByPublicId();
        $counts = [
            'reports' => 0,
            'absences' => 0,
            'sharers' => 0,
            'photos' => 0,
        ];

        DB::transaction(function () use ($rows, $personIdsByPublicId, $groupIdsByPublicId, &$counts): void {
            foreach ($rows as $row) {
                $this->migrateRow($row, $personIdsByPublicId, $groupIdsByPublicId, $counts);
            }
        });

        $this->info("Migrated {$counts['reports']} DG meeting reports.");
        $this->info("Created {$counts['absences']} absence rows, {$counts['sharers']} meditation sharer rows, {$counts['photos']} photo rows.");

        return self::SUCCESS;
    }

    /**
     * @param array<string, int> $personIdsByPublicId
     * @param array<string, int> $groupIdsByPublicId
     * @param array{reports: int, absences: int, sharers: int, photos: int} $counts
     */
    private function migrateRow(
        object $row,
        array $personIdsByPublicId,
        array $groupIdsByPublicId,
        array &$counts,
    ): void {
        $branch = $this->branch($row->branch ?? 'kutisari');
        $runtime = $this->runtimeForBranch($branch);
        $peopleById = is_array($runtime['people_by_id'] ?? null) ? $runtime['people_by_id'] : [];
        $groupsById = is_array($runtime['groups_by_id'] ?? null) ? $runtime['groups_by_id'] : [];

        $publicId = $this->publicId($row);
        $leaderPublicId = $this->nullableString($row->leader_id ?? null);
        $groupPublicId = $this->nullableString($row->group_id ?? null);
        $groupRow = $groupPublicId !== null && isset($groupsById[$groupPublicId]) && is_array($groupsById[$groupPublicId])
            ? $groupsById[$groupPublicId]
            : [];

        $leaderName = $leaderPublicId === null ? null : $this->personName($leaderPublicId, $peopleById);
        if ($leaderName === null) {
            $leaderName = $this->nullableString($groupRow['leader_name'] ?? null);
        }

        $groupName = $this->nullableString($groupRow['name'] ?? null) ?? 'Kelompok';
        $groupProgress = normalize_dg_progress_value((string) ($row->group_progress ?? ''));
        if ($groupProgress === '') {
            $groupProgress = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
        }
        if ($groupProgress === '') {
            $groupProgress = 'DG 1';
        }

        $createdAt = $this->timestampFrom([
            $row->created_at_legacy ?? null,
            $row->created_at ?? null,
        ]);
        $updatedAt = $this->timestampFrom([
            $row->record_updated_at ?? null,
            $row->updated_at ?? null,
            $createdAt,
        ]);

        DB::table('discipleship_meeting_reports')->updateOrInsert(
            ['public_id' => $publicId],
            [
                'branch_code' => $branch,
                'leader_person_id' => $this->mappedId($personIdsByPublicId, $leaderPublicId),
                'leader_person_public_id' => $leaderPublicId,
                'leader_name_snapshot' => $leaderName,
                'discipleship_group_id' => $this->mappedId($groupIdsByPublicId, $groupPublicId),
                'discipleship_group_public_id' => $groupPublicId,
                'group_name_snapshot' => $groupName,
                'meeting_date' => $this->dateValue($row->meeting_date ?? null),
                'material_topic' => $this->nullableString($row->material_topic ?? null),
                'group_progress_snapshot' => $groupProgress,
                'absence_reason' => $this->nullableString($row->absence_reason ?? null),
                'additional_notes' => $this->nullableString($row->additional_notes ?? null),
                'meditation_min_times' => max(0, (int) ($row->meditation_min_times ?? 0)),
                'sharing_openness_score' => $this->sharingOpenness($row->sharing_openness ?? null),
                'prepared_material' => parse_bool_value($row->quality_prepare ?? false),
                'prayed_for_members' => parse_bool_value($row->quality_pray ?? false),
                'shared_meditation' => parse_bool_value($row->quality_share_meditation ?? false),
                'relationally_contacted' => parse_bool_value($row->quality_relational ?? false),
                'source' => $this->nullableString($row->source ?? null) ?? 'public_form',
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ],
        );

        $reportId = (int) DB::table('discipleship_meeting_reports')
            ->where('public_id', $publicId)
            ->value('id');

        DB::table('discipleship_meeting_report_absences')
            ->where('discipleship_meeting_report_id', $reportId)
            ->delete();
        DB::table('discipleship_meeting_report_meditation_sharers')
            ->where('discipleship_meeting_report_id', $reportId)
            ->delete();
        DB::table('discipleship_meeting_report_photos')
            ->where('discipleship_meeting_report_id', $reportId)
            ->delete();

        $counts['absences'] += $this->insertPersonRows(
            'discipleship_meeting_report_absences',
            $reportId,
            $this->jsonList($row->absent_member_ids_json ?? null),
            $personIdsByPublicId,
            $peopleById,
            $createdAt,
            $updatedAt,
        );

        $counts['sharers'] += $this->insertPersonRows(
            'discipleship_meeting_report_meditation_sharers',
            $reportId,
            $this->jsonList($row->meditation_sharer_ids_json ?? null),
            $personIdsByPublicId,
            $peopleById,
            $createdAt,
            $updatedAt,
        );

        $counts['photos'] += $this->insertPhotoRows(
            $reportId,
            $this->jsonList($row->meeting_photos_json ?? null),
            $createdAt,
            $updatedAt,
        );

        $counts['reports']++;
    }

    /**
     * @param array<int, string> $personPublicIds
     * @param array<string, int> $personIdsByPublicId
     * @param array<string, array<string, mixed>> $peopleById
     */
    private function insertPersonRows(
        string $table,
        int $reportId,
        array $personPublicIds,
        array $personIdsByPublicId,
        array $peopleById,
        string $createdAt,
        string $updatedAt,
    ): int {
        $rows = [];
        foreach ($this->uniqueStrings($personPublicIds) as $personPublicId) {
            $rows[] = [
                'discipleship_meeting_report_id' => $reportId,
                'person_id' => $this->mappedId($personIdsByPublicId, $personPublicId),
                'person_public_id' => $personPublicId,
                'person_name_snapshot' => $this->personName($personPublicId, $peopleById),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        if ($rows !== []) {
            DB::table($table)->insert($rows);
        }

        return count($rows);
    }

    /**
     * @param array<int, mixed> $photos
     */
    private function insertPhotoRows(int $reportId, array $photos, string $createdAt, string $updatedAt): int
    {
        $rows = [];
        foreach ($photos as $sortOrder => $photo) {
            if (! is_array($photo)) {
                continue;
            }

            $relativePath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($relativePath === '') {
                continue;
            }

            $rows[] = [
                'discipleship_meeting_report_id' => $reportId,
                'relative_path' => $relativePath,
                'original_file_name' => $this->nullableString($photo['name'] ?? null),
                'sort_order' => max(0, (int) $sortOrder),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        if ($rows !== []) {
            DB::table('discipleship_meeting_report_photos')->insert($rows);
        }

        return count($rows);
    }

    /**
     * @return array<string, int>
     */
    private function personIdsByPublicId(): array
    {
        if (! Schema::hasTable('rec_people_registry')) {
            return [];
        }

        $map = [];
        foreach (DB::table('rec_people_registry')->select(['id', 'record_uid', 'dg_person_id', 'legacy_dg_person_id'])->get() as $row) {
            foreach (['record_uid', 'dg_person_id', 'legacy_dg_person_id'] as $column) {
                $publicId = trim((string) ($row->{$column} ?? ''));
                if ($publicId !== '') {
                    $map[$publicId] = (int) $row->id;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function groupIdsByPublicId(): array
    {
        if (! Schema::hasTable('rec_discipleship_groups')) {
            return [];
        }

        $map = [];
        foreach (DB::table('rec_discipleship_groups')->select(['id', 'record_uid'])->get() as $row) {
            $publicId = trim((string) ($row->record_uid ?? ''));
            if ($publicId !== '') {
                $map[$publicId] = (int) $row->id;
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeForBranch(string $branch): array
    {
        $branch = $this->branch($branch);
        if (! isset($this->runtimeByBranch[$branch])) {
            $this->runtimeByBranch[$branch] = load_branch_discipleship_runtime($branch);
        }

        return $this->runtimeByBranch[$branch];
    }

    private function publicId(object $row): string
    {
        $publicId = trim((string) ($row->record_uid ?? ''));
        if ($publicId !== '') {
            return $publicId;
        }

        return 'dg_report_' . (string) ($row->id ?? bin2hex(random_bytes(4)));
    }

    private function branch(mixed $value): string
    {
        $branch = strtolower(trim((string) $value));

        return is_known_public_branch_code($branch) ? normalize_public_branch_code($branch) : 'kutisari';
    }

    /**
     * @param array<string, int> $map
     */
    private function mappedId(array $map, mixed $publicId): ?int
    {
        $publicId = trim((string) $publicId);

        return $publicId === '' || ! isset($map[$publicId]) ? null : (int) $map[$publicId];
    }

    /**
     * @param array<string, array<string, mixed>> $peopleById
     */
    private function personName(string $personPublicId, array $peopleById): ?string
    {
        $personPublicId = trim($personPublicId);
        if ($personPublicId === '' || ! isset($peopleById[$personPublicId]) || ! is_array($peopleById[$personPublicId])) {
            return null;
        }

        return $this->nullableString($peopleById[$personPublicId]['name'] ?? null);
    }

    private function dateValue(mixed $value): ?string
    {
        $date = normalize_ymd_date((string) $value);

        return $date === '' ? null : $date;
    }

    private function sharingOpenness(mixed $value): ?int
    {
        $value = is_numeric($value) ? (int) $value : 0;

        return $value >= 1 && $value <= 10 ? $value : null;
    }

    /**
     * @return array<int, mixed>
     */
    private function jsonList(mixed $payload): array
    {
        if (is_array($payload)) {
            return array_values($payload);
        }

        $payload = trim((string) $payload);
        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function uniqueStrings(array $values): array
    {
        $seen = [];
        $result = [];
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text === '' || isset($seen[$text])) {
                continue;
            }

            $seen[$text] = true;
            $result[] = $text;
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $candidates
     */
    private function timestampFrom(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate instanceof CarbonImmutable) {
                return $candidate->format('Y-m-d H:i:s');
            }

            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
            } catch (Throwable) {
                continue;
            }
        }

        return now()->format('Y-m-d H:i:s');
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
