<?php

namespace App\Services\SpiritualJourney;

use App\Http\Requests\SpiritualJourney\ExportSpiritualJourneyRequest;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\DiscipleshipPeople\DiscipleshipPeopleXlsxWriter;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpiritualJourneyExportService
{
    public function __construct(
        private readonly SpiritualJourneyPageData $pageData,
        private readonly DiscipleshipPeopleXlsxWriter $writer,
        private readonly CurrentDiscipleshipScope $scope,
    ) {}

    public function export(ExportSpiritualJourneyRequest $request): BinaryFileResponse|RedirectResponse
    {
        $context = $this->pageData->exportContext($request);
        $search = (string) $context['search'];
        $filter = (string) $context['journey_filter'];
        $headers = ['No.', 'Nama', 'Cabang', 'MSK', 'DG 1', 'RG / KGAP', 'DG 2', 'DG 3', 'Pemimpin DG', 'Ringkasan Progress'];
        $rows = (function () use ($request): \Generator {
            $index = 0;
            foreach ($this->pageData->exportRowsForCurrentContext($request) as $row) {
                $steps = collect($row['progress_steps'] ?? [])->keyBy('label');
                yield [
                    ++$index,
                    (string) ($row['name'] ?? '-'),
                    (string) ($row['branch_label'] ?? 'Tanpa cabang'),
                    (string) ($row['msk_progress'] ?? '-'),
                    (string) ($steps->get('DG 1')['state_label'] ?? 'Belum'),
                    $this->bridgeLabel((string) ($row['journey_bridge_status'] ?? 'belum')),
                    (string) ($steps->get('DG 2')['state_label'] ?? 'Belum'),
                    (string) ($steps->get('DG 3')['state_label'] ?? 'Belum'),
                    ! empty($row['has_led_dg']) ? 'Pernah' : 'Belum pernah',
                    (string) ($row['progress_summary'] ?? 'Belum memulai DG'),
                ];
            }
        })();

        $filterLabel = $this->filterLabel($filter);
        $subtitle = 'Cabang: '.$this->scope->selectedLabel()
            .' | Filter: '.$filterLabel
            .($search !== '' ? ' | Pencarian: '.$search : '')
            .' | Diekspor: '.now()->format('d/m/Y H:i');
        $errorCode = '';
        $xlsxPath = $this->writer->create(
            $headers,
            $rows,
            $subtitle,
            $errorCode,
            'Spiritual Journey',
            'Spiritual Journey',
        );
        if ($xlsxPath === null) {
            $error = $errorCode === 'zip_unavailable' ? 'export_zip_unavailable' : 'export_failed';

            return redirect()->route('discipleship.spiritual-journey', $this->redirectParams($request) + ['error' => $error]);
        }

        $branch = sanitize_file_name_component($this->scope->selectedSlug(), 'cabang');
        $filterSlug = sanitize_file_name_component($filterLabel, 'semua-peserta');
        $downloadName = 'spiritual-journey-'.$branch.'-'.$filterSlug.'-'.now()->format('Y-m-d').'.xlsx';
        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?: 'spiritual-journey.xlsx';

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

    private function bridgeLabel(string $status): string
    {
        return match (normalize_journey_bridge_status($status)) {
            'sudah_rg' => 'Sudah RG',
            'sudah_kgap' => 'Sudah KGAP',
            'ikut_keduanya' => 'Ikut Keduanya',
            default => 'Belum',
        };
    }

    private function filterLabel(string $filter): string
    {
        return $filter === 'dg_without_kgap'
            ? 'Minimal DG 1, Belum Kamp GAP'
            : 'Semua Peserta';
    }

    /** @return array<string, string> */
    private function redirectParams(ExportSpiritualJourneyRequest $request): array
    {
        $params = [];
        foreach (['branch_id', 'journey_filter', 'q'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
