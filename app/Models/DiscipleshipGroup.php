<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class DiscipleshipGroup extends Model
{
    use ResolvesBranchSlug;

    protected $fillable = [
        'public_id',
        'branch_id',
        'name',
        'status',
        'start_stage',
        'current_stage',
        'parent_group_id',
        'parent_group_public_id',
        'source_group_id',
        'source_group_public_id',
        'initiated_by_person_id',
        'initiated_by_person_public_id',
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
        return $this->belongsTo(DiscipleshipPerson::class, 'initiated_by_person_id');
    }

    public function childGroups(): HasMany
    {
        return $this->hasMany(self::class, 'parent_group_id');
    }

    public function memberships(): HasMany
    {
        if (Schema::hasTable('discipleship_group_people')) {
            return $this->hasMany(DiscipleshipGroupPerson::class, 'discipleship_group_id')
                ->where('role', 'member');
        }

        return $this->hasMany(DiscipleshipGroupMembership::class);
    }

    public function leaderships(): HasMany
    {
        if (Schema::hasTable('discipleship_group_people')) {
            return $this->hasMany(DiscipleshipGroupPerson::class, 'discipleship_group_id')
                ->where('role', '!=', 'member');
        }

        return $this->hasMany(DiscipleshipGroupLeadership::class);
    }

    public function groupPeople(): HasMany
    {
        return $this->hasMany(DiscipleshipGroupPerson::class, 'discipleship_group_id');
    }
}
