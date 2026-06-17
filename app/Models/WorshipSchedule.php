<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorshipSchedule extends Model
{
    protected $fillable = [
        'month',
        'title',
        'update_note',
        'branch_id',
        'branch_code',
        'rows',
    ];

    protected $casts = [
        'rows' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'month';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
