<?php

namespace App\Services\DgMeetingReports;

use App\Models\DiscipleshipMeetingReport;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DgMeetingReportLegacySync
{
    public function sync(DiscipleshipMeetingReport $report): void
    {
        if (! Schema::hasTable('rec_dg_meeting_reports')) {
            return;
        }

        $columns = Schema::getColumnListing('rec_dg_meeting_reports');
        if (! in_array('record_uid', $columns, true)) {
            return;
        }

        $report->loadMissing(['absences', 'meditationSharers', 'photos']);

        $photos = [];
        foreach ($report->photos as $photo) {
            $path = sanitize_relative_upload_path((string) $photo->relative_path);
            if ($path === '') {
                continue;
            }
            $name = trim((string) $photo->original_file_name);
            if ($name === '') {
                $name = basename($path);
            }

            $photos[] = [
                'path' => $path,
                'name' => $name,
            ];
        }

        $row = [
            'leader_id' => $this->nullableString($report->leader_person_public_id),
            'group_id' => $this->nullableString($report->discipleship_group_public_id),
            'meeting_date' => $report->meeting_date?->format('Y-m-d'),
            'material_topic' => $this->nullableString($report->material_topic),
            'group_progress' => $this->nullableString($report->group_progress_snapshot),
            'source' => $this->nullableString($report->source) ?? 'public_form',
            'created_at_legacy' => $this->legacyTimestamp($report->created_at),
            'branch' => $this->nullableString($report->branch_code),
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at,
            'absence_reason' => $this->nullableString($report->absence_reason),
            'absent_member_ids_json' => $this->json($this->publicPersonIds($report->absences)),
            'additional_notes' => $this->nullableString($report->additional_notes),
            'meditation_min_times' => max(0, (int) $report->meditation_min_times),
            'meditation_sharer_ids_json' => $this->json($this->publicPersonIds($report->meditationSharers)),
            'meeting_photos_json' => $this->json($photos),
            'quality_pray' => $this->legacyBool($report->prayed_for_members),
            'quality_prepare' => $this->legacyBool($report->prepared_material),
            'quality_relational' => $this->legacyBool($report->relationally_contacted),
            'quality_share_meditation' => $this->legacyBool($report->shared_meditation),
            'sharing_openness' => max(0, (int) $report->sharing_openness_score),
            'record_updated_at' => $this->legacyTimestamp($report->updated_at),
        ];

        $filtered = array_intersect_key($row, array_flip($columns));

        DB::table('rec_dg_meeting_reports')->updateOrInsert(
            ['record_uid' => $report->public_id],
            $filtered,
        );
    }

    /**
     * @param iterable<int, object> $rows
     * @return array<int, string>
     */
    private function publicPersonIds(iterable $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $publicId = trim((string) ($row->person_public_id ?? ''));
            if ($publicId === '') {
                $publicId = trim((string) ($row->person_id ?? ''));
            }
            if ($publicId !== '' && ! in_array($publicId, $ids, true)) {
                $ids[] = $publicId;
            }
        }

        return $ids;
    }

    private function legacyTimestamp(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy()
                ->timezone(config('app.timezone', 'Asia/Jakarta'))
                ->format('Y-m-d\TH:i:sP');
        }

        return function_exists('now_iso') ? now_iso() : now()->format('Y-m-d\TH:i:sP');
    }

    private function legacyBool(mixed $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @param array<int, mixed> $value
     */
    private function json(array $value): string
    {
        $json = json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '[]';
    }
}
