<?php

namespace App\Services\MskParticipants;

use App\Http\Requests\MskParticipants\ExportMskParticipantsRequest;
use App\Models\Person;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MskParticipantExportService
{
    public function __construct(
        private readonly CurrentDiscipleshipScope $scope,
    ) {}

    public function export(ExportMskParticipantsRequest $request): BinaryFileResponse|RedirectResponse
    {
        $batchMonth = $request->batchMonth();
        $search = $request->search();
        $batchMonthParam = $batchMonth !== '' ? ['batch_month' => $batchMonth] : [];
        if ($search !== '') {
            $batchMonthParam['q'] = $search;
        }

        $branchCodes = [];
        foreach ($this->scope->branchIds() as $branchId) {
            $branchCode = $this->scope->optionsById()[$branchId]['slug'] ?? '';
            if ($branchCode !== '') {
                $branchCodes[] = $branchCode;
            }
        }
        $query = Person::query()
            ->select(Person::VIEW_COLUMNS)
            ->whereIn('branch_id', branch_ids_from_slugs($branchCodes))
            ->when($batchMonth !== '' && $batchMonth !== 'all', static fn ($builder) => $builder->where('batch_month', $batchMonth))
            ->when($search !== '', static function ($builder) use ($search): void {
                $builder->where(static function ($filter) use ($search): void {
                    $filter->whereRaw('LOWER(full_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(whatsapp) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$search.'%']);
                });
            });
        $participantsToExport = (static function () use ($query): \Generator {
            foreach ($query->lazyById(500) as $participant) {
                if ($participant instanceof Person) {
                    yield $participant->toViewArray();
                }
            }
        })();

        $exportError = '';
        $xlsxPath = create_msk_import_export_xlsx($participantsToExport, $exportError);
        if ($xlsxPath === null) {
            $error = $exportError === 'zip_unavailable' ? 'export_zip_unavailable' : ($exportError === 'template_missing' ? 'export_template_missing' : 'export_failed');

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
