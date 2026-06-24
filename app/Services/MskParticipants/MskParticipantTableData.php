<?php

namespace App\Services\MskParticipants;

use App\Models\MskParticipant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MskParticipantTableData
{
    /** @param array<int, string>|null $branchCodes */
    public function countParticipants(?array $branchCodes = null): int
    {
        $query = MskParticipant::query();
        if ($branchCodes !== null) {
            $branchCodes = $this->normalizeBranchCodes($branchCodes);
            if ($branchCodes === []) {
                return 0;
            }

            $query->whereIn('branch_id', branch_ids_from_slugs($branchCodes));
        }

        try {
            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @param  array<int, string>  $branchCodes
     * @return array<int, array<string, mixed>>
     */
    public function participantsForBranches(array $branchCodes): array
    {
        $branchCodes = $this->normalizeBranchCodes($branchCodes);
        if ($branchCodes === []) {
            return [];
        }

        try {
            return MskParticipant::query()
                ->select(MskParticipant::VIEW_COLUMNS)
                ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
                ->orderBy('full_name')
                ->orderBy('id')
                ->get()
                ->map(static function (MskParticipant $participant): array {
                    $row = $participant->toViewArray();
                    $row['branch_code'] = normalize_public_branch_code((string) $participant->branch_code);

                    return $row;
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @return array{inserted: int, updated: int, sessions: int, photos: int}
     */
    public function replaceBranchRows(string $branchCode, array $participants, bool $deleteMissing = false): array
    {
        $counts = ['inserted' => 0, 'updated' => 0, 'sessions' => 0, 'photos' => 0];
        $branchCode = normalize_public_branch_code($branchCode);
        $branchId = branch_id_from_slug($branchCode);
        if ($branchCode === '' || $branchId === null || ! $this->hasTables()) {
            return $counts;
        }

        DB::transaction(function () use ($branchId, $participants, $deleteMissing, &$counts): void {
            $seenIds = [];
            foreach ($participants as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $participantId = (int) ($row['id'] ?? 0);
                $participant = $participantId > 0
                    ? MskParticipant::query()->where('branch_id', $branchId)->whereKey($participantId)->first()
                    : null;
                $isNew = ! $participant instanceof MskParticipant;
                $participant ??= new MskParticipant;
                $participant->fill($this->participantAttributes($branchId, $row));
                $participant->save();

                $seenIds[] = (int) $participant->getKey();
                $counts[$isNew ? 'inserted' : 'updated']++;
                $counts['sessions'] += count(normalize_msk_session_numbers($row['session_numbers'] ?? []));
                $counts['photos'] += count(extract_msk_participant_photos($row));
            }

            if ($deleteMissing) {
                MskParticipant::query()
                    ->where('branch_id', $branchId)
                    ->when($seenIds !== [], static fn ($query) => $query->whereNotIn('id', $seenIds))
                    ->delete();
            }
        });

        return $counts;
    }

    public function hasTables(): bool
    {
        return Schema::hasTable('msk_participants');
    }

    /**
     * @param  array<int, string>  $branchCodes
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
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function participantAttributes(int $branchId, array $row): array
    {
        $createdAt = $this->timestampFrom([$row['created_at'] ?? null]) ?? now();
        $updatedAt = $this->timestampFrom([$row['updated_at'] ?? null, $row['created_at'] ?? null]) ?? $createdAt;
        $birthDate = normalize_ymd_date((string) ($row['birth_date'] ?? ''));
        $batchMonth = import_normalize_month_strict((string) ($row['msk_month'] ?? ''));

        return [
            'branch_id' => $branchId,
            'discipleship_person_id' => (int) ($row['member_id'] ?? 0) ?: null,
            'full_name' => $this->nullableString($row['full_name'] ?? null),
            'gender' => $this->nullableString(normalize_member_gender_value((string) ($row['gender'] ?? ''))),
            'birth_date' => $birthDate !== '' ? $birthDate : null,
            'birth_day_month' => $this->nullableString(normalize_member_birth_day_month_value((string) ($row['birth_day_month'] ?? ''))),
            'birth_place' => $this->nullableString($row['birth_place'] ?? null),
            'address' => $this->nullableString($row['address'] ?? null),
            'email' => $this->nullableString(strtolower(trim((string) ($row['email'] ?? '')))),
            'whatsapp' => $this->nullableString($row['whatsapp'] ?? null),
            'batch_month' => $this->nullableString($batchMonth),
            'notes' => $this->nullableString($row['notes'] ?? null),
            'completed_at' => $this->nullableString($row['completed_at'] ?? null),
            'journey_bridge_status' => normalize_journey_bridge_status((string) ($row['journey_bridge_status'] ?? 'belum')),
            'status' => normalize_msk_participant_status((string) ($row['status'] ?? 'active')),
            'session_numbers' => normalize_msk_session_numbers($row['session_numbers'] ?? []),
            'photos' => extract_msk_participant_photos($row),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /** @param array<int, mixed> $candidates */
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
