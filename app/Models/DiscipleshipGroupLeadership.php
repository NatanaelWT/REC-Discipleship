<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipGroupLeadership extends Model
{
    protected $fillable = [
        'public_id',
        'branch_code',
        'discipleship_group_id',
        'group_public_id',
        'person_id',
        'person_public_id',
        'role',
        'status',
        'start_date',
        'end_date',
        'reason_change',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipGroup::class, 'discipleship_group_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class, 'person_id');
    }
}
