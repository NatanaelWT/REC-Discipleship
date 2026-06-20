<?php

namespace App\Services\MskParticipants;

use App\Http\Requests\MskParticipants\ImportMskParticipantsRequest;

class MskParticipantImportService
{
    public function __construct(
        private readonly MskParticipantTableData $tableData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function import(ImportMskParticipantsRequest $request): array
    {
        $branchCode = normalize_public_branch_code(current_user_branch());
        $participants = $this->tableData->participantsForBranches([$branchCode]);

        $file = $request->file('import_pemuridan_excel');
        if ($file === null) {
            return ['error' => 'import_missing_file'];
        }
        if (! $file->isValid()) {
            return ['error' => 'import_upload_failed'];
        }

        $tmpPath = trim((string) $file->getRealPath());
        if ($tmpPath === '') {
            return ['error' => 'import_upload_failed'];
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower(pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
        }
        if ($extension !== 'xlsx') {
            return ['error' => 'import_invalid_file_type'];
        }

        $size = (int) ($file->getSize() ?? 0);
        if ($size <= 0 || $size > (10 * 1024 * 1024)) {
            return ['error' => 'import_file_too_large'];
        }

        $xlsxError = '';
        $sheets = import_read_xlsx_sheets($tmpPath, $xlsxError);
        if ($xlsxError !== '') {
            return ['error' => $xlsxError === 'zip_unavailable' ? 'import_zip_unavailable' : 'import_invalid_excel'];
        }

        $sheetMap = [];
        foreach ($sheets as $sheetName => $rows) {
            $sheetMap[import_sheet_name_key((string) $sheetName)] = is_array($rows) ? $rows : [];
        }

        $mskRows = $sheetMap['kelas msk'] ?? null;
        if (! is_array($mskRows)) {
            return ['error' => 'import_missing_sheet'];
        }
        if (count($mskRows) === 0) {
            return ['error' => 'import_empty_sheet'];
        }

        $importErrors = [];
        $mskInserted = 0;
        $mskUpdated = 0;

        $mskHeaderMap = import_build_header_map($mskRows[0] ?? []);
        foreach (['full_name', 'msk_month', 'session_numbers'] as $requiredHeader) {
            if (! isset($mskHeaderMap[$requiredHeader])) {
                $importErrors[] = 'Sheet Kelas MSK: kolom wajib "'.$requiredHeader.'" tidak ditemukan.';
            }
        }

        if ($importErrors === []) {
            [$participants, $mskInserted, $mskUpdated, $importErrors] = $this->mergeRows(
                $participants,
                $mskRows,
                $mskHeaderMap,
            );
        }

        if ($importErrors === []) {
            if ($mskInserted > 0 || $mskUpdated > 0) {
                $this->tableData->replaceBranchRows($branchCode, $participants, true);
            }
        } else {
            $mskInserted = 0;
            $mskUpdated = 0;
        }

        $redirectParams = [
            'imported' => 1,
            'import_msk_inserted' => $mskInserted,
            'import_msk_updated' => $mskUpdated,
            'import_error_count' => count($importErrors),
        ];
        if (count($importErrors) > 0) {
            $redirectParams['import_error_preview'] = substr((string) $importErrors[0], 0, 220);
        }

        return $redirectParams;
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<int, mixed>  $mskRows
     * @param  array<string, int>  $mskHeaderMap
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: int, 3: array<int, string>}
     */
    private function mergeRows(array $participants, array $mskRows, array $mskHeaderMap): array
    {
        $importErrors = [];
        $mskInserted = 0;
        $mskUpdated = 0;
        $mskIndexById = [];
        $mskIndexByIdentity = [];

        foreach ($participants as $idx => $participant) {
            $participantId = trim((string) ($participant['id'] ?? ''));
            if ($participantId !== '') {
                $mskIndexById[$participantId] = $idx;
            }
            $identityKey = discipleship_unified_identity_key((string) ($participant['full_name'] ?? ''), (string) ($participant['whatsapp'] ?? ''));
            if ($identityKey !== '') {
                $mskIndexByIdentity[$identityKey][] = $idx;
            }
        }

        $seenMskRowKeys = [];
        foreach ($mskRows as $rowIndex => $row) {
            if ($rowIndex === 0 || ! is_array($row) || import_is_blank_row($row)) {
                continue;
            }

            $excelRowNumber = $rowIndex + 1;
            $rowData = $this->normalizeImportRow($row, $mskHeaderMap, $excelRowNumber, $importErrors);
            if ($rowData === null) {
                continue;
            }

            $identityKey = discipleship_unified_identity_key($rowData['full_name'], $rowData['whatsapp']);
            $participantIdInput = $rowData['participant_id_input'];
            $rowIdentityKey = $participantIdInput !== '' ? ('id:'.$participantIdInput) : ('identity:'.$identityKey);
            if ($rowIdentityKey !== '' && isset($seenMskRowKeys[$rowIdentityKey])) {
                $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': data peserta duplikat di file import.';

                continue;
            }
            if ($rowIdentityKey !== '') {
                $seenMskRowKeys[$rowIdentityKey] = true;
            }

            $existingIndex = $this->existingParticipantIndex(
                $participantIdInput,
                $identityKey,
                $mskIndexById,
                $mskIndexByIdentity,
                $excelRowNumber,
                $importErrors,
            );
            if ($existingIndex === false) {
                continue;
            }

            $existing = $existingIndex !== null ? ($participants[$existingIndex] ?? null) : null;
            $participantId = $participantIdInput !== '' ? (int) $participantIdInput : (int) ($existing['id'] ?? 0);
            if ($participantIdInput !== '' && $existingIndex === null && isset($mskIndexById[$participantId])) {
                $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': participant_id sudah dipakai peserta lain.';

                continue;
            }

            $participantData = $this->participantData($participantId, is_array($existing) ? $existing : [], $rowData);
            if ($existingIndex === null) {
                $participants[] = $participantData;
                $mskInserted++;
            } else {
                $participants[$existingIndex] = $participantData;
                $mskUpdated++;
            }
        }

        return [$participants, $mskInserted, $mskUpdated, $importErrors];
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $mskHeaderMap
     * @param  array<int, string>  $importErrors
     * @return array<string, mixed>|null
     */
    private function normalizeImportRow(array $row, array $mskHeaderMap, int $excelRowNumber, array &$importErrors): ?array
    {
        $participantIdInput = import_row_value($row, $mskHeaderMap, ['participant_id', 'id']);
        $fullName = trim(import_row_value($row, $mskHeaderMap, ['full_name', 'nama']));
        $whatsapp = trim(import_row_value($row, $mskHeaderMap, ['whatsapp', 'phone', 'nomor_wa', 'nomor_whatsapp']));
        $genderRaw = import_row_value($row, $mskHeaderMap, ['gender', 'jenis_kelamin']);
        $birthDateRaw = import_row_value($row, $mskHeaderMap, ['birth_date', 'tanggal_lahir']);
        $birthPlace = trim(import_row_value($row, $mskHeaderMap, ['birth_place', 'tempat_lahir']));
        $address = trim(import_row_value($row, $mskHeaderMap, ['address', 'alamat']));
        $emailRaw = import_row_value($row, $mskHeaderMap, ['email']);
        $mskMonthRaw = import_row_value($row, $mskHeaderMap, ['msk_month', 'bulan_msk']);
        $sessionRaw = import_row_value($row, $mskHeaderMap, ['session_numbers', 'sessions', 'sesi']);
        $notes = trim(import_row_value($row, $mskHeaderMap, ['notes', 'keterangan']));

        if ($fullName === '') {
            $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': full_name wajib diisi.';

            return null;
        }

        if ($participantIdInput !== '' && (! ctype_digit($participantIdInput) || (int) $participantIdInput < 1)) {
            $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': participant_id harus berupa ID numerik dari hasil export.';

            return null;
        }

        $mskMonth = import_normalize_month_strict($mskMonthRaw);
        if ($mskMonth === '') {
            $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': msk_month wajib format YYYY-MM.';

            return null;
        }

        $sessionTokens = import_split_csv_tokens($sessionRaw);
        $sessionNumbers = import_parse_msk_session_numbers($sessionRaw);
        $invalidSession = count($sessionTokens) === 0 || count($sessionNumbers) === 0;
        foreach ($sessionTokens as $token) {
            $tokenInt = (int) $token;
            if (! preg_match('/^\d+$/', $token) || $tokenInt < 1 || $tokenInt > 12) {
                $invalidSession = true;
                break;
            }
        }
        if ($invalidSession) {
            $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': session_numbers harus angka 1-12 dipisah koma.';

            return null;
        }

        $gender = import_normalize_gender_value($genderRaw);
        if ($genderRaw !== '' && $gender === '') {
            $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': gender tidak valid.';

            return null;
        }

        $birthDate = '';
        if ($birthDateRaw !== '') {
            $birthDate = normalize_ymd_date($birthDateRaw);
            if ($birthDate === '') {
                $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': birth_date harus format YYYY-MM-DD.';

                return null;
            }
        }

        $email = strtolower(trim($emailRaw));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': email tidak valid.';

            return null;
        }

        return [
            'participant_id_input' => $participantIdInput,
            'full_name' => $fullName,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'birth_place' => $birthPlace,
            'address' => $address,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'msk_month' => $mskMonth,
            'session_numbers' => $sessionNumbers,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, int>  $mskIndexById
     * @param  array<string, array<int, int>>  $mskIndexByIdentity
     * @param  array<int, string>  $importErrors
     */
    private function existingParticipantIndex(
        string $participantIdInput,
        string $identityKey,
        array $mskIndexById,
        array $mskIndexByIdentity,
        int $excelRowNumber,
        array &$importErrors,
    ): int|null|false {
        if ($participantIdInput !== '' && isset($mskIndexById[$participantIdInput])) {
            return (int) $mskIndexById[$participantIdInput];
        }

        if ($identityKey === '' || ! isset($mskIndexByIdentity[$identityKey])) {
            return null;
        }

        $candidateIndexes = array_values(array_unique(array_map('intval', $mskIndexByIdentity[$identityKey])));
        if (count($candidateIndexes) > 1) {
            $importErrors[] = 'Kelas MSK baris '.$excelRowNumber.': nama/whatsapp ambigu. Gunakan participant_id dari hasil export atau rapikan data peserta MSK yang duplikat terlebih dahulu.';

            return false;
        }

        return count($candidateIndexes) === 1 ? $candidateIndexes[0] : null;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $rowData
     * @return array<string, mixed>
     */
    private function participantData(int $participantId, array $existing, array $rowData): array
    {
        $birthDate = (string) $rowData['birth_date'];

        return [
            'id' => $participantId,
            'member_id' => trim((string) ($existing['member_id'] ?? '')),
            'full_name' => (string) $rowData['full_name'],
            'gender' => (string) $rowData['gender'],
            'birth_date' => $birthDate,
            'birth_day_month' => $birthDate !== '' ? date('d-m', strtotime($birthDate)) : normalize_member_birth_day_month_value((string) ($existing['birth_day_month'] ?? '')),
            'birth_place' => (string) $rowData['birth_place'],
            'address' => (string) $rowData['address'],
            'email' => (string) $rowData['email'],
            'whatsapp' => (string) $rowData['whatsapp'],
            'photos' => extract_msk_participant_photos($existing),
            'msk_month' => (string) $rowData['msk_month'],
            'session_numbers' => $rowData['session_numbers'],
            'notes' => (string) $rowData['notes'],
            'completed_at' => trim((string) ($existing['completed_at'] ?? '')),
            'journey_bridge_status' => normalize_journey_bridge_status((string) ($existing['journey_bridge_status'] ?? 'belum')),
            'status' => normalize_msk_participant_status((string) ($existing['status'] ?? 'active')),
            'created_at' => (string) ($existing['created_at'] ?? now_iso()),
            'updated_at' => now_iso(),
        ];
    }
}
