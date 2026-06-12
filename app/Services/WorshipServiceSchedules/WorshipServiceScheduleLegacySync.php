<?php

namespace App\Services\WorshipServiceSchedules;

use App\Models\WorshipServiceSchedule;
use App\Support\LegacyRuntimeBootstrap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorshipServiceScheduleLegacySync
{
    public function __construct(private readonly WorshipServiceScheduleBuilder $builder)
    {
    }

    public function syncSchedule(WorshipServiceSchedule $schedule): void
    {
        LegacyRuntimeBootstrap::load();

        if (! Schema::hasTable('rec_worship_penatalayan_schedules')) {
            return;
        }

        $record = $this->builder->recordFromModel($schedule);
        $month = (string) ($record['month'] ?? '');
        if ($month === '') {
            return;
        }

        $existing = DB::table('rec_worship_penatalayan_schedules')->where('month', $month)->first();
        $rowsPayload = json_encode($record['rows'] ?? [], JSON_UNESCAPED_UNICODE);
        if (! is_string($rowsPayload)) {
            $rowsPayload = '[]';
        }

        $nowIso = function_exists('now_iso') ? now_iso() : now()->toIso8601String();
        $values = [
            'month' => $month,
            'title' => (string) ($record['title'] ?? ''),
            'update_note' => (string) ($record['update_note'] ?? ''),
            'rows_payload' => $rowsPayload,
            'branch' => $schedule->branch_code,
            'created_at_legacy' => $existing->created_at_legacy ?? ((string) ($record['created_at'] ?? $nowIso)),
            'record_updated_at' => (string) ($record['updated_at'] ?? $nowIso),
            'updated_at' => now(),
        ];

        if ($existing === null) {
            $values['created_at'] = now();
            DB::table('rec_worship_penatalayan_schedules')->insert($values);

            return;
        }

        DB::table('rec_worship_penatalayan_schedules')
            ->where('id', $existing->id)
            ->update($values);
    }

    public function deleteMonth(string $month): void
    {
        LegacyRuntimeBootstrap::load();

        if (! Schema::hasTable('rec_worship_penatalayan_schedules')) {
            return;
        }

        $month = normalize_month_value($month);
        DB::table('rec_worship_penatalayan_schedules')->where('month', $month)->delete();
    }
}
