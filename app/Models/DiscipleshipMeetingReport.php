<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscipleshipMeetingReport extends Model
{
    protected $fillable = [
        'public_id',
        'branch_code',
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
}
