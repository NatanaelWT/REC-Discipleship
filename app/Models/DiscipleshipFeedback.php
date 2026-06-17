<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class DiscipleshipFeedback extends Model
{
    protected $fillable = [
        'public_id',
        'branch_id',
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
        'ratings',
        'notes',
        'source',
    ];

    protected $casts = [
        'feedback_session' => 'integer',
        'ratings' => 'array',
        'notes' => 'array',
    ];

    public function getTable()
    {
        if (Schema::hasTable('discipleship_feedbacks')) {
            return 'discipleship_feedbacks';
        }

        return 'discipleship_member_feedback_journals';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipGroup::class, 'discipleship_group_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class, 'leader_person_id');
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class, 'respondent_person_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(DiscipleshipMemberFeedbackRating::class, 'discipleship_member_feedback_journal_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DiscipleshipMemberFeedbackNote::class, 'discipleship_member_feedback_journal_id');
    }
}
