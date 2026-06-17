<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipGroupPerson extends Model
{
    protected $fillable = [
        'public_id',
        'branch_id',
        'branch_code',
        'discipleship_group_id',
        'group_public_id',
        'person_id',
        'person_public_id',
        'role',
        'stage',
        'status',
        'started_on',
        'ended_on',
        'end_reason',
    ];

    protected $casts = [
        'started_on' => 'date',
        'ended_on' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipGroup::class, 'discipleship_group_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class, 'person_id');
    }
}
