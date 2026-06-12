<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscipleshipMemberFeedbackJournal extends Model
{
    protected $fillable = [
        'public_id',
        'branch_code',
        'feedback_session',
        'discipleship_group_id',
        'leader_person_id',
        'respondent_person_id',
        'respondent_name_snapshot',
        'leader_name_snapshot',
        'group_name_snapshot',
        'group_label_snapshot',
        'group_progress_snapshot',
        'source',
    ];

    protected $casts = [
        'feedback_session' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(DiscipleshipMemberFeedbackRating::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DiscipleshipMemberFeedbackNote::class);
    }
}
