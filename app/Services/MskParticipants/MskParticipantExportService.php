<?php

namespace App\Services\MskParticipants;

use App\Http\Requests\MskParticipants\ExportMskParticipantsRequest;
use App\Services\Activity\ActivityRecorder;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MskParticipantExportService
{
    public function __construct(
        private readonly MskParticipantTableData $tableData,
        private readonly CurrentDiscipleshipScope $scope,
        private readonly ActivityRecorder $activity,
    ) {}

    public function export(ExportMskParticipantsRequest $request): BinaryFileResponse|RedirectResponse
    {
        $batchMonth = $request->batchMonth();
        $batchMonthParam = $batchMonth !== '' ? ['batch_month' => $batchMonth] : [];

        $branchCodes = [];
        foreach ($this->scope->branchIds() as $branchId) {
            $branchCode = $this->scope->optionsById()[$branchId]['slug'] ?? '';
            if ($branchCode !== '') {
                $branchCodes[] = $branchCode;
            }
        }
        $participantsToExport = $this->tableData->participantsForBranches($branchCodes);
        usort($participantsToExport, static function ($a, $b): int {
            return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
        });

        if ($batchMonth !== '' && $batchMonth !== 'all') {
            $participantsToExport = array_values(array_filter($participantsToExport, static function ($participant) use ($batchMonth): bool {
                return import_normalize_month_strict((string) ($participant['msk_month'] ?? '')) === $batchMonth;
            }));
        }

        $exportError = '';
        $xlsxPath = create_msk_import_export_xlsx($participantsToExport, $exportError);
        if ($xlsxPath === null) {
            $error = $exportError === 'zip_unavailable' ? 'export_zip_unavailable' : ($exportError === 'template_missing' ? 'export_template_missing' : 'export_failed');
            $this->activity->record(
                'export',
                'msk.export.failed',
                'msk_export',
                $this->scope->selectedSlug(),
                null,
                'Ekspor peserta MSK gagal.',
                metadata: ['error' => $error, 'batch_month' => $batchMonth],
            );

            return redirect()->route('discipleship.msk-classes', $batchMonthParam + ['error' => $error]);
        }

        $branchLabel = sanitize_file_name_component($this->scope->selectedSlug(), 'cabang');
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

        $this->activity->record(
            'export',
            'msk.export.completed',
            'msk_export',
            $this->scope->selectedSlug(),
            $downloadName,
            'Data peserta MSK diekspor.',
            metadata: [
                'batch_month' => $batchMonth,
                'participant_count' => count($participantsToExport),
                'name' => $downloadName,
                'size_bytes' => is_file($xlsxPath) ? (int) filesize($xlsxPath) : 0,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'sha256' => is_file($xlsxPath) ? hash_file('sha256', $xlsxPath) : null,
            ],
        );

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
