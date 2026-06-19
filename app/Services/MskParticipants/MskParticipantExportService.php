<?php

namespace App\Services\MskParticipants;

use App\Http\Requests\MskParticipants\ExportMskParticipantsRequest;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MskParticipantExportService
{
    public function __construct(
        private readonly MskParticipantTableData $tableData,
    ) {}

    public function export(ExportMskParticipantsRequest $request): BinaryFileResponse|RedirectResponse
    {
        $batchMonth = $request->batchMonth();
        $batchMonthParam = $batchMonth !== '' ? ['batch_month' => $batchMonth] : [];

        $participantsToExport = $this->tableData->participantsForBranches([normalize_public_branch_code(current_user_branch())]);
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
        $downloadName = 'kelas-msk-'.$branchLabel.'-'.$filterLabel.'.xlsx';
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
                'Content-Disposition' => 'attachment; filename="'.$asciiDownloadName.'"; filename*=UTF-8\'\''.rawurlencode($downloadName),
            ])
            ->deleteFileAfterSend(true);
    }
}
