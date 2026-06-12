<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorshipServiceScheduleWeek extends Model
{
    protected $fillable = [
        'worship_service_schedule_id',
        'week_index',
        'service_date',
        'training_date',
    ];

    protected $casts = [
        'week_index' => 'integer',
        'service_date' => 'date:Y-m-d',
        'training_date' => 'date:Y-m-d',
    ];

    /**
     * @return BelongsTo<WorshipServiceSchedule, WorshipServiceScheduleWeek>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorshipServiceSchedule::class, 'worship_service_schedule_id');
    }

    /**
     * @return HasMany<WorshipServiceAssignment>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WorshipServiceAssignment::class)->orderBy('sort_order');
    }
}
