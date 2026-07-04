<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscipleshipGroup extends Model
{
    use ResolvesBranchSlug;

    protected $table = 'kelompok_dg';

    protected $fillable = [
        'branch_id',
        'name',
        'status',
        'start_stage',
        'current_stage',
        'parent_group_id',
        'source_group_id',
        'initiated_by_person_id',
        'multiplied_at',
        'notes',
    ];

    protected $casts = [
        'multiplied_at' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parentGroup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_group_id');
    }

    public function sourceGroup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_group_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'initiated_by_person_id');
    }

    public function childGroups(): HasMany
    {
        return $this->hasMany(self::class, 'parent_group_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(DiscipleshipGroupPerson::class, 'discipleship_group_id')
            ->where('role', 'member');
    }

    public function leaderships(): HasMany
    {
        return $this->hasMany(DiscipleshipGroupPerson::class, 'discipleship_group_id')
            ->where('role', '!=', 'member');
    }

    public function groupPeople(): HasMany
    {
        return $this->hasMany(DiscipleshipGroupPerson::class, 'discipleship_group_id');
    }
}
