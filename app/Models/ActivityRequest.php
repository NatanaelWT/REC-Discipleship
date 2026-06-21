<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
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
            'started_at' => UtcDateTimeCast::class,
            'completed_at' => UtcDateTimeCast::class,
            'duration_ms' => 'decimal:3',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(ActivityEvent::class, 'request_id')->orderBy('id');
    }
}
