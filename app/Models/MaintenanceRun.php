<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRun extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'cursor' => 'array',
            'summary' => 'array',
            'started_at' => UtcDateTimeCast::class,
            'heartbeat_at' => UtcDateTimeCast::class,
            'completed_at' => UtcDateTimeCast::class,
        ];
    }
}
