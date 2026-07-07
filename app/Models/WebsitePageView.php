<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePageView extends Model
{
    protected $table = 'aktivitas';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope('page_view', static function (Builder $builder): void {
            $builder->where('is_page_view', true);
        });
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => UtcDateTimeCast::class,
            'is_bot' => 'boolean',
            'is_prefetch' => 'boolean',
            'is_page_view' => 'boolean',
            'response_ms' => 'decimal:3',
        ];
    }

    public function getRequestIdAttribute(): string
    {
        return (string) $this->getKey();
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ActivityRequest::class, 'id', 'id');
    }
}
