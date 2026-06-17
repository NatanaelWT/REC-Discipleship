<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscipleshipGroup extends Model
{
    protected $fillable = [
        'public_id',
        'branch_code',
        'name',
        'status',
        'start_stage',
        'current_stage',
        'parent_group_id',
        'parent_group_public_id',
        'notes',
    ];

    public function parentGroup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_group_id');
    }

    public function childGroups(): HasMany
    {
        return $this->hasMany(self::class, 'parent_group_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(DiscipleshipGroupMembership::class);
    }

    public function leaderships(): HasMany
    {
        return $this->hasMany(DiscipleshipGroupLeadership::class);
    }
}
