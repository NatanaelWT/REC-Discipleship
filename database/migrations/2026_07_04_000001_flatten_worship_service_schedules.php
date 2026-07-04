<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FLAT_TABLE = 'worship_service_schedules_flat';

    public function up(): void
    {
        if ($this->alreadyFlat()) {
            $this->dropLegacyChildTables();

            return;
        }

        Schema::dropIfExists(self::FLAT_TABLE);
        $this->createFlatTable(self::FLAT_TABLE);

        if (Schema::hasTable('worship_service_schedules')) {
            $this->backfillFlatRows();
        }

        $this->dropLegacyChildTables();
        Schema::dropIfExists('worship_service_schedules');
        Schema::rename(self::FLAT_TABLE, 'worship_service_schedules');
    }

    public function down(): void
    {
        // Intentionally not reversible. The previous storage split one schedule across four tables.
    }

    private function alreadyFlat(): bool
    {
        return Schema::hasTable('worship_service_schedules')
            && Schema::hasColumn('worship_service_schedules', 'row_type')
            && Schema::hasColumn('worship_service_schedules', 'assignee_sort_order');
    }

    private function createFlatTable(string $tableName): void
    {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->index();
            $table->longText('update_note')->nullable();
            $table->string('row_type', 20)->default('assignment')->index();
            $table->string('role_name');
            $table->unsignedSmallInteger('role_sort_order')->default(0);
            $table->unsignedTinyInteger('week_index')->default(0);
            $table->date('service_date');
            $table->date('training_date')->nullable();
            $table->string('assignee_name')->nullable();
            $table->unsignedSmallInteger('assignee_sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['month', 'row_type', 'role_sort_order', 'week_index'],
                'wss_flat_month_row_order_index',
            );
            $table->index(['month', 'role_name'], 'wss_flat_month_role_index');
        });
    }

    private function backfillFlatRows(): void
    {
        foreach (DB::table('worship_service_schedules')->orderBy('month')->orderBy('id')->get() as $schedule) {
            $month = $this->normalizeMonth((string) ($schedule->month ?? ''));
            if ($month === '') {
                continue;
            }

            $scheduleId = (int) ($schedule->id ?? 0);
            $createdAt = $schedule->created_at ?? now();
            $updatedAt = $schedule->updated_at ?? now();
            $base = [
                'month' => $month,
                'update_note' => trim((string) ($schedule->update_note ?? '')),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];

            $weeks = $this->legacyWeeks($scheduleId, $month);
            $roleSortOrder = $this->backfillAssignmentRows($base, $scheduleId, $weeks);
            $this->backfillTrainingRows($base, $weeks, $roleSortOrder);
        }
    }

    /**
     * @param  array<int, array{id:int|null, week_index:int, service_date:string, training_date:string|null}>  $weeks
     * @return int
     */
    private function backfillAssignmentRows(array $base, int $scheduleId, array $weeks): int
    {
        if (! Schema::hasTable('worship_service_schedule_roles')) {
            return 0;
        }

        $nextSortOrder = 0;
        foreach (
            DB::table('worship_service_schedule_roles')
                ->where('worship_service_schedule_id', $scheduleId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get() as $role
        ) {
            $roleName = trim((string) ($role->role_name ?? ''));
            if ($roleName === '') {
                continue;
            }

            $roleSortOrder = (int) ($role->sort_order ?? $nextSortOrder);
            $nextSortOrder = max($nextSortOrder, $roleSortOrder + 1);
            $assignmentsByWeek = $this->legacyAssignmentsByWeek((int) $role->id);

            foreach ($weeks as $week) {
                $assignments = $week['id'] !== null
                    ? ($assignmentsByWeek[$week['id']] ?? [])
                    : [];

                if ($assignments === []) {
                    $this->insertFlatRow($base + [
                        'row_type' => 'assignment',
                        'role_name' => $roleName,
                        'role_sort_order' => $roleSortOrder,
                        'week_index' => $week['week_index'],
                        'service_date' => $week['service_date'],
                        'training_date' => null,
                        'assignee_name' => null,
                        'assignee_sort_order' => 0,
                    ]);

                    continue;
                }

                foreach ($assignments as $assignment) {
                    $this->insertFlatRow($base + [
                        'row_type' => 'assignment',
                        'role_name' => $roleName,
                        'role_sort_order' => $roleSortOrder,
                        'week_index' => $week['week_index'],
                        'service_date' => $week['service_date'],
                        'training_date' => null,
                        'assignee_name' => $assignment['assignee_name'],
                        'assignee_sort_order' => $assignment['assignee_sort_order'],
                    ]);
                }
            }
        }

        return $nextSortOrder;
    }

    /**
     * @param  array<int, array{id:int|null, week_index:int, service_date:string, training_date:string|null}>  $weeks
     */
    private function backfillTrainingRows(array $base, array $weeks, int $roleSortOrder): void
    {
        foreach ($weeks as $week) {
            $this->insertFlatRow($base + [
                'row_type' => 'training',
                'role_name' => 'Jadwal Latihan',
                'role_sort_order' => $roleSortOrder,
                'week_index' => $week['week_index'],
                'service_date' => $week['service_date'],
                'training_date' => $week['training_date'],
                'assignee_name' => null,
                'assignee_sort_order' => 0,
            ]);
        }
    }

    /**
     * @return array<int, array{id:int|null, week_index:int, service_date:string, training_date:string|null}>
     */
    private function legacyWeeks(int $scheduleId, string $month): array
    {
        $weeks = [];

        if (Schema::hasTable('worship_service_schedule_weeks')) {
            foreach (
                DB::table('worship_service_schedule_weeks')
                    ->where('worship_service_schedule_id', $scheduleId)
                    ->orderBy('week_index')
                    ->orderBy('id')
                    ->get() as $week
            ) {
                $serviceDate = $this->normalizeDate((string) ($week->service_date ?? ''));
                if ($serviceDate === '') {
                    continue;
                }

                $weeks[] = [
                    'id' => (int) $week->id,
                    'week_index' => (int) ($week->week_index ?? count($weeks)),
                    'service_date' => $serviceDate,
                    'training_date' => $this->nullableDate($week->training_date ?? null),
                ];
            }
        }

        if ($weeks !== []) {
            return $weeks;
        }

        foreach ($this->weekDates($month) as $weekIndex => $serviceDate) {
            $weeks[] = [
                'id' => null,
                'week_index' => $weekIndex,
                'service_date' => $serviceDate,
                'training_date' => null,
            ];
        }

        return $weeks;
    }

    /**
     * @return array<int, array<int, array{assignee_name:string|null, assignee_sort_order:int}>>
     */
    private function legacyAssignmentsByWeek(int $roleId): array
    {
        if (! Schema::hasTable('worship_service_assignments')) {
            return [];
        }

        $assignmentsByWeek = [];
        foreach (
            DB::table('worship_service_assignments')
                ->where('worship_service_schedule_role_id', $roleId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get() as $assignment
        ) {
            $name = trim((string) ($assignment->assignee_name ?? ''));
            $assignmentsByWeek[(int) $assignment->worship_service_schedule_week_id][] = [
                'assignee_name' => $name !== '' ? $name : null,
                'assignee_sort_order' => (int) ($assignment->sort_order ?? 0),
            ];
        }

        return $assignmentsByWeek;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insertFlatRow(array $row): void
    {
        DB::table(self::FLAT_TABLE)->insert($row);
    }

    private function dropLegacyChildTables(): void
    {
        Schema::dropIfExists('worship_service_assignments');
        Schema::dropIfExists('worship_service_schedule_weeks');
        Schema::dropIfExists('worship_service_schedule_roles');
    }

    /**
     * @return array<int, string>
     */
    private function weekDates(string $month): array
    {
        $timestamp = strtotime($month.'-01');
        if ($timestamp === false) {
            return [];
        }

        $dates = [];
        for ($day = 1; $day <= (int) date('t', $timestamp); $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            if ((int) date('w', strtotime($date) ?: 0) === 0) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    private function normalizeMonth(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private function nullableDate(mixed $value): ?string
    {
        $date = $this->normalizeDate((string) ($value ?? ''));

        return $date !== '' ? $date : null;
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return '';
        }

        return strtotime($value) !== false ? $value : '';
    }
};
