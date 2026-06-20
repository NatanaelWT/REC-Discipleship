<?php

namespace App\Models;

use App\Models\Concerns\ResolvesBranchSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MskParticipant extends Model
{
    use ResolvesBranchSlug;

    protected $fillable = [
        'branch_id',
        'discipleship_person_id',
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

    public function discipleshipPerson(): BelongsTo
    {
        return $this->belongsTo(DiscipleshipPerson::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toViewArray(): array
    {
        $photos = $this->jsonPhotos();
        $sessionNumbers = normalize_msk_session_numbers($this->session_numbers ?? []);

        return [
            'id' => (string) $this->getKey(),
            'member_id' => $this->discipleship_person_id !== null ? (string) $this->discipleship_person_id : '',
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

    /** @return array<int, array{path: string, name: string}> */
    private function jsonPhotos(): array
    {
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
