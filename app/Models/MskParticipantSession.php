<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MskParticipantSession extends Model
{
    protected $fillable = [
        'msk_participant_id',
        'session_number',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MskParticipant::class, 'msk_participant_id');
    }
}
