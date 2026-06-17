<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipRelationship extends Model
{
    protected $fillable = [
        'public_id',
        'branch_code',
        'mentor_person_id',
        'mentor_person_public_id',
        'disciple_person_id',
        'disciple_person_public_id',
        'context_group_id',
        'context_group_public_id',
        'relation_type',
        'stage_at_start',
        'status',
        'start_date',
        'end_date',
        'reason_end',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class, 'mentor_person_id');
    }

    public function disciple(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class, 'disciple_person_id');
    }

    public function contextGroup(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipGroup::class, 'context_group_id');
    }
}
