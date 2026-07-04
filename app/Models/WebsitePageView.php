<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePageView extends Model
{
    protected $table = 'kunjungan_halaman';

    protected $primaryKey = 'request_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => UtcDateTimeCast::class,
            'is_bot' => 'boolean',
            'is_prefetch' => 'boolean',
            'response_ms' => 'decimal:3',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ActivityRequest::class, 'request_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WebsiteSession::class, 'session_id');
    }
}
