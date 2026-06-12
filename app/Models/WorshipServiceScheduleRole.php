<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorshipServiceScheduleRole extends Model
{
    protected $fillable = [
        'worship_service_schedule_id',
        'role_name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<WorshipServiceSchedule, WorshipServiceScheduleRole>
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
