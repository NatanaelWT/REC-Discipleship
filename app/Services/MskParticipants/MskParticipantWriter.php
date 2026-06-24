<?php

namespace App\Services\MskParticipants;

use App\Http\Requests\MskParticipants\MskParticipantWriteRequest;
use App\Models\MskParticipant;
use App\Services\Activity\ActivityRecorder;
use Illuminate\Support\Facades\DB;

class MskParticipantWriter
{
    public function __construct(private readonly ActivityRecorder $activity) {}

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
        $participantId = (int) ($payload['id'] ?? 0);
        $existing = $participantId > 0 ? $this->participantForBranch($branchCode, $participantId) : null;
        $existingViewRow = $existing?->toViewArray() ?? [];
        $existingLinkedPersonId = (int) ($existingViewRow['member_id'] ?? 0);

        $uploadResult = $this->uploadedPhotos();
        if ($uploadResult['error'] !== '') {
            return [
                'batch_month' => (string) ($payload['batch_month'] ?? ''),
                'auto_converted' => false,
                'error' => $uploadResult['error'],
            ];
        }
        if ($uploadResult['photos'] !== []) {
            $this->activity->onRollback(fn () => $this->deleteUploadedPhotos($uploadResult['photos']));
        }

        $finalPhotos = $this->mergePhotos($existingViewRow, $payload['remove_photo_paths'] ?? [], $uploadResult['photos']);
        $participantData = $this->participantData($participantId, $payload, $existingViewRow, $finalPhotos);
        $wasLinkedMember = $existingLinkedPersonId > 0;

        $finalLinkedPersonId = (int) ($participantData['member_id'] ?? 0);
        if ($this->memberAlreadyRegisteredInAnotherParticipant($branchCode, $participantId, $finalLinkedPersonId)) {
            $this->deleteUploadedPhotos($uploadResult['photos']);

            return [
                'batch_month' => (string) ($payload['batch_month'] ?? ''),
                'auto_converted' => false,
                'error' => 'duplicate_msk_member',
            ];
        }

        $savedParticipant = $this->persistParticipant($branchCode, $participantData);

        $autoConverted = ! $wasLinkedMember
            && $finalLinkedPersonId > 0
            && msk_is_complete($participantData);

        $removePhotoPaths = $payload['remove_photo_paths'] ?? [];
        if ($removePhotoPaths !== []) {
            $this->activity->onCommit(function () use ($branchCode, $removePhotoPaths): void {
                $participants = $this->participantsForBranch($branchCode);
                foreach ($removePhotoPaths as $pathToDelete) {
                    delete_photo_file_if_unused([], $participants, (string) $pathToDelete);
                }
            });
        }

        return [
            'participant' => $savedParticipant,
            'batch_month' => (string) ($participantData['msk_month'] ?? ''),
            'auto_converted' => $autoConverted,
            'error' => '',
        ];
    }

    /**
     * @param  array<int, int>  $sessionNumbers
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

        $wasLinkedMember = (int) ($participantData['member_id'] ?? 0) > 0;

        $this->persistParticipant($branchCode, $participantData);

        return [
            'auto_converted' => ! $wasLinkedMember
                && (int) ($participantData['member_id'] ?? 0) > 0
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

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $existing
     * @param  array<int, array{path: string, name: string}>  $photos
     * @return array<string, mixed>
     */
    private function participantData(int $participantId, array $payload, array $existing, array $photos): array
    {
        $birthDate = (string) ($payload['birth_date'] ?? '');
        $batchMonth = import_normalize_month_strict((string) ($payload['batch_month'] ?? ''));
        if ($batchMonth === '') {
            $batchMonth = import_normalize_month_strict((string) ($existing['msk_month'] ?? ''));
        }

        return [
            'id' => $participantId,
            'member_id' => (int) ($existing['member_id'] ?? $payload['discipleship_person_id'] ?? 0),
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
     * @param  array<string, mixed>  $participantData
     */
    private function persistParticipant(string $branchCode, array $participantData): MskParticipant
    {
        return DB::transaction(function () use ($branchCode, $participantData): MskParticipant {
            $participantId = (int) ($participantData['id'] ?? 0);
            $participant = $participantId > 0 ? $this->participantForBranch($branchCode, $participantId) : null;
            $participant ??= new MskParticipant([
                'branch_id' => branch_id_from_slug($branchCode),
            ]);

            $birthDate = normalize_ymd_date((string) ($participantData['birth_date'] ?? ''));
            $batchMonth = import_normalize_month_strict((string) ($participantData['msk_month'] ?? ''));
            $fill = [
                'branch_id' => branch_id_from_slug($branchCode),
                'discipleship_person_id' => (int) ($participantData['member_id'] ?? 0) ?: null,
                'full_name' => $this->nullableString($participantData['full_name'] ?? null),
                'gender' => $this->nullableString(normalize_member_gender_value((string) ($participantData['gender'] ?? ''))),
                'birth_date' => $birthDate !== '' ? $birthDate : null,
                'birth_day_month' => $this->nullableString(normalize_member_birth_day_month_value((string) ($participantData['birth_day_month'] ?? ''))),
                'birth_place' => $this->nullableString($participantData['birth_place'] ?? null),
                'address' => $this->nullableString($participantData['address'] ?? null),
                'email' => $this->nullableString(strtolower(trim((string) ($participantData['email'] ?? '')))),
                'whatsapp' => $this->nullableString($participantData['whatsapp'] ?? null),
                'batch_month' => $this->nullableString($batchMonth),
                'notes' => $this->nullableString($participantData['notes'] ?? null),
                'completed_at' => $this->nullableString($participantData['completed_at'] ?? null),
                'journey_bridge_status' => normalize_journey_bridge_status((string) ($participantData['journey_bridge_status'] ?? 'belum')),
                'status' => normalize_msk_participant_status((string) ($participantData['status'] ?? 'active')),
                'session_numbers' => normalize_msk_session_numbers($participantData['session_numbers'] ?? []),
                'photos' => is_array($participantData['photos'] ?? null) ? $participantData['photos'] : [],
            ];

            $participant->fill($fill);
            $participant->save();

            return $participant->fresh() ?? $participant;
        });
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<int, string>  $removePhotoPaths
     * @param  array<int, array{path: string, name: string}>  $uploadedPhotos
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
     * @param  array<int, array{path: string, name: string}>  $photos
     */
    private function deleteUploadedPhotos(array $photos): void
    {
        foreach ($photos as $photo) {
            delete_relative_upload_file((string) $photo['path']);
        }
    }

    private function memberAlreadyRegisteredInAnotherParticipant(string $branchCode, int $participantId, int $personId): bool
    {
        if ($personId < 1) {
            return false;
        }

        return MskParticipant::query()
            ->where('branch_id', branch_id_from_slug($branchCode))
            ->where('discipleship_person_id', $personId)
            ->when($participantId > 0, static fn ($query) => $query->where('id', '!=', $participantId))
            ->exists();
    }

    private function participantForBranch(string $branchCode, int $participantId): ?MskParticipant
    {
        $query = MskParticipant::query()
            ->where('branch_id', branch_id_from_slug($branchCode))
            ->whereKey($participantId);

        return $query->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function participantsForBranch(string $branchCode): array
    {
        $query = MskParticipant::query()
            ->where('branch_id', branch_id_from_slug($branchCode))
            ->orderBy('full_name')
            ->orderBy('id');

        return $query->get()
            ->map(static fn (MskParticipant $participant): array => $participant->toViewArray())
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
