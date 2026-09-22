<?php

namespace App\Services\DgMeetingReports;

use App\Services\DiscipleshipPeople\DiscipleshipPeopleXlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DgMeetingReportExportService
{
    public function __construct(
        private readonly DgMeetingReportRecapPageData $recapData,
        private readonly DiscipleshipPeopleXlsxWriter $writer,
    ) {}

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        $data = $this->recapData->forCurrentContext($request);
        $headers = [
            'No.',
            'Tanggal Pertemuan',
            'Cabang',
            'Progress',
            'Pemimpin',
            'Kelompok',
            'Materi / Topik',
            'Anggota Tidak Hadir',
            'Alasan Ketidakhadiran',
            'Pembagi Renungan',
            'Minimal Membagikan Renungan',
            'Persiapan Materi',
            'Mendoakan Anggota',
            'Membagikan Renungan',
            'Kontak Relasional',
            'Skor Kualitas',
            'Keterbukaan Sharing',
            'Catatan Tambahan',
            'Foto Pertemuan',
        ];
        $branch = $this->selectedBranch();
        $branchLabel = $branch === 'all' ? 'Semua Cabang' : user_branch_label($branch);
        $subtitle = 'Cabang: '.$branchLabel.' | Diekspor: '.now()->format('d/m/Y H:i');
        $errorCode = '';
        $xlsxPath = $this->writer->create(
            $headers,
            $this->rows($data['dgMeetingReports'] ?? []),
            $subtitle,
            $errorCode,
            'Jurnal Temu DG',
            'Jurnal Temu DG',
        );
        if ($xlsxPath === null) {
            $error = $errorCode === 'zip_unavailable' ? 'export_zip_unavailable' : 'export_failed';

            return redirect()->route('discipleship.reports-recap', $this->redirectParams($request) + ['error' => $error]);
        }

        $branchSlug = sanitize_file_name_component($branch, 'cabang');
        $downloadName = 'jurnal-temu-dg-'.$branchSlug.'-'.now()->format('Y-m-d').'.xlsx';
        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?: 'jurnal-temu-dg.xlsx';

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

    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @return \Generator<int, array<int, string|int>>
     */
    private function rows(array $reports): \Generator
    {
        foreach ($reports as $index => $report) {
            $quality = [
                parse_bool_value($report['quality_prepare'] ?? false),
                parse_bool_value($report['quality_pray'] ?? false),
                parse_bool_value($report['quality_share_meditation'] ?? false),
                parse_bool_value($report['quality_relational'] ?? false),
            ];
            $progress = normalize_dg_progress_value((string) ($report['group_progress'] ?? '')) ?: 'DG 1';
            $minimum = (int) ($report['meditation_min_times'] ?? 0);
            if ($minimum < 1) {
                $minimum = dg_progress_min_share_times($progress);
            }

            yield [
                $index + 1,
                $this->formattedDate((string) ($report['meeting_date'] ?? '')),
                $this->value($report['branch_label'] ?? null, 'Tanpa cabang'),
                $progress,
                $this->value($report['leader_name'] ?? null),
                $this->value($report['group_name'] ?? null, 'Kelompok'),
                $this->value($report['material_topic'] ?? null),
                $this->listLabel($report['absent_member_names'] ?? []),
                $this->value($report['absence_reason'] ?? null),
                $this->listLabel($report['meditation_sharer_names'] ?? []),
                $minimum,
                $this->yesNo($quality[0]),
                $this->yesNo($quality[1]),
                $this->yesNo($quality[2]),
                $this->yesNo($quality[3]),
                array_sum(array_map('intval', $quality)).' / 4',
                $this->sharingLabel($report['sharing_openness'] ?? null),
                $this->value($report['additional_notes'] ?? null),
                $this->photoNames($report['meeting_photos'] ?? []),
            ];
        }
    }

    private function selectedBranch(): string
    {
        return is_effective_central_discipleship_readonly()
            ? normalize_central_recap_branch(central_recap_selected_branch())
            : normalize_user_branch(current_user_branch());
    }

    private function formattedDate(string $value): string
    {
        $date = normalize_ymd_date($value);

        return $date !== '' ? date('d-m-Y', strtotime($date)) : '-';
    }

    private function value(mixed $value, string $fallback = '-'): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    private function listLabel(mixed $values): string
    {
        if (! is_array($values)) {
            return '-';
        }

        $names = array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $values));

        return $names !== [] ? implode(', ', $names) : '-';
    }

    private function sharingLabel(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        $score = (int) $value;

        return $score >= 1 && $score <= 10 ? $score.' / 10' : '-';
    }

    private function photoNames(mixed $photos): string
    {
        if (! is_array($photos)) {
            return '-';
        }

        $names = [];
        foreach ($photos as $photo) {
            if (! is_array($photo)) {
                continue;
            }

            $name = trim((string) ($photo['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names !== [] ? implode(', ', $names) : '-';
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Ya' : 'Tidak';
    }

    /** @return array<string, string> */
    private function redirectParams(Request $request): array
    {
        $params = [];
        foreach (['branch_id', 'rekap_cabang'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
