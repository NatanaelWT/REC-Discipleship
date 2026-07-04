<?php

namespace App\Services\WorshipServiceSchedules;

use App\Models\WorshipServiceSchedule;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorshipServiceScheduleBuilder
{
    private const TRAINING_ROLE = 'Jadwal Latihan';
    private const ASSIGNEE_SLOT_COUNT = 4;
    private const CUSTOM_ROLE_COUNT = 4;

    /** @var array<string, string> */
    private array $roleColumns = [
        'stage manager' => 'stage_manager',
        'lw' => 'lw',
        'singer' => 'singer',
        'keyboard' => 'keyboard',
        'bass' => 'bass',
        'gitar' => 'gitar',
        'drum' => 'drum',
        'soundman' => 'soundman',
        'video mixer, streaming, & camera' => 'video_mixer_streaming_camera',
        'lighting' => 'lighting',
        'operator' => 'operator',
    ];

    public function __construct(private readonly WorshipServiceScheduleNormalizer $normalizer) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allRecords(): array
    {
        RuntimeBootstrap::load();

        $rowsByMonth = [];
        foreach (
            WorshipServiceSchedule::query()
                ->orderByDesc('month')
                ->orderBy('week_index')
                ->orderBy('id')
                ->get() as $row
        ) {
            $month = normalize_month_value((string) $row->month);
            $rowsByMonth[$month][] = $row;
        }

        $records = [];
        foreach ($rowsByMonth as $rows) {
            $records[] = $this->recordFromRows($rows);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recordForMonth(string $month): ?array
    {
        RuntimeBootstrap::load();

        $rows = $this->rowsForMonth($month);
        if ($rows->isEmpty()) {
            return null;
        }

        return $this->recordFromRows($rows->all());
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public function buildSchedule(string $month, ?array $existing = null): array
    {
        RuntimeBootstrap::load();

        return build_worship_penatalayan_schedule($month, $existing);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function saveRecord(array $record, bool $preserveTimestamps = false): WorshipServiceSchedule
    {
        RuntimeBootstrap::load();

        $month = normalize_month_value((string) ($record['month'] ?? date('Y-m')));

        return DB::transaction(function () use ($record, $month, $preserveTimestamps): WorshipServiceSchedule {
            $existing = WorshipServiceSchedule::query()
                ->where('month', $month)
                ->orderBy('week_index')
                ->orderBy('id')
                ->first();

            $timestamps = $this->timestampsForSave($record, $existing, $preserveTimestamps);

            WorshipServiceSchedule::query()
                ->where('month', $month)
                ->delete();

            $weeklyRows = $this->weeklyRowsForRecord($record, $month, $timestamps['created_at'], $timestamps['updated_at']);
            foreach (array_chunk($weeklyRows, 100) as $chunk) {
                DB::table('worship_service_schedules')->insert($chunk);
            }

            return WorshipServiceSchedule::query()
                ->where('month', $month)
                ->orderBy('week_index')
                ->orderBy('id')
                ->firstOrFail();
        });
    }

    public function deleteMonth(string $month): bool
    {
        RuntimeBootstrap::load();

        $month = normalize_month_value($month);
        $exists = WorshipServiceSchedule::query()
            ->where('month', $month)
            ->exists();
        if (! $exists) {
            return false;
        }

        WorshipServiceSchedule::query()
            ->where('month', $month)
            ->delete();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function recordFromModel(WorshipServiceSchedule $schedule): array
    {
        RuntimeBootstrap::load();

        $rows = $this->rowsForMonth((string) $schedule->month);

        return $this->recordFromRows($rows->all());
    }

    /**
     * @return EloquentCollection<int, WorshipServiceSchedule>
     */
    private function rowsForMonth(string $month): EloquentCollection
    {
        return WorshipServiceSchedule::query()
            ->where('month', normalize_month_value($month))
            ->orderBy('week_index')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, WorshipServiceSchedule>  $rows
     * @return array<string, mixed>
     */
    private function recordFromRows(array $rows): array
    {
        $first = $rows[0] ?? null;
        $month = $first instanceof WorshipServiceSchedule
            ? normalize_month_value((string) $first->month)
            : normalize_month_value(date('Y-m'));
        $weekDates = worship_penatalayan_week_dates($month);

        $rowsByWeek = [];
        $createdAt = null;
        $updatedAt = null;
        $updateNote = '';
        foreach ($rows as $row) {
            if (! $row instanceof WorshipServiceSchedule) {
                continue;
            }

            $rowsByWeek[(int) $row->week_index] = $row;
            $createdAt ??= $row->created_at;
            $updatedAt = $this->latestTimestamp($updatedAt, $row->updated_at);
            if ($updateNote === '') {
                $updateNote = trim((string) ($row->update_note ?? ''));
            }
        }

        $scheduleRows = [];
        foreach ($this->displayRoleColumns() as $roleName => $columnPrefix) {
            $assignments = array_fill(0, count($weekDates), '');
            foreach ($weekDates as $weekIndex => $_weekDate) {
                $weekRow = $rowsByWeek[$weekIndex] ?? null;
                if (! $weekRow instanceof WorshipServiceSchedule) {
                    continue;
                }

                $assignments[$weekIndex] = implode("\n", $this->assigneeSlots($weekRow, $columnPrefix));
            }

            $scheduleRows[] = [
                'role' => $roleName,
                'assignments' => $assignments,
            ];
        }

        $trainingAssignments = array_fill(0, count($weekDates), '');
        foreach ($weekDates as $weekIndex => $_weekDate) {
            $weekRow = $rowsByWeek[$weekIndex] ?? null;
            if ($weekRow instanceof WorshipServiceSchedule) {
                $trainingAssignments[$weekIndex] = $this->dateString($weekRow->training_date);
            }
        }
        $scheduleRows[] = [
            'role' => self::TRAINING_ROLE,
            'assignments' => $trainingAssignments,
        ];

        foreach ($this->customRoleRows($rowsByWeek, count($weekDates)) as $customRow) {
            $scheduleRows[] = $customRow;
        }

        return [
            'month' => $month,
            'update_note' => $updateNote,
            'rows' => normalize_worship_penatalayan_rows($scheduleRows, count($weekDates)),
            'created_at' => $this->timestampString($createdAt),
            'updated_at' => $this->timestampString($updatedAt),
        ];
    }

    /**
     * @param  array<int, WorshipServiceSchedule>  $rowsByWeek
     * @return array<int, array{role:string, assignments:array<int, string>}>
     */
    private function customRoleRows(array $rowsByWeek, int $weekCount): array
    {
        $customRows = [];
        for ($customRole = 1; $customRole <= self::CUSTOM_ROLE_COUNT; $customRole++) {
            $label = '';
            $assignments = array_fill(0, $weekCount, '');

            foreach ($rowsByWeek as $weekIndex => $weekRow) {
                $rowLabel = trim((string) ($weekRow->{'custom_role_'.$customRole.'_label'} ?? ''));
                if ($label === '' && $rowLabel !== '') {
                    $label = $rowLabel;
                }

                $assignments[$weekIndex] = implode("\n", $this->assigneeSlots($weekRow, 'custom_role_'.$customRole.'_assignee'));
            }

            if ($label !== '') {
                $customRows[] = [
                    'role' => $label,
                    'assignments' => $assignments,
                ];
            }
        }

        return $customRows;
    }

    /**
     * @return array<int, string>
     */
    private function assigneeSlots(WorshipServiceSchedule $row, string $columnPrefix): array
    {
        $names = [];
        for ($slot = 1; $slot <= self::ASSIGNEE_SLOT_COUNT; $slot++) {
            $name = trim((string) ($row->{$columnPrefix.'_'.$slot.'_name'} ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, array<string, mixed>>
     */
    private function weeklyRowsForRecord(array $record, string $month, mixed $createdAt, mixed $updatedAt): array
    {
        $weekDates = worship_penatalayan_week_dates($month);
        $normalizedRows = normalize_worship_penatalayan_rows(
            is_array($record['rows'] ?? null) ? $record['rows'] : [],
            count($weekDates),
        );
        $updateNote = trim((string) ($record['update_note'] ?? ''));

        $weeklyRows = [];
        foreach ($weekDates as $weekIndex => $serviceDate) {
            $weeklyRows[$weekIndex] = $this->blankWeeklyRow(
                $month,
                $updateNote,
                $weekIndex,
                $serviceDate,
                $createdAt,
                $updatedAt,
            );
        }

        $customRoleIndex = 1;
        foreach ($normalizedRows as $row) {
            $roleName = trim((string) ($row['role'] ?? ''));
            if ($roleName === '') {
                continue;
            }

            $assignments = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
            if (strtolower($roleName) === strtolower(self::TRAINING_ROLE)) {
                foreach ($weekDates as $weekIndex => $_serviceDate) {
                    $trainingDate = worship_penatalayan_training_date((string) ($assignments[$weekIndex] ?? ''), $month);
                    $weeklyRows[$weekIndex]['training_date'] = $trainingDate !== '' ? $trainingDate : null;
                }

                continue;
            }

            $columnPrefix = $this->roleColumns[$this->roleKey($roleName)] ?? null;
            if ($columnPrefix === null) {
                if ($customRoleIndex > self::CUSTOM_ROLE_COUNT) {
                    continue;
                }

                $columnPrefix = 'custom_role_'.$customRoleIndex.'_assignee';
                foreach ($weekDates as $weekIndex => $_serviceDate) {
                    $weeklyRows[$weekIndex]['custom_role_'.$customRoleIndex.'_label'] = $roleName;
                }
                $customRoleIndex++;
            }

            foreach ($weekDates as $weekIndex => $_serviceDate) {
                $assigneeNames = $this->normalizer->splitAssignmentLines((string) ($assignments[$weekIndex] ?? ''));
                for ($slot = 1; $slot <= self::ASSIGNEE_SLOT_COUNT; $slot++) {
                    $weeklyRows[$weekIndex][$columnPrefix.'_'.$slot.'_name'] = $assigneeNames[$slot - 1] ?? null;
                }
            }
        }

        return array_values($weeklyRows);
    }

    /**
     * @return array<string, mixed>
     */
    private function blankWeeklyRow(
        string $month,
        string $updateNote,
        int $weekIndex,
        string $serviceDate,
        mixed $createdAt,
        mixed $updatedAt,
    ): array {
        $row = [
            'month' => $month,
            'update_note' => $updateNote,
            'week_index' => $weekIndex,
            'service_date' => $serviceDate,
            'training_date' => null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        foreach ($this->roleColumns as $columnPrefix) {
            for ($slot = 1; $slot <= self::ASSIGNEE_SLOT_COUNT; $slot++) {
                $row[$columnPrefix.'_'.$slot.'_name'] = null;
            }
        }

        for ($customRole = 1; $customRole <= self::CUSTOM_ROLE_COUNT; $customRole++) {
            $row['custom_role_'.$customRole.'_label'] = null;
            for ($slot = 1; $slot <= self::ASSIGNEE_SLOT_COUNT; $slot++) {
                $row['custom_role_'.$customRole.'_assignee_'.$slot.'_name'] = null;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{created_at:mixed, updated_at:mixed}
     */
    private function timestampsForSave(array $record, ?WorshipServiceSchedule $existing, bool $preserveTimestamps): array
    {
        $now = now();
        $createdAt = $existing?->created_at ?? $now;
        $updatedAt = $now;

        if ($preserveTimestamps) {
            $preservedCreatedAt = $this->parseTimestamp((string) ($record['created_at'] ?? ''));
            $preservedUpdatedAt = $this->parseTimestamp((string) ($record['updated_at'] ?? ''));
            $createdAt = $preservedCreatedAt ?? $createdAt;
            $updatedAt = $preservedUpdatedAt ?? $updatedAt;
        }

        return [
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    private function parseTimestamp(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function latestTimestamp(mixed $current, mixed $candidate): mixed
    {
        if ($current === null) {
            return $candidate;
        }

        $currentTimestamp = strtotime((string) $current) ?: 0;
        $candidateTimestamp = strtotime((string) $candidate) ?: 0;

        return $candidateTimestamp > $currentTimestamp ? $candidate : $current;
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        return normalize_ymd_date((string) ($value ?? ''));
    }

    private function timestampString(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        return trim((string) ($value ?? ''));
    }

    private function roleKey(string $roleName): string
    {
        return strtolower(trim($roleName));
    }

    /**
     * @return array<string, string>
     */
    private function displayRoleColumns(): array
    {
        return [
            'Stage Manager' => 'stage_manager',
            'LW' => 'lw',
            'Singer' => 'singer',
            'Keyboard' => 'keyboard',
            'Bass' => 'bass',
            'Gitar' => 'gitar',
            'Drum' => 'drum',
            'Soundman' => 'soundman',
            'Video Mixer, Streaming, & Camera' => 'video_mixer_streaming_camera',
            'Lighting' => 'lighting',
            'Operator' => 'operator',
        ];
    }
}
