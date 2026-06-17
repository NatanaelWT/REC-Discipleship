<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscipleshipPerson extends Model
{
    protected $fillable = [
        'public_id',
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

    public function memberships(): HasMany
    {
        return $this->hasMany(DiscipleshipGroupMembership::class, 'person_id');
    }

    public function leaderships(): HasMany
    {
        return $this->hasMany(DiscipleshipGroupLeadership::class, 'person_id');
    }
}
