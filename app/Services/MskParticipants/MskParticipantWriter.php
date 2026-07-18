<?php

namespace App\Services\MskParticipants;

use App\Http\Requests\MskParticipants\MskParticipantWriteRequest;
use App\Models\Person;
use App\Services\Media\ClientImageVariantStore;
use App\Services\Mutation\MutationLifecycle;
use Illuminate\Support\Facades\DB;

class MskParticipantWriter
{
    public function __construct(
        private readonly MutationLifecycle $lifecycle,
        private readonly ClientImageVariantStore $variantStore,
    ) {}

    /**
     * @return array{
     *     participant?: Person,
     *     batch_month: string,
     *     auto_converted: bool,
     *     error: string
     * }
     */
    public function save(MskParticipantWriteRequest $request): array
    {
        $payload = $request->payload();
        $branchCode = normalize_user_branch(current_user_branch());
        $participantId = (int) ($payload['id'] ?? 0);
        $existing = $participantId > 0 ? $this->participantForBranch($branchCode, $participantId) : null;
        $existingViewRow = $existing?->toViewArray() ?? [];
        $existingLinkedPersonId = $existing instanceof Person ? (int) $existing->getKey() : 0;

        $uploadResult = $this->uploadedPhotos($request);
        if ($uploadResult['error'] !== '') {
            return [
                'batch_month' => (string) ($payload['batch_month'] ?? ''),
                'auto_converted' => false,
                'error' => $uploadResult['error'],
            ];
        }
        if ($uploadResult['photos'] !== []) {
            $this->lifecycle->onRollback(fn () => $this->deleteUploadedPhotos($uploadResult['photos']));
        }

        $finalPhotos = $this->mergePhotos($existingViewRow, $payload['remove_photo_paths'] ?? [], $uploadResult['photos']);
        $participantData = $this->participantData($participantId, $payload, $existingViewRow, $finalPhotos);
        $wasLinkedMember = $existingLinkedPersonId > 0;

        $savedParticipant = $this->persistParticipant($branchCode, $participantData);

        $autoConverted = false;

        $removePhotoPaths = $payload['remove_photo_paths'] ?? [];
        if ($removePhotoPaths !== []) {
            $this->lifecycle->onCommit(function () use ($branchCode, $removePhotoPaths): void {
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
    public function updateSessions(Person $participant, array $sessionNumbers): array
    {
        $branchCode = normalize_user_branch(current_user_branch());
        $participant = $this->currentBranchParticipant($participant);
        if ($participant === null) {
            return ['auto_converted' => false, 'error' => 'invalid_msk_participant'];
        }

        $participantData = $participant->toViewArray();
        $participantData['session_numbers'] = normalize_msk_session_numbers($sessionNumbers);
        $participantData['updated_at'] = now_iso();

        $this->persistParticipant($branchCode, $participantData);

        return [
            'auto_converted' => false,
            'error' => '',
        ];
    }

    /**
     * @return array{error: string}
     */
    public function setStatus(Person $participant, string $status): array
    {
        $branchCode = normalize_user_branch(current_user_branch());
        $participant = $this->currentBranchParticipant($participant);
        if ($participant === null) {
            return ['error' => 'invalid_msk_participant'];
        }

        $participant->forceFill([
            'status' => normalize_msk_participant_status($status),
        ])->save();

        return ['error' => ''];
    }

    /**
     * @return array{error: string}
     */
    public function deletePermanently(Person $participant): array
    {
        $participant = $this->currentBranchParticipant($participant);
        if ($participant === null) {
            return ['error' => 'invalid_msk_participant'];
        }

        $participant = Person::query()
            ->where('branch_id', (int) $participant->branch_id)
            ->whereKey((int) $participant->getKey())
            ->lockForUpdate()
            ->first();
        if (! $participant instanceof Person) {
            return ['error' => 'invalid_msk_participant'];
        }
        if (normalize_msk_participant_status((string) $participant->status) !== 'inactive') {
            return ['error' => 'msk_participant_must_be_inactive'];
        }

        $photoPaths = $this->participantPhotoPaths($participant->toViewArray());
        $participant->delete();

        if ($photoPaths !== []) {
            $this->lifecycle->onCommit(function () use ($photoPaths): void {
                $usedPhotoPaths = $this->usedPhotoPathMap();
                foreach ($photoPaths as $photoPath) {
                    if (! isset($usedPhotoPaths[$photoPath])) {
                        delete_relative_upload_file($photoPath);
                    }
                }
            });
        }

        return ['error' => ''];
    }

    public function currentBranchParticipant(Person $participant): ?Person
    {
        $branchCode = normalize_user_branch(current_user_branch());
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
            'member_id' => (int) ($existing['member_id'] ?? $payload['person_id'] ?? $payload['discipleship_person_id'] ?? 0),
            'full_name' => (string) ($payload['full_name'] ?? ''),
            'gender' => (string) ($payload['gender'] ?? ''),
            'birth_date' => $birthDate,
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
    private function persistParticipant(string $branchCode, array $participantData): Person
    {
        return DB::transaction(function () use ($branchCode, $participantData): Person {
            $participantId = (int) ($participantData['id'] ?? 0);
            $requestedPersonId = (int) ($participantData['member_id'] ?? 0);
            $participant = $participantId > 0
                ? $this->participantForBranch($branchCode, $participantId)
                : ($requestedPersonId > 0 ? $this->participantForBranch($branchCode, $requestedPersonId) : null);
            if (! $participant instanceof Person && $participantId < 1) {
                $participant = $this->matchingParticipantForIdentity($branchCode, $participantData);
            }
            $participant ??= new Person([
                'branch_id' => branch_id_from_slug($branchCode),
            ]);

            $birthDate = normalize_ymd_date((string) ($participantData['birth_date'] ?? ''));
            $batchMonth = import_normalize_month_strict((string) ($participantData['msk_month'] ?? ''));
            $fill = [
                'branch_id' => branch_id_from_slug($branchCode),
                'full_name' => $this->nullableString($participantData['full_name'] ?? null),
                'gender' => $this->nullableString(normalize_member_gender_value((string) ($participantData['gender'] ?? ''))),
                'birth_date' => $birthDate !== '' ? $birthDate : null,
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

            $photosByPath[$path] = $photo;
        }

        foreach ($uploadedPhotos as $photo) {
            $storedPhoto = $photo;
            unset($storedPhoto['_created'], $storedPhoto['storage_reused']);
            $photosByPath[$photo['path']] = $storedPhoto;
        }

        return array_values($photosByPath);
    }

    /**
     * @return array{photos: array<int, array{path: string, name: string}>, error: string}
     */
    private function uploadedPhotos(MskParticipantWriteRequest $request): array
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
                'sha256' => (string) ($photo['sha256'] ?? ''),
                'size' => (int) ($photo['size'] ?? 0),
                'width' => (int) ($photo['width'] ?? 0),
                'height' => (int) ($photo['height'] ?? 0),
                '_created' => empty($photo['storage_reused']),
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

        return [
            'photos' => $this->variantStore->attachFromRequest(
                $photos,
                $request,
                'participant_photo_web_variants',
                'participant_photo_thumbnails',
                'uploads/peserta',
            ),
            'error' => '',
        ];
    }

    /**
     * @param  array<int, array{path: string, name: string}>  $photos
     */
    private function deleteUploadedPhotos(array $photos): void
    {
        $this->variantStore->cleanupCreatedDerivatives();
        foreach ($photos as $photo) {
            if (! empty($photo['_created'])) {
                delete_relative_upload_file((string) $photo['path']);
            }
        }
    }

    private function participantForBranch(string $branchCode, int $participantId): ?Person
    {
        $query = Person::query()
            ->where('branch_id', branch_id_from_slug($branchCode))
            ->whereKey($participantId);

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $participantData
     */
    private function matchingParticipantForIdentity(string $branchCode, array $participantData): ?Person
    {
        $identityKey = $this->identityKey(
            (string) ($participantData['full_name'] ?? ''),
            (string) ($participantData['whatsapp'] ?? ''),
        );
        $branchId = branch_id_from_slug($branchCode);
        if ($identityKey === '' || $branchId === null) {
            return null;
        }

        return Person::query()
            ->where('branch_id', $branchId)
            ->get()
            ->filter(fn (Person $participant): bool => $this->identityKey(
                (string) ($participant->full_name ?? ''),
                (string) ($participant->whatsapp ?? ''),
            ) === $identityKey)
            ->sortByDesc(fn (Person $participant): int => $this->participantCompletenessScore($participant))
            ->first();
    }

    private function identityKey(string $fullName, string $whatsapp): string
    {
        if (normalize_whatsapp_digits($whatsapp) === '') {
            return '';
        }

        return discipleship_unified_identity_key($fullName, $whatsapp);
    }

    private function participantCompletenessScore(Person $participant): int
    {
        return (count(normalize_msk_session_numbers($participant->session_numbers ?? [])) * 100)
            + (trim((string) ($participant->batch_month ?? '')) !== '' ? 10 : 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function participantsForBranch(string $branchCode): array
    {
        $query = Person::query()
            ->where('branch_id', branch_id_from_slug($branchCode))
            ->orderBy('full_name')
            ->orderBy('id');

        return $query->get()
            ->map(static fn (Person $participant): array => $participant->toViewArray())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $participant
     * @return array<int, string>
     */
    private function participantPhotoPaths(array $participant): array
    {
        $paths = [];
        foreach (extract_msk_participant_photos($participant) as $photo) {
            foreach (['path', 'web_path', 'thumbnail_path'] as $key) {
                $path = sanitize_relative_upload_path((string) ($photo[$key] ?? ''));
                if ($path !== '') {
                    $paths[$path] = true;
                }
            }
        }

        return array_keys($paths);
    }

    /** @return array<string, true> */
    private function usedPhotoPathMap(): array
    {
        $usedPhotoPaths = [];
        foreach (Person::query()->select(['id', 'photos'])->cursor() as $participant) {
            $usedPaths = $this->participantPhotoPaths([
                'photos' => is_array($participant->photos) ? $participant->photos : [],
            ]);
            foreach ($usedPaths as $usedPath) {
                $usedPhotoPaths[$usedPath] = true;
            }
        }

        return $usedPhotoPaths;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
