<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipMemberFeedbackRating extends Model
{
    protected $fillable = [
        'discipleship_member_feedback_journal_id',
        'section_key',
        'question_key',
        'score',
        'scale',
    ];

    protected $casts = [
        'score' => 'integer',
        'scale' => 'integer',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipMemberFeedbackJournal::class, 'discipleship_member_feedback_journal_id');
    }
}
