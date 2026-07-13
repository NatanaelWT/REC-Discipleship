<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ActivityEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function getTable()
    {
        return config('activity.storage', 'legacy') === 'split'
            ? 'audit_events'
            : 'aktivitas';
    }

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
