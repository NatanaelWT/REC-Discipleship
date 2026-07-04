<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WEEKLY_TABLE = 'worship_service_schedules_weekly';
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

    public function up(): void
    {
        if ($this->alreadyWeekly()) {
            return;
        }

        Schema::dropIfExists(self::WEEKLY_TABLE);
        $this->createWeeklyTable(self::WEEKLY_TABLE);

        if (Schema::hasTable('worship_service_schedules')) {
            $this->backfillWeeklyRows();
        }

        Schema::dropIfExists('worship_service_schedules');
        Schema::rename(self::WEEKLY_TABLE, 'worship_service_schedules');
    }

    public function down(): void
    {
        // Intentionally not reversible. The previous shape stored each role/assignee as separate rows.
    }

    private function alreadyWeekly(): bool
    {
        return Schema::hasTable('worship_service_schedules')
            && Schema::hasColumn('worship_service_schedules', 'lw_1_name')
            && ! Schema::hasColumn('worship_service_schedules', 'row_type');
    }

    private function createWeeklyTable(string $tableName): void
    {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->index();
            $table->longText('update_note')->nullable();
            $table->unsignedTinyInteger('week_index')->default(0);
            $table->date('service_date');
            $table->date('training_date')->nullable();

            foreach ($this->roleColumns as $columnPrefix) {
                for ($slot = 1; $slot <= self::ASSIGNEE_SLOT_COUNT; $slot++) {
                    $table->string($columnPrefix.'_'.$slot.'_name', 120)->nullable();
                }
            }

            for ($customRole = 1; $customRole <= self::CUSTOM_ROLE_COUNT; $customRole++) {
                $table->string('custom_role_'.$customRole.'_label', 120)->nullable();
                for ($slot = 1; $slot <= self::ASSIGNEE_SLOT_COUNT; $slot++) {
                    $table->string('custom_role_'.$customRole.'_assignee_'.$slot.'_name', 120)->nullable();
                }
            }

            $table->timestamps();

            $table->unique(['month', 'week_index'], 'wss_weekly_month_week_unique');
            $table->unique(['month', 'service_date'], 'wss_weekly_month_service_date_unique');
        });
    }

    private function backfillWeeklyRows(): void
    {
        if (Schema::hasColumn('worship_service_schedules', 'row_type')) {
            $this->backfillFromFlatRows();

            return;
        }

        $this->backfillFromExistingWeeklyRows();
    }

    private function backfillFromFlatRows(): void
    {
        $flatRows = DB::table('worship_service_schedules')
            ->orderBy('month')
            ->orderBy('week_index')
            ->orderBy('role_sort_order')
            ->orderBy('assignee_sort_order')
            ->orderBy('id')
            ->get();

        $weeklyRows = [];
        $customRoleColumnsByMonth = [];

        foreach ($flatRows as $flatRow) {
            $month = $this->normalizeMonth((string) ($flatRow->month ?? ''));
            $serviceDate = $this->normalizeDate((string) ($flatRow->service_date ?? ''));
            if ($month === '' || $serviceDate === '') {
                continue;
            }

            $weekIndex = (int) ($flatRow->week_index ?? 0);
            $key = $month.'|'.$weekIndex;
            $weeklyRows[$key] ??= $this->blankWeeklyRow(
                $month,
                $weekIndex,
                $serviceDate,
                trim((string) ($flatRow->update_note ?? '')),
                $flatRow->created_at ?? now(),
                $flatRow->updated_at ?? now(),
            );
            $weeklyRows[$key]['updated_at'] = $this->latestTimestamp(
                $weeklyRows[$key]['updated_at'],
                $flatRow->updated_at ?? null,
            );

            if ((string) ($flatRow->row_type ?? '') === 'training') {
                $weeklyRows[$key]['training_date'] = $this->nullableDate($flatRow->training_date ?? null);

                continue;
            }

            $assigneeName = trim((string) ($flatRow->assignee_name ?? ''));
            if ($assigneeName === '') {
                continue;
            }

            $roleName = trim((string) ($flatRow->role_name ?? ''));
            $roleKey = strtolower($roleName);
            $columnPrefix = $this->roleColumns[$roleKey] ?? null;
            if ($columnPrefix === null) {
                $customRoleColumnsByMonth[$month][$roleKey] ??= count($customRoleColumnsByMonth[$month] ?? []) + 1;
                $customRoleIndex = (int) $customRoleColumnsByMonth[$month][$roleKey];
                if ($customRoleIndex > self::CUSTOM_ROLE_COUNT) {
                    continue;
                }

                $weeklyRows[$key]['custom_role_'.$customRoleIndex.'_label'] = $roleName;
                $columnPrefix = 'custom_role_'.$customRoleIndex.'_assignee';
            }

            $slot = ((int) ($flatRow->assignee_sort_order ?? 0)) + 1;
            if ($slot < 1 || $slot > self::ASSIGNEE_SLOT_COUNT) {
                continue;
            }

            $weeklyRows[$key][$columnPrefix.'_'.$slot.'_name'] = $assigneeName;
        }

        foreach (array_chunk(array_values($weeklyRows), 100) as $chunk) {
            DB::table(self::WEEKLY_TABLE)->insert($chunk);
        }
    }

    private function backfillFromExistingWeeklyRows(): void
    {
        if (! Schema::hasColumn('worship_service_schedules', 'lw_1_name')) {
            return;
        }

        foreach (DB::table('worship_service_schedules')->orderBy('month')->orderBy('week_index')->get() as $row) {
            $values = (array) $row;
            unset($values['id']);
            DB::table(self::WEEKLY_TABLE)->insert(array_intersect_key(
                $values,
                array_flip(Schema::getColumnListing(self::WEEKLY_TABLE)),
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function blankWeeklyRow(
        string $month,
        int $weekIndex,
        string $serviceDate,
        string $updateNote,
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

    private function latestTimestamp(mixed $current, mixed $candidate): mixed
    {
        if ($candidate === null) {
            return $current;
        }

        return (strtotime((string) $candidate) ?: 0) > (strtotime((string) $current) ?: 0)
            ? $candidate
            : $current;
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
