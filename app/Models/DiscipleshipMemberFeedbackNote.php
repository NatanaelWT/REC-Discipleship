<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipMemberFeedbackNote extends Model
{
    protected $fillable = [
        'discipleship_member_feedback_journal_id',
        'section_key',
        'note_key',
        'content',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipMemberFeedbackJournal::class, 'discipleship_member_feedback_journal_id');
    }
}
