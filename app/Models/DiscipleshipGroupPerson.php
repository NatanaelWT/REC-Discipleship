<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipGroupPerson extends Model
{
    use ResolvesBranchSlug;

    protected $fillable = [
        'branch_id',
        'discipleship_group_id',
        'person_id',
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
        return $this->belongsTo(Person::class, 'person_id');
    }
}
