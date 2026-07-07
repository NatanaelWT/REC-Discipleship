<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
use Illuminate\Database\Eloquent\Model;

class ActivityEvent extends Model
{
    protected $table = 'aktivitas';

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
}
