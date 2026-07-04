<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorshipServiceSchedule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'week_index' => 'integer',
        'service_date' => 'date:Y-m-d',
        'training_date' => 'date:Y-m-d',
    ];

    public function getRouteKeyName(): string
    {
        return 'month';
    }
}
