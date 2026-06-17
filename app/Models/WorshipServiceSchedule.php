<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorshipServiceSchedule extends Model
{
    protected $fillable = [
        'month',
        'title',
        'update_note',
        'branch_id',
        'branch_code',
    ];

    public function getRouteKeyName(): string
    {
        return 'month';
    }

    /**
     * @return HasMany<WorshipServiceScheduleRole>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(WorshipServiceScheduleRole::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<WorshipServiceScheduleWeek>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(WorshipServiceScheduleWeek::class)->orderBy('week_index');
    }
}
