<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscipleshipPerson extends Model
{
    use ResolvesBranchSlug;

    protected $fillable = [
        'branch_id',
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
        return $this->hasMany(DiscipleshipGroupPerson::class, 'person_id')
            ->where('role', 'member');
    }

    public function leaderships(): HasMany
    {
        return $this->hasMany(DiscipleshipGroupPerson::class, 'person_id')
            ->where('role', '!=', 'member');
    }
}
