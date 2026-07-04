<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Model;

class DiscipleshipMeetingReport extends Model
{
    use ResolvesBranchSlug;

    protected $table = 'jurnal_temu_dg';

    protected $fillable = [
        'branch_id',
        'leader_person_id',
        'leader_name_snapshot',
        'discipleship_group_id',
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function absenceItems(): array
    {
        return $this->jsonArray('absences');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function meditationSharerItems(): array
    {
        return $this->jsonArray('meditation_sharers');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function photoItems(): array
    {
        return $this->jsonArray('photos');
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
}
