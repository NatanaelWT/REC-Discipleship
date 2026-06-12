<?php

namespace App\Models\Rec;

class WorshipPenatalayanSchedule extends RecRecord
{
    protected $table = 'rec_worship_penatalayan_schedules';

    protected $casts = [
        'rows_payload' => 'array',
    ];
}
