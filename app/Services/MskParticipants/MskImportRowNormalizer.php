<?php

namespace App\Services\MskParticipants;

use App\Support\PersonNameNormalizer;

class MskImportRowNormalizer
{
    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headers
     * @return array{data:array<string,mixed>|null,error:array<string,mixed>|null}
     */
    public function normalize(array $row, array $headers, int $rowNumber): array
    {
        $participantId = trim(import_row_value($row, $headers, ['participant_id', 'id']));
        $fullName = PersonNameNormalizer::normalize(
            import_row_value($row, $headers, ['full_name', 'nama']),
        ) ?? '';
        $whatsapp = trim(import_row_value($row, $headers, ['whatsapp', 'phone', 'nomor_wa', 'nomor_whatsapp']));
        $genderRaw = import_row_value($row, $headers, ['gender', 'jenis_kelamin']);
        $birthDateRaw = import_row_value($row, $headers, ['birth_date', 'tanggal_lahir']);
        $birthPlace = trim(import_row_value($row, $headers, ['birth_place', 'tempat_lahir']));
        $address = trim(import_row_value($row, $headers, ['address', 'alamat']));
        $emailRaw = import_row_value($row, $headers, ['email']);
        $monthRaw = import_row_value($row, $headers, ['msk_month', 'bulan_msk']);
        $sessionRaw = import_row_value($row, $headers, ['session_numbers', 'sessions', 'sesi']);
        $notes = trim(import_row_value($row, $headers, ['notes', 'keterangan']));

        foreach ([
            'full_name' => [$fullName, 255],
            'whatsapp' => [$whatsapp, 255],
            'gender' => [$genderRaw, 32],
            'birth_date' => [$birthDateRaw, 32],
            'birth_place' => [$birthPlace, 255],
            'email' => [$emailRaw, 255],
            'msk_month' => [$monthRaw, 32],
            'session_numbers' => [$sessionRaw, 255],
            'address' => [$address, 65535],
            'notes' => [$notes, 65535],
        ] as $field => [$value, $limit]) {
            if (mb_strlen((string) $value) > $limit) {
                return $this->failure('field_too_long', $rowNumber, $field.' melebihi batas '.$limit.' karakter.');
            }
        }

        if ($fullName === '') {
            return $this->failure('missing_full_name', $rowNumber, 'full_name wajib diisi.');
        }
        if ($participantId !== '' && (! ctype_digit($participantId) || (int) $participantId < 1)) {
            return $this->failure('invalid_participant_id', $rowNumber, 'participant_id harus berupa ID numerik dari hasil export.');
        }

        $month = import_normalize_month_strict($monthRaw);
        if ($month === '') {
            return $this->failure('invalid_msk_month', $rowNumber, 'msk_month wajib berformat YYYY-MM.');
        }

        $sessionTokens = import_split_csv_tokens($sessionRaw);
        $sessionNumbers = import_parse_msk_session_numbers($sessionRaw);
        $invalidSession = $sessionTokens === [] || $sessionNumbers === [];
        foreach ($sessionTokens as $token) {
            if (! preg_match('/^\d+$/', $token) || (int) $token < 1 || (int) $token > 12) {
                $invalidSession = true;
                break;
            }
        }
        if ($invalidSession) {
            return $this->failure('invalid_sessions', $rowNumber, 'session_numbers harus angka 1-12 dipisah koma.');
        }

        $gender = import_normalize_gender_value($genderRaw);
        if ($genderRaw !== '' && $gender === '') {
            return $this->failure('invalid_gender', $rowNumber, 'gender tidak valid.');
        }

        $birthDate = '';
        if ($birthDateRaw !== '') {
            $birthDate = normalize_ymd_date($birthDateRaw);
            if ($birthDate === '') {
                return $this->failure('invalid_birth_date', $rowNumber, 'birth_date wajib berformat YYYY-MM-DD.');
            }
        }

        $email = strtolower(trim($emailRaw));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->failure('invalid_email', $rowNumber, 'email tidak valid.');
        }

        $identity = discipleship_unified_identity_key($fullName, $whatsapp);

        return [
            'data' => [
                'row_number' => $rowNumber,
                'participant_id' => $participantId !== '' ? (int) $participantId : null,
                'identity_key' => $identity !== '' ? hash('sha256', $identity) : null,
                'full_name' => $fullName,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'birth_place' => $birthPlace,
                'address' => $address,
                'email' => $email,
                'whatsapp' => $whatsapp,
                'batch_month' => $month,
                'session_numbers' => $sessionNumbers,
                'notes' => $notes,
            ],
            'error' => null,
        ];
    }

    /** @return array{data:null,error:array{code:string,row:int,message:string}} */
    private function failure(string $code, int $row, string $message): array
    {
        return ['data' => null, 'error' => ['code' => $code, 'row' => $row, 'message' => $message]];
    }
}
