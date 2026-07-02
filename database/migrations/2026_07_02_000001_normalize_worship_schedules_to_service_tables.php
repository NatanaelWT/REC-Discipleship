<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasJsonSchedules = Schema::hasTable('worship_schedules');

        if ($hasJsonSchedules) {
            $this->dropServiceTables();
        }

        $this->createServiceTables();

        if ($hasJsonSchedules) {
            $this->backfillFromJsonSchedules();
            Schema::dropIfExists('worship_schedules');
        }
    }

    public function down(): void
    {
        // Intentionally not reversible. The old storage used a JSON payload table.
    }

    private function createServiceTables(): void
    {
        if (! Schema::hasTable('worship_service_schedules')) {
            Schema::create('worship_service_schedules', function (Blueprint $table): void {
                $table->id();
                $table->string('month', 7)->unique();
                $table->longText('update_note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('worship_service_schedule_roles')) {
            Schema::create('worship_service_schedule_roles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('worship_service_schedule_id')
                    ->constrained('worship_service_schedules')
                    ->cascadeOnDelete();
                $table->string('role_name');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('worship_service_schedule_id', 'wss_roles_schedule_index');
                $table->index('sort_order', 'wss_roles_sort_order_index');
            });
        }

        if (! Schema::hasTable('worship_service_schedule_weeks')) {
            Schema::create('worship_service_schedule_weeks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('worship_service_schedule_id')
                    ->constrained('worship_service_schedules')
                    ->cascadeOnDelete();
                $table->unsignedTinyInteger('week_index');
                $table->date('service_date');
                $table->date('training_date')->nullable();
                $table->timestamps();

                $table->unique(
                    ['worship_service_schedule_id', 'week_index'],
                    'wss_weeks_schedule_week_unique',
                );
                $table->unique(
                    ['worship_service_schedule_id', 'service_date'],
                    'wss_weeks_schedule_date_unique',
                );
            });
        }

        if (! Schema::hasTable('worship_service_assignments')) {
            Schema::create('worship_service_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('worship_service_schedule_role_id')
                    ->constrained('worship_service_schedule_roles')
                    ->cascadeOnDelete();
                $table->foreignId('worship_service_schedule_week_id')
                    ->constrained('worship_service_schedule_weeks')
                    ->cascadeOnDelete();
                $table->string('assignee_name');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('worship_service_schedule_role_id', 'wss_assignments_role_index');
                $table->index('worship_service_schedule_week_id', 'wss_assignments_week_index');
                $table->unique(
                    ['worship_service_schedule_role_id', 'worship_service_schedule_week_id', 'sort_order'],
                    'wss_assignments_role_week_sort_unique',
                );
            });
        }
    }

    private function dropServiceTables(): void
    {
        Schema::dropIfExists('worship_service_assignments');
        Schema::dropIfExists('worship_service_schedule_weeks');
        Schema::dropIfExists('worship_service_schedule_roles');
        Schema::dropIfExists('worship_service_schedules');
    }

    private function backfillFromJsonSchedules(): void
    {
        foreach (DB::table('worship_schedules')->orderBy('month')->orderBy('id')->get() as $jsonSchedule) {
            $month = $this->normalizeMonth((string) ($jsonSchedule->month ?? ''));
            if ($month === '') {
                continue;
            }

            $createdAt = $jsonSchedule->created_at ?? now();
            $updatedAt = $jsonSchedule->updated_at ?? now();
            $scheduleId = (int) $jsonSchedule->id;
            DB::table('worship_service_schedules')->insert([
                'id' => (int) $jsonSchedule->id,
                'month' => $month,
                'update_note' => trim((string) ($jsonSchedule->update_note ?? '')),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $weekIdsByIndex = $this->createWeeks($scheduleId, $month, $createdAt, $updatedAt);
            $rows = json_decode((string) ($jsonSchedule->rows ?? '[]'), true);
            if (! is_array($rows)) {
                $rows = [];
            }

            $roleSortOrder = 0;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $roleName = trim((string) ($row['role'] ?? ''));
                if ($roleName === '') {
                    continue;
                }

                $assignments = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
                if (strtolower($roleName) === 'jadwal latihan') {
                    $this->backfillTrainingDates($weekIdsByIndex, $assignments, $month, $updatedAt);

                    continue;
                }

                $roleId = (int) DB::table('worship_service_schedule_roles')->insertGetId([
                    'worship_service_schedule_id' => $scheduleId,
                    'role_name' => $roleName,
                    'sort_order' => $roleSortOrder,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);
                $roleSortOrder++;

                $this->backfillAssignments($roleId, $weekIdsByIndex, $assignments, $createdAt, $updatedAt);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function createWeeks(int $scheduleId, string $month, mixed $createdAt, mixed $updatedAt): array
    {
        $weekIdsByIndex = [];
        foreach ($this->weekDates($month) as $weekIndex => $serviceDate) {
            $weekIdsByIndex[$weekIndex] = (int) DB::table('worship_service_schedule_weeks')->insertGetId([
                'worship_service_schedule_id' => $scheduleId,
                'week_index' => $weekIndex,
                'service_date' => $serviceDate,
                'training_date' => null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
        }

        return $weekIdsByIndex;
    }

    /**
     * @param  array<int, int>  $weekIdsByIndex
     * @param  array<int|string, mixed>  $assignments
     */
    private function backfillTrainingDates(array $weekIdsByIndex, array $assignments, string $month, mixed $updatedAt): void
    {
        foreach ($assignments as $weekIndex => $trainingDate) {
            $weekIndex = (int) $weekIndex;
            if (! isset($weekIdsByIndex[$weekIndex])) {
                continue;
            }

            $normalizedDate = $this->normalizeTrainingDate((string) $trainingDate, $month);
            if ($normalizedDate === '') {
                continue;
            }

            DB::table('worship_service_schedule_weeks')
                ->where('id', $weekIdsByIndex[$weekIndex])
                ->update([
                    'training_date' => $normalizedDate,
                    'updated_at' => $updatedAt,
                ]);
        }
    }

    /**
     * @param  array<int, int>  $weekIdsByIndex
     * @param  array<int|string, mixed>  $assignments
     */
    private function backfillAssignments(
        int $roleId,
        array $weekIdsByIndex,
        array $assignments,
        mixed $createdAt,
        mixed $updatedAt,
    ): void {
        foreach ($assignments as $weekIndex => $cellValue) {
            $weekIndex = (int) $weekIndex;
            if (! isset($weekIdsByIndex[$weekIndex])) {
                continue;
            }

            foreach ($this->assignmentLines((string) $cellValue) as $sortOrder => $assigneeName) {
                DB::table('worship_service_assignments')->insert([
                    'worship_service_schedule_role_id' => $roleId,
                    'worship_service_schedule_week_id' => $weekIdsByIndex[$weekIndex],
                    'assignee_name' => $assigneeName,
                    'sort_order' => $sortOrder,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function assignmentLines(string $value): array
    {
        $lines = preg_split("/\r\n?|\n/", $value) ?: [];
        $names = [];
        foreach ($lines as $line) {
            $name = trim((string) $line);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
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
        $daysInMonth = (int) date('t', $timestamp);
        for ($day = 1; $day <= $daysInMonth; $day++) {
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
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return $value;
        }

        return '';
    }

    private function normalizeTrainingDate(string $value, string $month): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return '';
        }

        return strtotime($value) !== false ? $value : '';
    }
};
