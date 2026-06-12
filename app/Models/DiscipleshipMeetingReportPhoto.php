<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipMeetingReportPhoto extends Model
{
    protected $fillable = [
        'discipleship_meeting_report_id',
        'relative_path',
        'original_file_name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipMeetingReport::class, 'discipleship_meeting_report_id');
    }
}
