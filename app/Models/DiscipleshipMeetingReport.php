<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class DiscipleshipMeetingReport extends Model
{
    use ResolvesBranchSlug;

    protected $fillable = [
        'public_id',
        'branch_id',
        'leader_person_id',
        'leader_person_public_id',
        'leader_name_snapshot',
        'discipleship_group_id',
        'discipleship_group_public_id',
        'group_name_snapshot',
        'meeting_date',
        'material_topic',
        'group_progress_snapshot',
        'absence_reason',
        'absences',
        'meditation_sharers',
        'photos',
        'additional_notes',
        'meditation_min_times',
        'sharing_openness_score',
        'prepared_material',
        'prayed_for_members',
        'shared_meditation',
        'relationally_contacted',
        'source',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'absences' => 'array',
        'meditation_sharers' => 'array',
        'photos' => 'array',
        'meditation_min_times' => 'integer',
        'sharing_openness_score' => 'integer',
        'prepared_material' => 'boolean',
        'prayed_for_members' => 'boolean',
        'shared_meditation' => 'boolean',
        'relationally_contacted' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function absenceItems(): array
    {
        if ($this->hasJsonColumn('absences')) {
            return $this->jsonArray('absences');
        }

        $items = [];
        if (Schema::hasTable('discipleship_meeting_report_absences')) {
            foreach ($this->relationLoaded('absences') ? $this->getRelation('absences') : $this->absences()->get() as $row) {
                $items[] = [
                    'person_id' => $row->person_id ?? null,
                    'person_public_id' => (string) ($row->person_public_id ?? ''),
                    'person_name_snapshot' => (string) ($row->person_name_snapshot ?? ''),
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function meditationSharerItems(): array
    {
        if ($this->hasJsonColumn('meditation_sharers')) {
            return $this->jsonArray('meditation_sharers');
        }

        $items = [];
        if (Schema::hasTable('discipleship_meeting_report_meditation_sharers')) {
            foreach ($this->relationLoaded('meditationSharers') ? $this->getRelation('meditationSharers') : $this->meditationSharers()->get() as $row) {
                $items[] = [
                    'person_id' => $row->person_id ?? null,
                    'person_public_id' => (string) ($row->person_public_id ?? ''),
                    'person_name_snapshot' => (string) ($row->person_name_snapshot ?? ''),
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function photoItems(): array
    {
        if ($this->hasJsonColumn('photos')) {
            return $this->jsonArray('photos');
        }

        $items = [];
        if (Schema::hasTable('discipleship_meeting_report_photos')) {
            foreach ($this->relationLoaded('photos') ? $this->getRelation('photos') : $this->photos()->orderBy('sort_order')->get() as $row) {
                $items[] = [
                    'path' => (string) ($row->relative_path ?? ''),
                    'name' => (string) ($row->original_file_name ?? ''),
                    'sort_order' => (int) ($row->sort_order ?? 0),
                ];
            }
        }

        return $items;
    }

    public function absences(): HasMany
    {
        return $this->hasMany(DiscipleshipMeetingReportAbsence::class);
    }

    public function meditationSharers(): HasMany
    {
        return $this->hasMany(DiscipleshipMeetingReportMeditationSharer::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DiscipleshipMeetingReportPhoto::class)->orderBy('sort_order');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jsonArray(string $key): array
    {
        $attributes = $this->getAttributes();
        if (! array_key_exists($key, $attributes)) {
            return [];
        }

        $value = $this->getAttribute($key);
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn ($item): bool => is_array($item)));
    }

    private function hasJsonColumn(string $key): bool
    {
        return array_key_exists($key, $this->getAttributes());
    }
}
