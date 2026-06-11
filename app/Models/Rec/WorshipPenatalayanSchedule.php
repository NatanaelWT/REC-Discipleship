<?php

namespace App\Models\Rec;

class WorshipPenatalayanSchedule extends RecRecord
{
    protected $table = 'rec_worship_penatalayan_schedules';

    protected $casts = [
        'payload' => 'array',
        'rows_payload' => 'array',
        'source_updated_at' => 'datetime',
    ];
}
