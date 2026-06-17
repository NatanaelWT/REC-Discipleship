<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class DiscipleshipPerson extends Model
{
    protected $fillable = [
        'public_id',
        'branch_id',
        'branch_code',
        'member_public_id',
        'full_name',
        'phone',
        'gender',
        'status',
        'notes',
        'campus',
        'major',
        'occupation',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function memberships(): HasMany
    {
        if (Schema::hasTable('discipleship_group_people')) {
            return $this->hasMany(DiscipleshipGroupPerson::class, 'person_id')
                ->where('role', 'member');
        }

        return $this->hasMany(DiscipleshipGroupMembership::class, 'person_id');
    }

    public function leaderships(): HasMany
    {
        if (Schema::hasTable('discipleship_group_people')) {
            return $this->hasMany(DiscipleshipGroupPerson::class, 'person_id')
                ->where('role', '!=', 'member');
        }

        return $this->hasMany(DiscipleshipGroupLeadership::class, 'person_id');
    }
}
