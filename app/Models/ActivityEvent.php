<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'changed_values' => 'array',
            'metadata' => 'array',
            'occurred_at' => UtcDateTimeCast::class,
        ];
    }

    public function activityRequest(): BelongsTo
    {
        return $this->belongsTo(ActivityRequest::class, 'request_id');
    }
}
