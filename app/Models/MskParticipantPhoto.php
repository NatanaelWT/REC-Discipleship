<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MskParticipantPhoto extends Model
{
    protected $fillable = [
        'msk_participant_id',
        'path',
        'original_name',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MskParticipant::class, 'msk_participant_id');
    }
}
