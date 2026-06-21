<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityRequest extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'query_data' => 'array',
            'input_data' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'duration_ms' => 'decimal:3',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(ActivityEvent::class, 'request_id')->orderBy('id');
    }
}
