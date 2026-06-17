<?php

namespace App\Services\WorshipServiceSchedules;

use App\Models\WorshipServiceSchedule;
use App\Models\WorshipServiceScheduleRole;
use App\Models\WorshipServiceScheduleWeek;
use App\Models\WorshipSchedule;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorshipServiceScheduleBuilder
{
    public function __construct(private readonly WorshipServiceScheduleNormalizer $normalizer)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allRecords(): array
    {
        RuntimeBootstrap::load();

        if ($this->usesNewTable()) {
            $branchCode = $this->currentBranchCode();

            return WorshipSchedule::query()
                ->where('branch_code', $branchCode)
                ->orderByDesc('month')
                ->get()
                ->map(fn (WorshipSchedule $schedule): array => $this->recordFromJsonModel($schedule))
                ->all();
        }

        $schedules = WorshipServiceSchedule::query()
            ->with(['roles.assignments', 'weeks'])
            ->orderByDesc('month')
            ->get();

        return $schedules
            ->map(fn (WorshipServiceSchedule $schedule): array => $this->recordFromModel($schedule))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recordForMonth(string $month): ?array
    {
        RuntimeBootstrap::load();

        $month = normalize_month_value($month);
        if ($this->usesNewTable()) {
            $branchCode = $this->currentBranchCode();
            $schedule = WorshipSchedule::query()
                ->where('branch_code', $branchCode)
                ->where('month', $month)
                ->first();

            return $schedule instanceof WorshipSchedule ? $this->recordFromJsonModel($schedule) : null;
        }

        $schedule = WorshipServiceSchedule::query()
            ->with(['roles.assignments', 'weeks'])
            ->where('month', $month)
            ->first();

        return $schedule instanceof WorshipServiceSchedule ? $this->recordFromModel($schedule) : null;
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    public function buildSchedule(string $month, ?array $existing = null): array
    {
        RuntimeBootstrap::load();

        return build_worship_penatalayan_schedule($month, $existing);
    }

    /**
     * @param array<string, mixed> $record
     */
    public function saveRecord(array $record, bool $preserveTimestamps = false): WorshipServiceSchedule
    {
        RuntimeBootstrap::load();

        $month = normalize_month_value((string) ($record['month'] ?? date('Y-m')));
        $title = trim((string) ($record['title'] ?? ''));
        if ($title === '') {
            $title = default_worship_penatalayan_title($month);
        }
        $branchCode = $this->recordBranchCode($record);

        if ($this->usesNewTable()) {
            /** @var WorshipSchedule $schedule */
            $schedule = DB::transaction(function () use ($record, $month, $title, $branchCode, $preserveTimestamps): WorshipSchedule {
                $schedule = WorshipSchedule::query()->firstOrNew([
                    'branch_code' => $branchCode,
                    'month' => $month,
                ]);
                $schedule->fill([
                    'title' => $title,
                    'update_note' => trim((string) ($record['update_note'] ?? '')),
                    'branch_id' => $this->branchId($branchCode),
                    'branch_code' => $branchCode,
                    'rows' => is_array($record['rows'] ?? null) ? normalize_worship_penatalayan_rows($record['rows'], count(worship_penatalayan_week_dates($month))) : [],
                ]);

                if ($preserveTimestamps) {
                    $createdAt = $this->parseTimestamp((string) ($record['created_at'] ?? ''));
                    $updatedAt = $this->parseTimestamp((string) ($record['updated_at'] ?? ''));
                    if ($createdAt instanceof Carbon) {
                        $schedule->created_at = $createdAt;
                    }
                    if ($updatedAt instanceof Carbon) {
                        $schedule->updated_at = $updatedAt;
                    }
                }

                $schedule->save();

                return $schedule->fresh() ?? $schedule;
            });

            return $this->legacyScheduleProxy($schedule);
        }

        /** @var WorshipServiceSchedule $schedule */
        $schedule = DB::transaction(function () use ($record, $month, $title, $preserveTimestamps): WorshipServiceSchedule {
            $schedule = WorshipServiceSchedule::query()->firstOrNew(['month' => $month]);
            $schedule->fill([
                'title' => $title,
                'update_note' => trim((string) ($record['update_note'] ?? '')),
                'branch_code' => $this->nullableString($record['branch_code'] ?? null),
            ]);

            if ($preserveTimestamps) {
                $createdAt = $this->parseTimestamp((string) ($record['created_at'] ?? ''));
                $updatedAt = $this->parseTimestamp((string) ($record['updated_at'] ?? ''));
                if ($createdAt instanceof Carbon) {
                    $schedule->created_at = $createdAt;
                }
                if ($updatedAt instanceof Carbon) {
                    $schedule->updated_at = $updatedAt;
                }
            }

            $schedule->save();
            $this->replaceChildren($schedule, is_array($record['rows'] ?? null) ? $record['rows'] : []);

            return $schedule->fresh(['roles.assignments', 'weeks']) ?? $schedule;
        });

        return $schedule;
    }

    public function deleteMonth(string $month): bool
    {
        RuntimeBootstrap::load();

        $month = normalize_month_value($month);
        if ($this->usesNewTable()) {
            $schedule = WorshipSchedule::query()
                ->where('branch_code', $this->currentBranchCode())
                ->where('month', $month)
                ->first();
            if (! $schedule instanceof WorshipSchedule) {
                return false;
            }

            $schedule->delete();

            return true;
        }

        $schedule = WorshipServiceSchedule::query()->where('month', $month)->first();
        if (! $schedule instanceof WorshipServiceSchedule) {
            return false;
        }

        $schedule->delete();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function recordFromModel(WorshipServiceSchedule $schedule): array
    {
        RuntimeBootstrap::load();

        $schedule->loadMissing(['roles.assignments', 'weeks']);
        $month = normalize_month_value((string) $schedule->month);
        $weekDates = worship_penatalayan_week_dates($month);
        $weeks = $this->weeksByIndex($schedule->weeks);
        $weeksById = $this->weeksById($schedule->weeks);
        $rows = [];

        foreach ($schedule->roles as $role) {
            $assignments = array_fill(0, count($weekDates), '');
            $assignmentLines = [];
            foreach ($role->assignments as $assignment) {
                $week = $weeksById[$assignment->worship_service_schedule_week_id] ?? null;
                if (! $week instanceof WorshipServiceScheduleWeek) {
                    continue;
                }

                $weekIndex = (int) $week->week_index;
                if ($weekIndex < 0 || $weekIndex >= count($weekDates)) {
                    continue;
                }

                $assignmentLines[$weekIndex][] = (string) $assignment->assignee_name;
            }

            foreach ($assignmentLines as $weekIndex => $lines) {
                $assignments[$weekIndex] = implode("\n", array_values($lines));
            }

            $rows[] = [
                'role' => (string) $role->role_name,
                'assignments' => $assignments,
            ];
        }

        $trainingAssignments = array_fill(0, count($weekDates), '');
        foreach ($weeks as $week) {
            $weekIndex = (int) $week->week_index;
            if ($weekIndex < 0 || $weekIndex >= count($weekDates)) {
                continue;
            }

            $trainingAssignments[$weekIndex] = $week->training_date instanceof Carbon
                ? $week->training_date->format('Y-m-d')
                : '';
        }
        $rows[] = [
            'role' => 'Jadwal Latihan',
            'assignments' => $trainingAssignments,
        ];

        return [
            'month' => $month,
            'title' => trim((string) $schedule->title) !== ''
                ? trim((string) $schedule->title)
                : default_worship_penatalayan_title($month),
            'update_note' => trim((string) ($schedule->update_note ?? '')),
            'rows' => normalize_worship_penatalayan_rows($rows, count($weekDates)),
            'created_at' => $this->timestampString($schedule->created_at),
            'updated_at' => $this->timestampString($schedule->updated_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordFromJsonModel(WorshipSchedule $schedule): array
    {
        RuntimeBootstrap::load();

        $month = normalize_month_value((string) $schedule->month);
        $rows = is_array($schedule->rows ?? null) ? $schedule->rows : [];

        return [
            'month' => $month,
            'title' => trim((string) $schedule->title) !== ''
                ? trim((string) $schedule->title)
                : default_worship_penatalayan_title($month),
            'update_note' => trim((string) ($schedule->update_note ?? '')),
            'rows' => normalize_worship_penatalayan_rows($rows, count(worship_penatalayan_week_dates($month))),
            'branch_code' => $this->nullableString($schedule->branch_code ?? null),
            'created_at' => $this->timestampString($schedule->created_at),
            'updated_at' => $this->timestampString($schedule->updated_at),
        ];
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function replaceChildren(WorshipServiceSchedule $schedule, array $rows): void
    {
        $schedule->roles()->delete();
        $schedule->weeks()->delete();

        $month = normalize_month_value((string) $schedule->month);
        $weekDates = worship_penatalayan_week_dates($month);
        $weeksByIndex = [];
        foreach ($weekDates as $weekIndex => $weekDate) {
            $weeksByIndex[$weekIndex] = $schedule->weeks()->create([
                'week_index' => $weekIndex,
                'service_date' => $weekDate,
                'training_date' => null,
            ]);
        }

        $normalizedRows = normalize_worship_penatalayan_rows($rows, count($weekDates));
        $roleSortOrder = 0;
        foreach ($normalizedRows as $row) {
            $roleName = trim((string) ($row['role'] ?? ''));
            if ($roleName === '') {
                continue;
            }

            $assignments = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
            if (strtolower($roleName) === 'jadwal latihan') {
                foreach ($assignments as $weekIndex => $trainingDate) {
                    if (! isset($weeksByIndex[$weekIndex])) {
                        continue;
                    }

                    $normalizedTrainingDate = worship_penatalayan_training_date((string) $trainingDate, $month);
                    $weeksByIndex[$weekIndex]->forceFill([
                        'training_date' => $normalizedTrainingDate !== '' ? $normalizedTrainingDate : null,
                    ])->save();
                }

                continue;
            }

            /** @var WorshipServiceScheduleRole $role */
            $role = $schedule->roles()->create([
                'role_name' => $roleName,
                'sort_order' => $roleSortOrder,
            ]);
            $roleSortOrder++;

            foreach ($assignments as $weekIndex => $cellValue) {
                if (! isset($weeksByIndex[$weekIndex])) {
                    continue;
                }

                foreach ($this->normalizer->splitAssignmentLines((string) $cellValue) as $lineIndex => $assigneeName) {
                    $role->assignments()->create([
                        'worship_service_schedule_week_id' => $weeksByIndex[$weekIndex]->id,
                        'assignee_name' => $assigneeName,
                        'sort_order' => $lineIndex,
                    ]);
                }
            }
        }
    }

    private function usesNewTable(): bool
    {
        return Schema::hasTable('worship_schedules');
    }

    /**
     * @param WorshipSchedule $schedule
     * @return WorshipServiceSchedule
     */
    private function legacyScheduleProxy(WorshipSchedule $schedule): WorshipServiceSchedule
    {
        $proxy = new WorshipServiceSchedule();
        $proxy->forceFill([
            'id' => $schedule->id,
            'month' => $schedule->month,
            'title' => $schedule->title,
            'update_note' => $schedule->update_note,
            'branch_code' => $schedule->branch_code,
            'created_at' => $schedule->created_at,
            'updated_at' => $schedule->updated_at,
        ]);

        return $proxy;
    }

    /**
     * @param EloquentCollection<int, WorshipServiceScheduleWeek> $weeks
     * @return array<int, WorshipServiceScheduleWeek>
     */
    private function weeksByIndex(EloquentCollection $weeks): array
    {
        $byIndex = [];
        foreach ($weeks as $week) {
            $byIndex[(int) $week->week_index] = $week;
        }
        ksort($byIndex);

        return $byIndex;
    }

    /**
     * @param EloquentCollection<int, WorshipServiceScheduleWeek> $weeks
     * @return array<int, WorshipServiceScheduleWeek>
     */
    private function weeksById(EloquentCollection $weeks): array
    {
        $byId = [];
        foreach ($weeks as $week) {
            $byId[(int) $week->id] = $week;
        }

        return $byId;
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : null;
    }

    private function currentBranchCode(): string
    {
        return normalize_public_branch_code(is_logged_in() ? current_user_branch() : 'kutisari');
    }

    /**
     * @param array<string, mixed> $record
     */
    private function recordBranchCode(array $record): string
    {
        $branchCode = trim((string) ($record['branch_code'] ?? ''));

        return normalize_public_branch_code($branchCode !== '' ? $branchCode : $this->currentBranchCode());
    }

    private function branchId(string $branchCode): ?int
    {
        if (! Schema::hasTable('branches')) {
            return null;
        }

        $id = DB::table('branches')
            ->where('code', normalize_public_branch_code($branchCode))
            ->value('id');

        return $id === null ? null : (int) $id;
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

    private function timestampString(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        return trim((string) $value);
    }
}
