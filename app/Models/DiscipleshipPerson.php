<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiscipleshipPerson extends Model
{
    use ResolvesBranchSlug;

    protected $fillable = [
        'branch_id',
        'status',
        'notes',
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

    public function mskParticipant(): HasOne
    {
        return $this->hasOne(MskParticipant::class, 'discipleship_person_id');
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (mixed $value): ?string => $value !== null ? (string) $value : $this->profileValue('full_name'));
    }

    protected function phone(): Attribute
    {
        return Attribute::get(fn (mixed $value): ?string => $value !== null ? (string) $value : $this->profileValue('whatsapp'));
    }

    protected function gender(): Attribute
    {
        return Attribute::get(fn (mixed $value): ?string => $value !== null ? (string) $value : $this->profileValue('gender'));
    }

    private function profileValue(string $column): ?string
    {
        $participant = $this->relationLoaded('mskParticipant')
            ? $this->getRelation('mskParticipant')
            : $this->mskParticipant()->first();

        $value = $participant?->{$column};

        return $value !== null ? (string) $value : null;
    }
}
