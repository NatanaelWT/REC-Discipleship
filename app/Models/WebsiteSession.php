<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteSession extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => UtcDateTimeCast::class,
            'last_seen_at' => UtcDateTimeCast::class,
            'page_views' => 'integer',
        ];
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(WebsitePageView::class, 'session_id');
    }
}
