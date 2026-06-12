<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipMeetingReportMeditationSharer extends Model
{
    protected $fillable = [
        'discipleship_meeting_report_id',
        'person_id',
        'person_public_id',
        'person_name_snapshot',
    ];

    protected $casts = [
        'person_id' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipMeetingReport::class, 'discipleship_meeting_report_id');
    }
}
