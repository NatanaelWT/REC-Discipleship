<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MskParticipant extends Model
{
    protected $fillable = [
        'public_id',
        'branch_code',
        'member_public_id',
        'full_name',
        'gender',
        'birth_date',
        'birth_day_month',
        'birth_place',
        'address',
        'email',
        'whatsapp',
        'batch_month',
        'notes',
        'completed_at',
        'journey_bridge_status',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MskParticipantSession::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MskParticipantPhoto::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toViewArray(): array
    {
        $this->loadMissing(['sessions', 'photos']);

        return [
            'id' => (string) $this->public_id,
            'member_id' => (string) ($this->member_public_id ?? ''),
            'full_name' => (string) ($this->full_name ?? ''),
            'gender' => (string) ($this->gender ?? ''),
            'birth_date' => $this->birth_date !== null ? $this->birth_date->format('Y-m-d') : '',
            'birth_day_month' => (string) ($this->birth_day_month ?? ''),
            'birth_place' => (string) ($this->birth_place ?? ''),
            'address' => (string) ($this->address ?? ''),
            'email' => (string) ($this->email ?? ''),
            'whatsapp' => (string) ($this->whatsapp ?? ''),
            'photos' => $this->photos
                ->map(static fn (MskParticipantPhoto $photo): array => [
                    'path' => (string) $photo->path,
                    'name' => (string) ($photo->original_name ?? 'Foto'),
                ])
                ->values()
                ->all(),
            'msk_month' => (string) ($this->batch_month ?? ''),
            'session_numbers' => $this->sessions
                ->pluck('session_number')
                ->map(static fn (mixed $number): int => (int) $number)
                ->sort()
                ->values()
                ->all(),
            'notes' => (string) ($this->notes ?? ''),
            'completed_at' => (string) ($this->completed_at ?? ''),
            'journey_bridge_status' => (string) ($this->journey_bridge_status ?? 'belum'),
            'status' => (string) ($this->status ?? 'active'),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
