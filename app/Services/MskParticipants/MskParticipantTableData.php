<?php

namespace App\Services\MskParticipants;

use App\Models\MskParticipant;
use App\Models\MskParticipantPhoto;
use App\Models\MskParticipantSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MskParticipantTableData
{
    /**
     * @param array<int, string>|null $branchCodes
     */
    public function countParticipants(?array $branchCodes = null): int
    {
        if (! $this->hasTables()) {
            return 0;
        }

        $query = MskParticipant::query();
        if ($branchCodes !== null) {
            $branchCodes = $this->normalizeBranchCodes($branchCodes);
            if ($branchCodes === []) {
                return 0;
            }

            $query->whereIn('branch_id', branch_ids_from_slugs($branchCodes));
        }

        return (int) $query->count();
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, array<string, mixed>>
     */
    public function participantsForBranches(array $branchCodes): array
    {
        if (! $this->hasTables()) {
            return [];
        }

        $branchCodes = $this->normalizeBranchCodes($branchCodes);
        if ($branchCodes === []) {
            return [];
        }

        $query = MskParticipant::query()
            ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
            ->orderBy('full_name')
            ->orderBy('id');

        $with = [];
        if (! $this->hasJsonColumn('session_numbers') && Schema::hasTable('msk_participant_sessions')) {
            $with['sessions'] = static fn ($query) => $query->orderBy('session_number');
        }
        if (! $this->hasJsonColumn('photos') && Schema::hasTable('msk_participant_photos')) {
            $with['photos'] = static fn ($query) => $query->orderBy('id');
        }
        if ($with !== []) {
            $query->with($with);
        }

        return $query
            ->get()
            ->map(static function (MskParticipant $participant): array {
                $row = $participant->toViewArray();
                $row['branch_code'] = normalize_public_branch_code((string) $participant->branch_code);

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $participants
     * @return array{inserted: int, updated: int, sessions: int, photos: int}
     */
    public function replaceBranchRows(string $branchCode, array $participants, bool $deleteMissing = false): array
    {
        $counts = [
            'inserted' => 0,
            'updated' => 0,
            'sessions' => 0,
            'photos' => 0,
        ];

        $branchCode = normalize_public_branch_code($branchCode);
        if ($branchCode === '' || ! $this->hasTables()) {
            return $counts;
        }

        DB::transaction(function () use ($branchCode, $participants, $deleteMissing, &$counts): void {
            $existingIdsByPublicId = MskParticipant::query()
                ->where('branch_id', branch_id_from_slug($branchCode))
                ->pluck('id', 'public_id')
                ->all();

            $participantRows = [];
            $sessionNumbersByPublicId = [];
            $photosByPublicId = [];
            $seenPublicIds = [];

            foreach ($participants as $participantRow) {
                if (! is_array($participantRow)) {
                    continue;
                }

                $publicId = trim((string) ($participantRow['id'] ?? ''));
                if ($publicId === '') {
                    $publicId = generate_id('msk');
                }

                $participantRows[] = $this->participantTableRow($branchCode, $publicId, $participantRow);
                $sessionNumbersByPublicId[$publicId] = normalize_msk_session_numbers($participantRow['session_numbers'] ?? []);
                $photosByPublicId[$publicId] = extract_msk_participant_photos($participantRow);
                $seenPublicIds[] = $publicId;

                if (isset($existingIdsByPublicId[$publicId])) {
                    $counts['updated']++;
                } else {
                    $counts['inserted']++;
                }
            }

            $seenPublicIds = array_values(array_unique($seenPublicIds));

            if ($participantRows !== []) {
                DB::table('msk_participants')->upsert(
                    $participantRows,
                    ['branch_id', 'public_id'],
                    [
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
                        'updated_at',
                    ],
                );
            }

            $participantIdsByPublicId = MskParticipant::query()
                ->where('branch_id', branch_id_from_slug($branchCode))
                ->whereIn('public_id', $seenPublicIds)
                ->pluck('id', 'public_id')
                ->all();

            $participantIds = array_values(array_map('intval', array_values($participantIdsByPublicId)));
            if ($participantIds !== []) {
                if (Schema::hasTable('msk_participant_sessions')) {
                    MskParticipantSession::query()->whereIn('msk_participant_id', $participantIds)->delete();
                }
                if (Schema::hasTable('msk_participant_photos')) {
                    MskParticipantPhoto::query()->whereIn('msk_participant_id', $participantIds)->delete();
                }
            }

            $now = now();
            $sessionRows = [];
            foreach ($sessionNumbersByPublicId as $publicId => $numbers) {
                $participantId = (int) ($participantIdsByPublicId[$publicId] ?? 0);
                if ($participantId <= 0) {
                    continue;
                }
                foreach ($numbers as $number) {
                    $sessionRows[] = [
                        'msk_participant_id' => $participantId,
                        'session_number' => (int) $number,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($sessionRows, 500) as $chunk) {
                if ($chunk !== [] && Schema::hasTable('msk_participant_sessions')) {
                    DB::table('msk_participant_sessions')->insert($chunk);
                }
            }
            $counts['sessions'] = count($sessionRows);

            $photoRows = [];
            foreach ($photosByPublicId as $publicId => $photos) {
                $participantId = (int) ($participantIdsByPublicId[$publicId] ?? 0);
                if ($participantId <= 0 || ! is_array($photos)) {
                    continue;
                }
                foreach ($photos as $photo) {
                    $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
                    if ($photoPath === '') {
                        continue;
                    }
                    $photoRows[] = [
                        'msk_participant_id' => $participantId,
                        'path' => $photoPath,
                        'original_name' => trim((string) ($photo['name'] ?? '')) ?: 'Foto',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($photoRows, 500) as $chunk) {
                if ($chunk !== [] && Schema::hasTable('msk_participant_photos')) {
                    DB::table('msk_participant_photos')->insert($chunk);
                }
            }
            $counts['photos'] = count($photoRows);

            if ($deleteMissing) {
                MskParticipant::query()
                    ->where('branch_id', branch_id_from_slug($branchCode))
                    ->when($seenPublicIds !== [], static fn ($query) => $query->whereNotIn('public_id', $seenPublicIds))
                    ->delete();
            }
        });

        return $counts;
    }

    public function hasTables(): bool
    {
        return Schema::hasTable('msk_participants')
            && ($this->usesJsonPayloads()
                || (Schema::hasTable('msk_participant_sessions') && Schema::hasTable('msk_participant_photos')));
    }

    /**
     * @param array<int, string> $branchCodes
     * @return array<int, string>
     */
    private function normalizeBranchCodes(array $branchCodes): array
    {
        $normalized = [];
        foreach ($branchCodes as $branchCode) {
            $branchCode = normalize_public_branch_code((string) $branchCode);
            if ($branchCode !== '') {
                $normalized[] = $branchCode;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function participantTableRow(string $branchCode, string $publicId, array $row): array
    {
        $createdAt = $this->timestampFrom([$row['created_at'] ?? null]) ?? now();
        $updatedAt = $this->timestampFrom([$row['updated_at'] ?? null, $row['created_at'] ?? null]) ?? $createdAt;
        $birthDate = normalize_ymd_date((string) ($row['birth_date'] ?? ''));
        $sessionNumbers = normalize_msk_session_numbers($row['session_numbers'] ?? []);
        $photos = extract_msk_participant_photos($row);

        $data = [
            'branch_id' => branch_id_from_slug($branchCode),
            'public_id' => $publicId,
            'member_public_id' => $this->nullableString($row['member_id'] ?? null),
            'full_name' => $this->nullableString($row['full_name'] ?? null),
            'gender' => $this->nullableString(normalize_member_gender_value((string) ($row['gender'] ?? ''))),
            'birth_date' => $birthDate !== '' ? $birthDate : null,
            'birth_day_month' => $this->nullableString(normalize_member_birth_day_month_value((string) ($row['birth_day_month'] ?? ''))),
            'birth_place' => $this->nullableString($row['birth_place'] ?? null),
            'address' => $this->nullableString($row['address'] ?? null),
            'email' => $this->nullableString(strtolower(trim((string) ($row['email'] ?? '')))),
            'whatsapp' => $this->nullableString($row['whatsapp'] ?? null),
            'batch_month' => $this->nullableString(normalize_month_value((string) ($row['msk_month'] ?? date('Y-m')))),
            'notes' => $this->nullableString($row['notes'] ?? null),
            'completed_at' => $this->nullableString($row['completed_at'] ?? null),
            'journey_bridge_status' => normalize_journey_bridge_status((string) ($row['journey_bridge_status'] ?? 'belum')),
            'status' => normalize_msk_participant_status((string) ($row['status'] ?? 'active')),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        if ($this->hasJsonColumn('session_numbers')) {
            $data['session_numbers'] = json_encode($sessionNumbers);
        }
        if ($this->hasJsonColumn('photos')) {
            $data['photos'] = json_encode($photos);
        }

        return $data;
    }

    private function usesJsonPayloads(): bool
    {
        return $this->hasJsonColumn('session_numbers') || $this->hasJsonColumn('photos');
    }

    private function hasJsonColumn(string $column): bool
    {
        return Schema::hasColumn('msk_participants', $column);
    }

    /**
     * @param array<int, mixed> $candidates
     */
    private function timestampFrom(array $candidates): ?CarbonImmutable
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($value);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
