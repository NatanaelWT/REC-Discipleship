<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\MskParticipants\ExportMskParticipantsRequest;
use App\Http\Requests\MskParticipants\ImportMskParticipantsRequest;
use App\Http\Requests\MskParticipants\MskParticipantWriteRequest;
use App\Http\Requests\MskParticipants\StoreMskParticipantRequest;
use App\Http\Requests\MskParticipants\UpdateMskParticipantRequest;
use App\Http\Requests\MskParticipants\UpdateMskParticipantSessionsRequest;
use App\Http\Requests\MskParticipants\DeactivateMskParticipantRequest;
use App\Http\Requests\MskParticipants\ReactivateMskParticipantRequest;
use App\Models\MskParticipant;
use App\Services\Routing\CompatibilityRouteMap;
use App\Services\MskParticipants\MskParticipantPageData;
use App\Services\MskParticipants\MskParticipantTableData;
use App\Services\MskParticipants\MskParticipantWriter;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MskParticipantController extends Controller
{
    public function index(Request $request, MskParticipantPageData $pageData): RedirectResponse|Response
    {
        $pageQuery = trim((string) $request->query('page', ''));
        if ($pageQuery !== '' && CompatibilityRouteMap::hasPage($pageQuery)) {
            return redirect()->away($request->getSchemeAndHttpHost() . CompatibilityRouteMap::pageUrl($pageQuery, $request->query()));
        }

        RuntimeBootstrap::boot($request);

        if (trim((string) $request->input('action', '')) === 'logout') {
            destroy_current_session();

            return redirect('/index.php');
        }

        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }

        if (! branch_can_access_page(current_user_branch(), 'msk_classes')) {
            return redirect(CompatibilityRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied']));
        }

        return response(view('discipleship.msk-participants.index', $pageData->forCurrentContext($request))->render());
    }

    public function store(
        StoreMskParticipantRequest $request,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        return $this->saveParticipantFromRequest($request, $writer);
    }

    public function update(
        UpdateMskParticipantRequest $request,
        MskParticipant $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);
        $request->merge(['id' => $participant->public_id]);

        return $this->saveParticipantFromRequest($request, $writer);
    }

    public function updateSessions(
        UpdateMskParticipantSessionsRequest $request,
        MskParticipant $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        $result = $writer->updateSessions($participant, $request->payload()['session_numbers']);
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', ['error' => 'invalid_msk_participant']);
        }

        $redirectParams = ['msk_session_saved' => 1];
        if ($result['auto_converted']) {
            $redirectParams['converted'] = 1;
        }

        return redirect()->route('discipleship.msk-classes', $redirectParams);
    }

    public function deactivate(
        DeactivateMskParticipantRequest $request,
        MskParticipant $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);
        $batchMonthParam = $this->batchMonthFilterParam($request);

        $result = $writer->setStatus($participant, 'inactive');
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', ['error' => 'invalid_msk_participant'] + $batchMonthParam);
        }

        return redirect()->route('discipleship.msk-classes', ['deleted' => 1] + $batchMonthParam);
    }

    public function reactivate(
        ReactivateMskParticipantRequest $request,
        MskParticipant $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);
        $batchMonthParam = $this->batchMonthFilterParam($request);

        $result = $writer->setStatus($participant, 'active');
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', ['error' => 'invalid_msk_participant'] + $batchMonthParam);
        }

        return redirect()->route('discipleship.msk-classes', ['reactivated' => 1] + $batchMonthParam);
    }

    public function import(ImportMskParticipantsRequest $request, MskParticipantTableData $tableData): RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        if (! is_logged_in() || ! branch_can_access_page(current_user_branch(), 'msk_classes') || ! branch_can_use_action(current_user_branch(), 'import_pemuridan_excel')) {
            return redirect()->route('auth.login');
        }

        $branchCode = normalize_public_branch_code(current_user_branch());
        $participants = $tableData->participantsForBranches([$branchCode]);

        $file = $_FILES['import_pemuridan_excel'] ?? null;
        if (! is_array($file) || ! isset($file['tmp_name'])) {
            return redirect()->route('discipleship.msk-classes', ['error' => 'import_missing_file']);
        }
        $tmpPath = trim((string) ($file['tmp_name'] ?? ''));
        if ($tmpPath === '' || ! is_uploaded_file($tmpPath)) {
            return redirect()->route('discipleship.msk-classes', ['error' => 'import_upload_failed']);
        }
        $originalName = trim((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            return redirect()->route('discipleship.msk-classes', ['error' => 'import_invalid_file_type']);
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > (10 * 1024 * 1024)) {
            return redirect()->route('discipleship.msk-classes', ['error' => 'import_file_too_large']);
        }

        $xlsxError = '';
        $sheets = import_read_xlsx_sheets($tmpPath, $xlsxError);
        if ($xlsxError !== '') {
            return redirect()->route('discipleship.msk-classes', ['error' => $xlsxError === 'zip_unavailable' ? 'import_zip_unavailable' : 'import_invalid_excel']);
        }

        $sheetMap = [];
        foreach ($sheets as $sheetName => $rows) {
            $sheetMap[import_sheet_name_key((string) $sheetName)] = is_array($rows) ? $rows : [];
        }
        $mskRows = $sheetMap['kelas msk'] ?? null;
        if (! is_array($mskRows)) {
            return redirect()->route('discipleship.msk-classes', ['error' => 'import_missing_sheet']);
        }
        if (count($mskRows) === 0) {
            return redirect()->route('discipleship.msk-classes', ['error' => 'import_empty_sheet']);
        }

        $importErrors = [];
        $mskInserted = 0;
        $mskUpdated = 0;

        $mskHeaderMap = import_build_header_map($mskRows[0] ?? []);
        $requiredMskHeaders = ['full_name', 'msk_month', 'session_numbers'];
        foreach ($requiredMskHeaders as $requiredHeader) {
            if (! isset($mskHeaderMap[$requiredHeader])) {
                $importErrors[] = 'Sheet Kelas MSK: kolom wajib "' . $requiredHeader . '" tidak ditemukan.';
            }
        }

        if ($importErrors === []) {
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
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': full_name wajib diisi.';
                    continue;
                }
                $mskMonth = import_normalize_month_strict($mskMonthRaw);
                if ($mskMonth === '') {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': msk_month wajib format YYYY-MM.';
                    continue;
                }
                $sessionTokens = import_split_csv_tokens($sessionRaw);
                $sessionNumbers = import_parse_msk_session_numbers($sessionRaw);
                $invalidSession = count($sessionTokens) === 0 || count($sessionNumbers) === 0;
                if (! $invalidSession) {
                    foreach ($sessionTokens as $token) {
                        if (! preg_match('/^\d+$/', $token)) {
                            $invalidSession = true;
                            break;
                        }
                        $tokenInt = (int) $token;
                        if ($tokenInt < 1 || $tokenInt > 12) {
                            $invalidSession = true;
                            break;
                        }
                    }
                }
                if ($invalidSession) {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': session_numbers harus angka 1-12 dipisah koma.';
                    continue;
                }
                $gender = import_normalize_gender_value($genderRaw);
                if ($genderRaw !== '' && $gender === '') {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': gender tidak valid.';
                    continue;
                }
                $birthDate = '';
                if ($birthDateRaw !== '') {
                    $birthDate = normalize_ymd_date($birthDateRaw);
                    if ($birthDate === '') {
                        $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': birth_date harus format YYYY-MM-DD.';
                        continue;
                    }
                }
                $email = strtolower(trim($emailRaw));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': email tidak valid.';
                    continue;
                }

                $identityKey = discipleship_unified_identity_key($fullName, $whatsapp);
                $rowIdentityKey = $participantIdInput !== '' ? ('id:' . $participantIdInput) : ('identity:' . $identityKey);
                if ($rowIdentityKey !== '' && isset($seenMskRowKeys[$rowIdentityKey])) {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': data peserta duplikat di file import.';
                    continue;
                }
                if ($rowIdentityKey !== '') {
                    $seenMskRowKeys[$rowIdentityKey] = true;
                }

                $existingIndex = null;
                if ($participantIdInput !== '' && isset($mskIndexById[$participantIdInput])) {
                    $existingIndex = (int) $mskIndexById[$participantIdInput];
                } elseif ($identityKey !== '' && isset($mskIndexByIdentity[$identityKey])) {
                    $candidateIndexes = array_values(array_unique(array_map('intval', $mskIndexByIdentity[$identityKey])));
                    if (count($candidateIndexes) > 1) {
                        $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': nama/whatsapp ambigu. Gunakan participant_id dari hasil export atau rapikan data peserta MSK yang duplikat terlebih dahulu.';
                        continue;
                    }
                    if (count($candidateIndexes) === 1) {
                        $existingIndex = $candidateIndexes[0];
                    }
                }

                $existing = $existingIndex !== null ? ($participants[$existingIndex] ?? null) : null;
                $participantId = $participantIdInput !== '' ? $participantIdInput : trim((string) ($existing['id'] ?? ''));
                if ($participantId === '') {
                    $participantId = generate_id('msk');
                }
                if ($participantIdInput !== '' && $existingIndex === null && isset($mskIndexById[$participantId])) {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': participant_id sudah dipakai peserta lain.';
                    continue;
                }

                $participantData = [
                    'id' => $participantId,
                    'member_id' => trim((string) ($existing['member_id'] ?? '')),
                    'full_name' => $fullName,
                    'gender' => $gender,
                    'birth_date' => $birthDate,
                    'birth_day_month' => $birthDate !== '' ? date('d-m', strtotime($birthDate)) : normalize_member_birth_day_month_value((string) ($existing['birth_day_month'] ?? '')),
                    'birth_place' => $birthPlace,
                    'address' => $address,
                    'email' => $email,
                    'whatsapp' => $whatsapp,
                    'photos' => extract_msk_participant_photos(is_array($existing) ? $existing : []),
                    'msk_month' => $mskMonth,
                    'session_numbers' => $sessionNumbers,
                    'notes' => $notes,
                    'completed_at' => trim((string) ($existing['completed_at'] ?? '')),
                    'journey_bridge_status' => normalize_journey_bridge_status((string) ($existing['journey_bridge_status'] ?? 'belum')),
                    'status' => normalize_msk_participant_status((string) ($existing['status'] ?? 'active')),
                    'created_at' => (string) ($existing['created_at'] ?? now_iso()),
                    'updated_at' => now_iso(),
                ];

                if ($existingIndex === null) {
                    $participants[] = $participantData;
                    $mskInserted++;
                } else {
                    $participants[$existingIndex] = $participantData;
                    $mskUpdated++;
                }
            }
        }

        if ($importErrors === []) {
            if ($mskInserted > 0 || $mskUpdated > 0) {
                $tableData->replaceBranchRows($branchCode, $participants, true);
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

        return redirect()->route('discipleship.msk-classes', $redirectParams);
    }

    public function export(ExportMskParticipantsRequest $request, MskParticipantTableData $tableData): BinaryFileResponse|RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        $batchMonth = $request->batchMonth();
        $batchMonthParam = $batchMonth !== '' ? ['batch_month' => $batchMonth] : [];

        $participantsToExport = $tableData->participantsForBranches([normalize_public_branch_code(current_user_branch())]);
        usort($participantsToExport, static function ($a, $b): int {
            return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
        });
        if ($batchMonth !== '' && $batchMonth !== 'all') {
            $participantsToExport = array_values(array_filter($participantsToExport, static function ($participant) use ($batchMonth): bool {
                return normalize_month_value((string) ($participant['msk_month'] ?? date('Y-m'))) === $batchMonth;
            }));
        }

        $exportError = '';
        $xlsxPath = create_msk_import_export_xlsx($participantsToExport, $exportError);
        if ($xlsxPath === null) {
            $error = $exportError === 'zip_unavailable' ? 'export_zip_unavailable' : ($exportError === 'template_missing' ? 'export_template_missing' : 'export_failed');

            return redirect()->route('discipleship.msk-classes', $batchMonthParam + ['error' => $error]);
        }

        $branchLabel = sanitize_file_name_component((string) current_user_branch(), 'cabang');
        $filterLabel = $batchMonth === 'all' ? 'semua-batch' : ($batchMonth !== '' ? $batchMonth : 'semua-data');
        $downloadName = 'kelas-msk-' . $branchLabel . '-' . $filterLabel . '.xlsx';
        $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', $downloadName) ?? 'kelas-msk.xlsx';
        if ($downloadName === '') {
            $downloadName = 'kelas-msk.xlsx';
        }
        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'kelas-msk.xlsx';
        if ($asciiDownloadName === '') {
            $asciiDownloadName = 'kelas-msk.xlsx';
        }
        return response()
            ->download($xlsxPath, $asciiDownloadName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Content-Disposition' => 'attachment; filename="' . $asciiDownloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName),
            ])
            ->deleteFileAfterSend(true);
    }

    private function saveParticipantFromRequest(MskParticipantWriteRequest $request, MskParticipantWriter $writer): RedirectResponse
    {
        $payload = $request->payload();
        $redirectParams = $this->baseRedirectParams($payload['public_id'] ?? '', $payload['batch_month'] ?? '');

        if ($payload['full_name'] === '') {
            return redirect()->route('discipleship.msk-classes', $redirectParams + ['error' => 'missing_msk_name']);
        }

        if (($payload['birth_date_input'] ?? '') !== '' && ($payload['birth_date'] ?? '') === '') {
            return redirect()->route('discipleship.msk-classes', $redirectParams + ['error' => 'invalid_msk_birth_date']);
        }

        if (($payload['email'] ?? '') !== '' && filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->route('discipleship.msk-classes', $redirectParams + ['error' => 'invalid_msk_email']);
        }

        $result = $writer->save($request);
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', $redirectParams + ['error' => $result['error']]);
        }

        $redirectParams['saved'] = 1;
        if ($result['auto_converted']) {
            $redirectParams['converted'] = 1;
        }
        $redirectParams['batch_month'] = $result['batch_month'] !== '' ? $result['batch_month'] : (string) ($payload['batch_month'] ?? '');

        return redirect()->route('discipleship.msk-classes', $redirectParams);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function baseRedirectParams(string $id, string $batchMonth): array
    {
        $params = [];
        if (trim($id) !== '') {
            $params['edit'] = trim($id);
        }
        if ($batchMonth !== '') {
            $params['batch_month'] = $batchMonth;
        }

        return $params;
    }

    /**
     * @return array<string, string>
     */
    private function batchMonthFilterParam(Request $request): array
    {
        $batchMonthInput = trim((string) $request->input('batch_month', ''));
        if ($batchMonthInput === '') {
            return [];
        }

        if (strtolower($batchMonthInput) === 'all') {
            return ['batch_month' => 'all'];
        }

        $batchMonth = normalize_month_value($batchMonthInput);

        return $batchMonth !== '' ? ['batch_month' => $batchMonth] : [];
    }

}
