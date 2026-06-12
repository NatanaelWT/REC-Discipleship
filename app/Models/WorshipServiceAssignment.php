<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorshipServiceAssignment extends Model
{
    protected $fillable = [
        'worship_service_schedule_role_id',
        'worship_service_schedule_week_id',
        'assignee_name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<WorshipServiceScheduleRole, WorshipServiceAssignment>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(WorshipServiceScheduleRole::class, 'worship_service_schedule_role_id');
    }

    /**
     * @return BelongsTo<WorshipServiceScheduleWeek, WorshipServiceAssignment>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(WorshipServiceScheduleWeek::class, 'worship_service_schedule_week_id');
    }
}
