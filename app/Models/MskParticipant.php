<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class MskParticipant extends Model
{
    protected $fillable = [
        'public_id',
        'branch_id',
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
        'session_numbers',
        'photos',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'session_numbers' => 'array',
        'photos' => 'array',
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
        $relations = [];
        if (! $this->hasJsonColumn('session_numbers') && Schema::hasTable('msk_participant_sessions')) {
            $relations[] = 'sessions';
        }
        if (! $this->hasJsonColumn('photos') && Schema::hasTable('msk_participant_photos')) {
            $relations[] = 'photos';
        }
        if ($relations !== []) {
            $this->loadMissing($relations);
        }

        $photos = $this->jsonPhotos();
        if ($photos === null) {
            $photos = Schema::hasTable('msk_participant_photos')
                ? ($this->relationLoaded('photos') ? $this->getRelation('photos') : $this->photos()->get())
                    ->map(static fn (MskParticipantPhoto $photo): array => [
                        'path' => (string) $photo->path,
                        'name' => (string) ($photo->original_name ?? 'Foto'),
                    ])
                    ->values()
                    ->all()
                : [];
        }

        $sessionNumbers = $this->jsonSessionNumbers();
        if ($sessionNumbers === null) {
            $sessionNumbers = Schema::hasTable('msk_participant_sessions')
                ? ($this->relationLoaded('sessions') ? $this->getRelation('sessions') : $this->sessions()->get())
                    ->pluck('session_number')
                    ->map(static fn (mixed $number): int => (int) $number)
                    ->sort()
                    ->values()
                    ->all()
                : [];
        }

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
            'photos' => $photos,
            'msk_month' => (string) ($this->batch_month ?? ''),
            'session_numbers' => $sessionNumbers,
            'notes' => (string) ($this->notes ?? ''),
            'completed_at' => (string) ($this->completed_at ?? ''),
            'journey_bridge_status' => (string) ($this->journey_bridge_status ?? 'belum'),
            'status' => (string) ($this->status ?? 'active'),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function hasJsonColumn(string $column): bool
    {
        return array_key_exists($column, $this->getAttributes());
    }

    /**
     * @return array<int, int>|null
     */
    private function jsonSessionNumbers(): ?array
    {
        if (! array_key_exists('session_numbers', $this->getAttributes())) {
            return null;
        }

        return normalize_msk_session_numbers($this->session_numbers ?? []);
    }

    /**
     * @return array<int, array{path: string, name: string}>|null
     */
    private function jsonPhotos(): ?array
    {
        if (! array_key_exists('photos', $this->getAttributes())) {
            return null;
        }

        $photos = [];
        $rawPhotos = is_array($this->photos) ? $this->photos : [];
        foreach ($rawPhotos as $photo) {
            if (! is_array($photo)) {
                continue;
            }

            $path = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $photos[] = [
                'path' => $path,
                'name' => trim((string) ($photo['name'] ?? $photo['original_name'] ?? '')) ?: 'Foto',
            ];
        }

        return $photos;
    }
}
