<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteDailyRollup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date:Y-m-d',
            'page_views' => 'integer',
            'human_page_views' => 'integer',
            'unique_visitors' => 'integer',
            'human_unique_visitors' => 'integer',
            'bot_views' => 'integer',
            'prefetch_views' => 'integer',
            'total_response_ms' => 'decimal:3',
            'average_response_ms' => 'decimal:3',
            'human_total_response_ms' => 'decimal:3',
            'human_average_response_ms' => 'decimal:3',
        ];
    }
}
