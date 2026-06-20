<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscipleshipGroupMultiplication extends Model
{
    use ResolvesBranchSlug;

    protected $fillable = [
        'public_id',
        'branch_id',
        'initiated_by_person_id',
        'initiated_by_person_public_id',
        'source_group_id',
        'source_group_public_id',
        'new_group_id',
        'new_group_public_id',
        'multiplication_date',
        'notes',
    ];

    protected $casts = [
        'multiplication_date' => 'date',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class, 'initiated_by_person_id');
    }

    public function sourceGroup(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipGroup::class, 'source_group_id');
    }

    public function newGroup(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipGroup::class, 'new_group_id');
    }
}
