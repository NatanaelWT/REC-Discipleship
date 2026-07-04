<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorshipServiceSchedule extends Model
{
    protected $fillable = [
        'month',
        'update_note',
        'row_type',
        'role_name',
        'role_sort_order',
        'week_index',
        'service_date',
        'training_date',
        'assignee_name',
        'assignee_sort_order',
    ];

    protected $casts = [
        'role_sort_order' => 'integer',
        'week_index' => 'integer',
        'service_date' => 'date:Y-m-d',
        'training_date' => 'date:Y-m-d',
        'assignee_sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'month';
    }
}
