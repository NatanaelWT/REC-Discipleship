<?php

namespace App\Services\WorshipServiceSchedules;

use App\Models\WorshipServiceSchedule;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorshipServiceScheduleBuilder
{
    private const ROW_TYPE_ASSIGNMENT = 'assignment';
    private const ROW_TYPE_TRAINING = 'training';
    private const TRAINING_ROLE = 'Jadwal Latihan';

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
                ->orderBy('role_sort_order')
                ->orderBy('week_index')
                ->orderBy('assignee_sort_order')
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
                ->orderBy('created_at')
                ->orderBy('id')
                ->first();

            $timestamps = $this->timestampsForSave($record, $existing, $preserveTimestamps);

            WorshipServiceSchedule::query()
                ->where('month', $month)
                ->delete();

            $flatRows = $this->flatRowsForRecord($record, $month, $timestamps['created_at'], $timestamps['updated_at']);
            foreach (array_chunk($flatRows, 100) as $chunk) {
                DB::table('worship_service_schedules')->insert($chunk);
            }

            return WorshipServiceSchedule::query()
                ->where('month', $month)
                ->orderBy('row_type')
                ->orderBy('role_sort_order')
                ->orderBy('week_index')
                ->orderBy('assignee_sort_order')
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

    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return WorshipServiceSchedule::query()
            ->orderBy('role_sort_order')
            ->orderBy('week_index')
            ->orderBy('assignee_sort_order')
            ->orderBy('id');
    }

    /**
     * @return EloquentCollection<int, WorshipServiceSchedule>
     */
    private function rowsForMonth(string $month): EloquentCollection
    {
        return $this->baseQuery()
            ->where('month', normalize_month_value($month))
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

        $roleNamesBySort = [];
        $assignmentLines = [];
        $trainingAssignments = array_fill(0, count($weekDates), '');
        $createdAt = null;
        $updatedAt = null;
        $updateNote = '';

        foreach ($rows as $row) {
            if (! $row instanceof WorshipServiceSchedule) {
                continue;
            }

            $createdAt ??= $row->created_at;
            $updatedAt = $this->latestTimestamp($updatedAt, $row->updated_at);
            if ($updateNote === '') {
                $updateNote = trim((string) ($row->update_note ?? ''));
            }

            $weekIndex = (int) $row->week_index;
            if ($weekIndex < 0 || $weekIndex >= count($weekDates)) {
                continue;
            }

            if ((string) $row->row_type === self::ROW_TYPE_TRAINING) {
                $trainingAssignments[$weekIndex] = $this->dateString($row->training_date);

                continue;
            }

            $roleName = trim((string) $row->role_name);
            if ($roleName === '') {
                continue;
            }

            $roleSortOrder = (int) $row->role_sort_order;
            $roleNamesBySort[$roleSortOrder] ??= $roleName;

            $assigneeName = trim((string) ($row->assignee_name ?? ''));
            if ($assigneeName === '') {
                continue;
            }

            $assignmentLines[$roleSortOrder][$weekIndex][(int) $row->assignee_sort_order] = $assigneeName;
        }

        ksort($roleNamesBySort);

        $scheduleRows = [];
        foreach ($roleNamesBySort as $roleSortOrder => $roleName) {
            $assignments = array_fill(0, count($weekDates), '');
            foreach ($assignmentLines[$roleSortOrder] ?? [] as $weekIndex => $lines) {
                ksort($lines);
                $assignments[$weekIndex] = implode("\n", array_values($lines));
            }

            $scheduleRows[] = [
                'role' => $roleName,
                'assignments' => $assignments,
            ];
        }

        $scheduleRows[] = [
            'role' => self::TRAINING_ROLE,
            'assignments' => $trainingAssignments,
        ];

        return [
            'month' => $month,
            'update_note' => $updateNote,
            'rows' => normalize_worship_penatalayan_rows($scheduleRows, count($weekDates)),
            'created_at' => $this->timestampString($createdAt),
            'updated_at' => $this->timestampString($updatedAt),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, array<string, mixed>>
     */
    private function flatRowsForRecord(array $record, string $month, mixed $createdAt, mixed $updatedAt): array
    {
        $weekDates = worship_penatalayan_week_dates($month);
        $normalizedRows = normalize_worship_penatalayan_rows(
            is_array($record['rows'] ?? null) ? $record['rows'] : [],
            count($weekDates),
        );
        $updateNote = trim((string) ($record['update_note'] ?? ''));
        $flatRows = [];
        $roleSortOrder = 0;

        foreach ($normalizedRows as $row) {
            $roleName = trim((string) ($row['role'] ?? ''));
            if ($roleName === '') {
                continue;
            }

            $assignments = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
            if (strtolower($roleName) === strtolower(self::TRAINING_ROLE)) {
                foreach ($weekDates as $weekIndex => $serviceDate) {
                    $trainingDate = worship_penatalayan_training_date((string) ($assignments[$weekIndex] ?? ''), $month);
                    $flatRows[] = $this->flatRow(
                        $month,
                        $updateNote,
                        self::ROW_TYPE_TRAINING,
                        self::TRAINING_ROLE,
                        $roleSortOrder,
                        $weekIndex,
                        $serviceDate,
                        $trainingDate !== '' ? $trainingDate : null,
                        null,
                        0,
                        $createdAt,
                        $updatedAt,
                    );
                }
                $roleSortOrder++;

                continue;
            }

            foreach ($weekDates as $weekIndex => $serviceDate) {
                $assigneeNames = $this->normalizer->splitAssignmentLines((string) ($assignments[$weekIndex] ?? ''));
                if ($assigneeNames === []) {
                    $flatRows[] = $this->flatRow(
                        $month,
                        $updateNote,
                        self::ROW_TYPE_ASSIGNMENT,
                        $roleName,
                        $roleSortOrder,
                        $weekIndex,
                        $serviceDate,
                        null,
                        null,
                        0,
                        $createdAt,
                        $updatedAt,
                    );

                    continue;
                }

                foreach ($assigneeNames as $assigneeSortOrder => $assigneeName) {
                    $flatRows[] = $this->flatRow(
                        $month,
                        $updateNote,
                        self::ROW_TYPE_ASSIGNMENT,
                        $roleName,
                        $roleSortOrder,
                        $weekIndex,
                        $serviceDate,
                        null,
                        $assigneeName,
                        $assigneeSortOrder,
                        $createdAt,
                        $updatedAt,
                    );
                }
            }
            $roleSortOrder++;
        }

        return $flatRows;
    }

    /**
     * @return array<string, mixed>
     */
    private function flatRow(
        string $month,
        string $updateNote,
        string $rowType,
        string $roleName,
        int $roleSortOrder,
        int $weekIndex,
        string $serviceDate,
        ?string $trainingDate,
        ?string $assigneeName,
        int $assigneeSortOrder,
        mixed $createdAt,
        mixed $updatedAt,
    ): array {
        return [
            'month' => $month,
            'update_note' => $updateNote,
            'row_type' => $rowType,
            'role_name' => $roleName,
            'role_sort_order' => $roleSortOrder,
            'week_index' => $weekIndex,
            'service_date' => $serviceDate,
            'training_date' => $trainingDate,
            'assignee_name' => $assigneeName,
            'assignee_sort_order' => $assigneeSortOrder,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
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
}
