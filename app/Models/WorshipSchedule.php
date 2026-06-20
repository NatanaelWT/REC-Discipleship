<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorshipSchedule extends Model
{
    protected $fillable = [
        'month',
        'title',
        'update_note',
        'rows',
    ];

    protected $casts = [
        'rows' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'month';
    }

}
