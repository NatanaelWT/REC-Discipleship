<?php

namespace App\Services\DiscipleshipPeople;

use App\Http\Requests\DiscipleshipPeople\ExportDiscipleshipPeopleRequest;
use App\Services\Activity\ActivityRecorder;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DiscipleshipPeopleExportService
{
    public function __construct(
        private readonly DiscipleshipPeopleListData $listData,
        private readonly DiscipleshipPeopleXlsxWriter $writer,
        private readonly CurrentDiscipleshipScope $scope,
        private readonly ActivityRecorder $activity,
    ) {}

    public function export(ExportDiscipleshipPeopleRequest $request): BinaryFileResponse|RedirectResponse
    {
        $data = $this->listData->forCurrentContext($request);
        $search = trim((string) $request->query('q', ''));
        $people = collect($data['people'] ?? []);
        if ($search !== '') {
            $needle = $this->lower($search);
            $people = $people->filter(fn (array $row): bool => str_contains(
                $this->lower((string) ($row['name'] ?? '')),
                $needle,
            ))->values();
        }

        $headers = ['No.', 'Nama', 'Cabang', 'Relasi Pembina', 'Peran', 'DG 1', 'DG 2', 'DG 3', 'Ringkasan Progress'];
        $rows = $people->values()->map(function (array $row, int $index): array {
            $steps = collect($row['progress_steps'] ?? [])->keyBy('label');

            return [
                $index + 1,
                (string) ($row['export_name'] ?? $row['name'] ?? '-'),
                (string) ($row['branch_label'] ?? 'Tanpa cabang'),
                (string) ($row['parent_summary'] ?? 'Belum terhubung ke pembina'),
                (string) ($row['role_label'] ?? 'Anggota'),
                (string) ($steps->get('DG 1')['state_label'] ?? 'Belum'),
                (string) ($steps->get('DG 2')['state_label'] ?? 'Belum'),
                (string) ($steps->get('DG 3')['state_label'] ?? 'Belum'),
                (string) ($row['progress_summary'] ?? 'Belum memulai DG'),
            ];
        })->all();

        $progress = (string) ($data['peopleProgressFilter'] ?? 'all');
        $progressLabel = $this->progressLabel($progress);
        $subtitle = 'Cabang: '.$this->scope->selectedLabel()
            .' | Filter: '.$progressLabel
            .($search !== '' ? ' | Pencarian: '.$search : '')
            .' | Diekspor: '.now()->format('d/m/Y H:i');

        $errorCode = '';
        $xlsxPath = $this->writer->create($headers, $rows, $subtitle, $errorCode);
        if ($xlsxPath === null) {
            $error = $errorCode === 'zip_unavailable' ? 'export_zip_unavailable' : 'export_failed';
            $this->activity->record(
                'export',
                'discipleship_people.export.failed',
                'discipleship_people_export',
                $this->scope->selectedSlug(),
                null,
                'Ekspor Anggota DG gagal.',
                metadata: ['error' => $error, 'progress' => $progress, 'search' => $search],
            );

            return redirect()->route('discipleship.people-list', $this->redirectParams($request) + ['error' => $error]);
        }

        $branchLabel = sanitize_file_name_component($this->scope->selectedSlug(), 'cabang');
        $filterLabel = sanitize_file_name_component($progressLabel, 'semua-peserta');
        $downloadName = 'anggota-dg-'.$branchLabel.'-'.$filterLabel.'-'.now()->format('Y-m-d').'.xlsx';
        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?: 'anggota-dg.xlsx';

        $this->activity->record(
            'export',
            'discipleship_people.export.completed',
            'discipleship_people_export',
            $this->scope->selectedSlug(),
            $downloadName,
            'Data Anggota DG diekspor.',
            metadata: [
                'progress' => $progress,
                'search' => $search,
                'people_count' => count($rows),
                'name' => $downloadName,
                'size_bytes' => (int) filesize($xlsxPath),
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'sha256' => hash_file('sha256', $xlsxPath),
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

    private function progressLabel(string $progress): string
    {
        return match ($progress) {
            'active_dg1' => 'Sedang DG 1',
            'complete_dg1' => 'Selesai DG 1',
            'active_dg2' => 'Sedang DG 2',
            'complete_dg2' => 'Selesai DG 2',
            'active_dg3' => 'Sedang DG 3',
            'complete_dg3' => 'Selesai DG 3',
            default => 'Semua Peserta',
        };
    }

    /** @return array<string, string> */
    private function redirectParams(ExportDiscipleshipPeopleRequest $request): array
    {
        $params = [];
        foreach (['branch_id', 'progress', 'q'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
