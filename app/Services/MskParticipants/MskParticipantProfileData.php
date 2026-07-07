<?php

namespace App\Services\MskParticipants;

class MskParticipantProfileData
{
    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<string, array<string, mixed>>  $histories
     * @return array<string, array<string, mixed>>
     */
    public function forParticipants(array $participants, array $histories): array
    {
        $profiles = [];
        foreach ($participants as $participant) {
            $participantId = trim((string) ($participant['id'] ?? ''));
            if ($participantId === '') {
                continue;
            }
            $profiles[$participantId] = $this->profile(
                $participant,
                is_array($histories[$participantId] ?? null) ? $histories[$participantId] : [],
            );
        }

        return $profiles;
    }

    /** @return array<string, mixed> */
    private function profile(array $participant, array $history): array
    {
        $fullName = trim((string) ($participant['full_name'] ?? '')) ?: '-';
        $gender = normalize_member_gender_value((string) ($participant['gender'] ?? '')) ?: '-';
        $birthPlace = trim((string) ($participant['birth_place'] ?? '')) ?: '-';
        $birthDate = normalize_ymd_date((string) ($participant['birth_date'] ?? ''));
        $birthDateLabel = $birthDate !== '' ? format_indo_date($birthDate) : '-';
        $email = strtolower(trim((string) ($participant['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = '';
        }
        $whatsapp = trim((string) ($participant['whatsapp'] ?? ''));
        $waDigits = preg_replace('/\D+/', '', $whatsapp) ?? '';
        if (str_starts_with($waDigits, '0')) {
            $waDigits = '62'.substr($waDigits, 1);
        }
        $month = import_normalize_month_strict((string) ($participant['msk_month'] ?? ''));
        $sessions = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
        $sessionCount = min(12, count($sessions));
        $status = normalize_msk_participant_status((string) ($participant['status'] ?? 'active'));
        $statusLabel = 'Belum';
        $statusClass = 'is-pending';
        if ($status === 'inactive') {
            $statusLabel = 'Nonaktif';
            $statusClass = 'is-inactive';
        } elseif ($sessionCount >= 12) {
            $statusLabel = 'Selesai';
            $statusClass = 'is-complete';
        } elseif ($sessionCount > 0) {
            $statusLabel = 'Proses';
            $statusClass = 'is-progress';
        }

        $initials = '';
        foreach (array_slice(preg_split('/\s+/', $fullName) ?: [], 0, 2) as $part) {
            $initials .= strtoupper(substr(trim((string) $part), 0, 1));
        }

        $photos = [];
        foreach (extract_msk_participant_photos($participant) as $index => $photo) {
            $path = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            $url = $path !== '' ? secure_upload_url($path) : '';
            if ($url !== '') {
                $photos[] = ['label' => 'Foto '.($index + 1), 'url' => $url];
            }
        }

        return [
            'full_name' => $fullName,
            'initials' => $initials !== '' ? $initials : 'MS',
            'person_id' => trim((string) ($history['person_id'] ?? '')),
            'gender' => $gender,
            'birth_place' => $birthPlace,
            'birth_date' => $birthDateLabel,
            'address' => trim((string) ($participant['address'] ?? '')) ?: '-',
            'email' => $email,
            'whatsapp' => $whatsapp,
            'whatsapp_url' => $waDigits !== '' ? 'https://wa.me/'.$waDigits : '',
            'batch' => $month !== '' ? format_indo_month($month) : '-',
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'session_count' => $sessionCount,
            'session_progress' => $sessionCount.'/12 sesi',
            'session_percent' => max(0, min(100, (int) round(($sessionCount / 12) * 100))),
            'session_label' => $sessions !== [] ? 'Sesi '.implode(', ', array_map('strval', $sessions)) : '-',
            'notes' => trim((string) ($participant['notes'] ?? '')) ?: '-',
            'photos' => $photos,
            'linked' => ! empty($history['linked']),
            'current_mentors' => is_array($history['current_mentors'] ?? null) ? $history['current_mentors'] : [],
            'current_groups' => is_array($history['current_groups'] ?? null) ? $history['current_groups'] : [],
            'current_stage' => normalize_dg_progress_value((string) ($history['current_stage'] ?? '')),
            'member_items' => is_array($history['member_items'] ?? null) ? $history['member_items'] : [],
            'leader_items' => is_array($history['leader_items'] ?? null) ? $history['leader_items'] : [],
            'is_external_context' => ! empty($history['is_external_context']),
        ];
    }
}
