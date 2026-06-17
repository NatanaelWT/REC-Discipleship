<?php

namespace App\Services\MskParticipants;

use App\Http\Requests\MskParticipants\MskParticipantWriteRequest;
use App\Models\MskParticipant;
use App\Models\MskParticipantPhoto;
use App\Models\MskParticipantSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MskParticipantWriter
{
    /**
     * @return array{
     *     participant?: MskParticipant,
     *     batch_month: string,
     *     auto_converted: bool,
     *     error: string
     * }
     */
    public function save(MskParticipantWriteRequest $request): array
    {
        $payload = $request->payload();
        $branchCode = normalize_public_branch_code(current_user_branch());
        $publicId = trim((string) ($payload['public_id'] ?? ''));
        if ($publicId === '') {
            $publicId = generate_id('msk');
        }

        $existing = $this->participantForBranch($branchCode, $publicId);
        $existingViewRow = $existing?->toViewArray() ?? [];
        $existingLinkedMemberId = trim((string) ($existingViewRow['member_id'] ?? ''));

        $uploadResult = $this->uploadedPhotos();
        if ($uploadResult['error'] !== '') {
            return [
                'batch_month' => (string) ($payload['batch_month'] ?? ''),
                'auto_converted' => false,
                'error' => $uploadResult['error'],
            ];
        }

        $finalPhotos = $this->mergePhotos($existingViewRow, $payload['remove_photo_paths'] ?? [], $uploadResult['photos']);
        $participantData = $this->participantData($publicId, $payload, $existingViewRow, $finalPhotos);
        $wasLinkedMember = $existingLinkedMemberId !== '';

        $finalLinkedMemberId = trim((string) ($participantData['member_id'] ?? ''));
        if ($this->memberAlreadyRegisteredInAnotherParticipant($branchCode, $publicId, $finalLinkedMemberId)) {
            $this->deleteUploadedPhotos($uploadResult['photos']);

            return [
                'batch_month' => (string) ($payload['batch_month'] ?? ''),
                'auto_converted' => false,
                'error' => 'duplicate_msk_member',
            ];
        }

        $savedParticipant = $this->persistParticipant($branchCode, $participantData);

        $autoConverted = ! $wasLinkedMember
            && $finalLinkedMemberId !== ''
            && msk_is_complete($participantData);

        $participants = $this->participantsForBranch($branchCode);
        foreach ($payload['remove_photo_paths'] ?? [] as $pathToDelete) {
            delete_photo_file_if_unused([], $participants, (string) $pathToDelete);
        }

        return [
            'participant' => $savedParticipant,
            'batch_month' => (string) ($participantData['msk_month'] ?? ''),
            'auto_converted' => $autoConverted,
            'error' => '',
        ];
    }

    /**
     * @param array<int, int> $sessionNumbers
     * @return array{auto_converted: bool, error: string}
     */
    public function updateSessions(MskParticipant $participant, array $sessionNumbers): array
    {
        $branchCode = normalize_public_branch_code(current_user_branch());
        $participant = $this->currentBranchParticipant($participant);
        if ($participant === null) {
            return ['auto_converted' => false, 'error' => 'invalid_msk_participant'];
        }

        $participantData = $participant->toViewArray();
        $participantData['session_numbers'] = normalize_msk_session_numbers($sessionNumbers);
        $participantData['updated_at'] = now_iso();

        $wasLinkedMember = trim((string) ($participantData['member_id'] ?? '')) !== '';

        $this->persistParticipant($branchCode, $participantData);

        return [
            'auto_converted' => ! $wasLinkedMember
                && trim((string) ($participantData['member_id'] ?? '')) !== ''
                && msk_is_complete($participantData),
            'error' => '',
        ];
    }

    /**
     * @return array{error: string}
     */
    public function setStatus(MskParticipant $participant, string $status): array
    {
        $branchCode = normalize_public_branch_code(current_user_branch());
        $participant = $this->currentBranchParticipant($participant);
        if ($participant === null) {
            return ['error' => 'invalid_msk_participant'];
        }

        $participant->forceFill([
            'status' => normalize_msk_participant_status($status),
        ])->save();

        return ['error' => ''];
    }

    public function currentBranchParticipant(MskParticipant $participant): ?MskParticipant
    {
        $branchCode = normalize_public_branch_code(current_user_branch());
        if ((string) $participant->branch_code === $branchCode) {
            return $participant;
        }

        return $this->participantForBranch($branchCode, (string) $participant->public_id);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $existing
     * @param array<int, array{path: string, name: string}> $photos
     * @return array<string, mixed>
     */
    private function participantData(string $publicId, array $payload, array $existing, array $photos): array
    {
        $birthDate = (string) ($payload['birth_date'] ?? '');
        $batchMonth = normalize_month_value((string) ($payload['batch_month'] ?? ''));
        if ($batchMonth === '') {
            $batchMonth = normalize_month_value((string) ($existing['msk_month'] ?? date('Y-m')));
        }

        return [
            'id' => $publicId,
            'member_id' => trim((string) ($existing['member_id'] ?? $payload['member_public_id'] ?? '')),
            'full_name' => (string) ($payload['full_name'] ?? ''),
            'gender' => (string) ($payload['gender'] ?? ''),
            'birth_date' => $birthDate,
            'birth_day_month' => $birthDate !== '' ? date('d-m', strtotime($birthDate)) : (string) ($existing['birth_day_month'] ?? ''),
            'birth_place' => (string) ($payload['birth_place'] ?? ''),
            'address' => (string) ($payload['address'] ?? ''),
            'email' => (string) ($payload['email'] ?? ''),
            'whatsapp' => (string) ($payload['whatsapp'] ?? ''),
            'photos' => $photos,
            'msk_month' => $batchMonth,
            'session_numbers' => normalize_msk_session_numbers($payload['session_numbers'] ?? []),
            'notes' => (string) ($payload['notes'] ?? ''),
            'completed_at' => (string) ($existing['completed_at'] ?? ''),
            'journey_bridge_status' => normalize_journey_bridge_status((string) ($existing['journey_bridge_status'] ?? 'belum')),
            'status' => normalize_msk_participant_status((string) ($existing['status'] ?? 'active')),
            'created_at' => (string) ($existing['created_at'] ?? now_iso()),
            'updated_at' => now_iso(),
        ];
    }

    /**
     * @param array<string, mixed> $participantData
     */
    private function persistParticipant(string $branchCode, array $participantData): MskParticipant
    {
        return DB::transaction(function () use ($branchCode, $participantData): MskParticipant {
            $participant = $this->participantForBranch($branchCode, (string) $participantData['id']);
            $participant ??= new MskParticipant([
                'branch_code' => $branchCode,
                'public_id' => (string) $participantData['id'],
            ]);

            $birthDate = normalize_ymd_date((string) ($participantData['birth_date'] ?? ''));
            $fill = [
                'branch_code' => $branchCode,
                'public_id' => (string) $participantData['id'],
                'member_public_id' => $this->nullableString($participantData['member_id'] ?? null),
                'full_name' => $this->nullableString($participantData['full_name'] ?? null),
                'gender' => $this->nullableString(normalize_member_gender_value((string) ($participantData['gender'] ?? ''))),
                'birth_date' => $birthDate !== '' ? $birthDate : null,
                'birth_day_month' => $this->nullableString(normalize_member_birth_day_month_value((string) ($participantData['birth_day_month'] ?? ''))),
                'birth_place' => $this->nullableString($participantData['birth_place'] ?? null),
                'address' => $this->nullableString($participantData['address'] ?? null),
                'email' => $this->nullableString(strtolower(trim((string) ($participantData['email'] ?? '')))),
                'whatsapp' => $this->nullableString($participantData['whatsapp'] ?? null),
                'batch_month' => $this->nullableString(normalize_month_value((string) ($participantData['msk_month'] ?? date('Y-m')))),
                'notes' => $this->nullableString($participantData['notes'] ?? null),
                'completed_at' => $this->nullableString($participantData['completed_at'] ?? null),
                'journey_bridge_status' => normalize_journey_bridge_status((string) ($participantData['journey_bridge_status'] ?? 'belum')),
                'status' => normalize_msk_participant_status((string) ($participantData['status'] ?? 'active')),
            ];

            if ($this->hasJsonColumn('session_numbers')) {
                $fill['session_numbers'] = normalize_msk_session_numbers($participantData['session_numbers'] ?? []);
            }
            if ($this->hasJsonColumn('photos')) {
                $fill['photos'] = is_array($participantData['photos'] ?? null) ? $participantData['photos'] : [];
            }

            $participant->fill($fill);
            $participant->save();

            $this->replaceSessions($participant, $participantData['session_numbers'] ?? []);
            $this->replacePhotos($participant, is_array($participantData['photos'] ?? null) ? $participantData['photos'] : []);

            return $participant->fresh($this->refreshRelations()) ?? $participant;
        });
    }

    /**
     * @param mixed $sessionNumbers
     */
    private function replaceSessions(MskParticipant $participant, mixed $sessionNumbers): void
    {
        if (! Schema::hasTable('msk_participant_sessions')) {
            if ($this->hasJsonColumn('session_numbers')) {
                $participant->forceFill([
                    'session_numbers' => normalize_msk_session_numbers($sessionNumbers),
                ])->save();
            }

            return;
        }

        MskParticipantSession::query()
            ->where('msk_participant_id', $participant->id)
            ->delete();

        $now = now();
        $rows = [];
        foreach (normalize_msk_session_numbers($sessionNumbers) as $sessionNumber) {
            $rows[] = [
                'msk_participant_id' => $participant->id,
                'session_number' => (int) $sessionNumber,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            MskParticipantSession::query()->insert($rows);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $photos
     */
    private function replacePhotos(MskParticipant $participant, array $photos): void
    {
        if (! Schema::hasTable('msk_participant_photos')) {
            if ($this->hasJsonColumn('photos')) {
                $participant->forceFill([
                    'photos' => $photos,
                ])->save();
            }

            return;
        }

        MskParticipantPhoto::query()
            ->where('msk_participant_id', $participant->id)
            ->delete();

        $now = now();
        $rows = [];
        foreach ($photos as $photo) {
            $path = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $rows[] = [
                'msk_participant_id' => $participant->id,
                'path' => $path,
                'original_name' => trim((string) ($photo['name'] ?? '')) ?: 'Foto',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            MskParticipantPhoto::query()->insert($rows);
        }
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<int, string> $removePhotoPaths
     * @param array<int, array{path: string, name: string}> $uploadedPhotos
     * @return array<int, array{path: string, name: string}>
     */
    private function mergePhotos(array $existing, array $removePhotoPaths, array $uploadedPhotos): array
    {
        $removeMap = [];
        foreach ($removePhotoPaths as $pathToRemove) {
            $path = sanitize_relative_upload_path((string) $pathToRemove);
            if ($path !== '') {
                $removeMap[$path] = true;
            }
        }

        $photosByPath = [];
        foreach (extract_msk_participant_photos($existing) as $photo) {
            $path = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($path === '' || isset($removeMap[$path])) {
                continue;
            }

            $photosByPath[$path] = [
                'path' => $path,
                'name' => trim((string) ($photo['name'] ?? '')) ?: 'Foto',
            ];
        }

        foreach ($uploadedPhotos as $photo) {
            $photosByPath[$photo['path']] = $photo;
        }

        return array_values($photosByPath);
    }

    /**
     * @return array{photos: array<int, array{path: string, name: string}>, error: string}
     */
    private function uploadedPhotos(): array
    {
        $uploadError = '';
        $uploadedPhotos = [];
        if (isset($_FILES['participant_photos']) && is_array($_FILES['participant_photos'])) {
            $uploadedPhotos = upload_member_photos($_FILES['participant_photos'], $uploadError);
        }

        $photos = [];
        foreach ($uploadedPhotos as $photo) {
            $path = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $photos[] = [
                'path' => $path,
                'name' => trim((string) ($photo['name'] ?? '')) ?: 'Foto',
            ];
        }

        if ($uploadError !== '') {
            $error = match ($uploadError) {
                'invalid_member_photo_type' => 'invalid_msk_photo_type',
                'member_photo_too_large' => 'msk_photo_too_large',
                default => 'msk_photo_upload_failed',
            };

            return ['photos' => [], 'error' => $error];
        }

        return ['photos' => $photos, 'error' => ''];
    }

    /**
     * @param array<int, array{path: string, name: string}> $photos
     */
    private function deleteUploadedPhotos(array $photos): void
    {
        foreach ($photos as $photo) {
            delete_relative_upload_file((string) $photo['path']);
        }
    }

    private function memberAlreadyRegisteredInAnotherParticipant(string $branchCode, string $publicId, string $memberPublicId): bool
    {
        if ($memberPublicId === '') {
            return false;
        }

        return MskParticipant::query()
            ->where('branch_code', $branchCode)
            ->where('member_public_id', $memberPublicId)
            ->where('public_id', '!=', $publicId)
            ->exists();
    }

    private function participantForBranch(string $branchCode, string $publicId): ?MskParticipant
    {
        $query = MskParticipant::query()
            ->where('branch_code', $branchCode)
            ->where('public_id', $publicId);

        $relations = $this->refreshRelations();
        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function participantsForBranch(string $branchCode): array
    {
        $query = MskParticipant::query()
            ->where('branch_code', $branchCode)
            ->orderBy('full_name')
            ->orderBy('id');

        $relations = $this->refreshRelations();
        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->get()
            ->map(static fn (MskParticipant $participant): array => $participant->toViewArray())
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>|array<string, \Closure>
     */
    private function refreshRelations(): array
    {
        $relations = [];
        if (! $this->hasJsonColumn('session_numbers') && Schema::hasTable('msk_participant_sessions')) {
            $relations['sessions'] = static fn ($query) => $query->orderBy('session_number');
        }
        if (! $this->hasJsonColumn('photos') && Schema::hasTable('msk_participant_photos')) {
            $relations['photos'] = static fn ($query) => $query->orderBy('id');
        }

        return $relations;
    }

    private function hasJsonColumn(string $column): bool
    {
        return Schema::hasColumn('msk_participants', $column);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
