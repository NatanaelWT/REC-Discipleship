<?php

namespace App\Services\WorshipServiceSchedules;

use App\Models\WorshipServiceSchedule;
use App\Models\WorshipServiceScheduleRole;
use App\Models\WorshipServiceScheduleWeek;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorshipServiceScheduleBuilder
{
    public function __construct(private readonly WorshipServiceScheduleNormalizer $normalizer) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allRecords(): array
    {
        RuntimeBootstrap::load();

        return WorshipServiceSchedule::query()
            ->with(['roles.assignments', 'weeks'])
            ->orderByDesc('month')
            ->get()
            ->map(fn (WorshipServiceSchedule $schedule): array => $this->recordFromModel($schedule))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recordForMonth(string $month): ?array
    {
        RuntimeBootstrap::load();

        $schedule = WorshipServiceSchedule::query()
            ->with(['roles.assignments', 'weeks'])
            ->where('month', normalize_month_value($month))
            ->first();

        return $schedule instanceof WorshipServiceSchedule ? $this->recordFromModel($schedule) : null;
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
            $schedule = WorshipServiceSchedule::query()->firstOrNew(['month' => $month]);
            $schedule->fill([
                'update_note' => trim((string) ($record['update_note'] ?? '')),
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
    }

    public function deleteMonth(string $month): bool
    {
        RuntimeBootstrap::load();

        $schedule = WorshipServiceSchedule::query()
            ->where('month', normalize_month_value($month))
            ->first();
        if (! $schedule instanceof WorshipServiceSchedule) {
            return false;
        }

        DB::transaction(function () use ($schedule): void {
            $this->deleteChildren($schedule);
            $schedule->delete();
        });

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
            'update_note' => trim((string) ($schedule->update_note ?? '')),
            'rows' => normalize_worship_penatalayan_rows($rows, count($weekDates)),
            'created_at' => $this->timestampString($schedule->created_at),
            'updated_at' => $this->timestampString($schedule->updated_at),
        ];
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function replaceChildren(WorshipServiceSchedule $schedule, array $rows): void
    {
        $this->deleteChildren($schedule);

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

    private function deleteChildren(WorshipServiceSchedule $schedule): void
    {
        $roleIds = $schedule->roles()->pluck('id')->all();
        $weekIds = $schedule->weeks()->pluck('id')->all();

        if ($roleIds !== []) {
            DB::table('worship_service_assignments')
                ->whereIn('worship_service_schedule_role_id', $roleIds)
                ->delete();
        }
        if ($weekIds !== []) {
            DB::table('worship_service_assignments')
                ->whereIn('worship_service_schedule_week_id', $weekIds)
                ->delete();
        }

        $schedule->roles()->delete();
        $schedule->weeks()->delete();
    }

    /**
     * @param  EloquentCollection<int, WorshipServiceScheduleWeek>  $weeks
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
     * @param  EloquentCollection<int, WorshipServiceScheduleWeek>  $weeks
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
