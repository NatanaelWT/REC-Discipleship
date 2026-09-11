<?php

namespace App\Services\MemberFeedbackJournals;

use App\Services\DiscipleshipPeople\DiscipleshipPeopleXlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MemberFeedbackExportService
{
    public function __construct(
        private readonly MemberFeedbackRecapPageData $recapData,
        private readonly MemberFeedbackQuestionCatalog $questionCatalog,
        private readonly DiscipleshipPeopleXlsxWriter $writer,
    ) {}

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        $data = $this->recapData->forCurrentContext($request);
        $ratingQuestions = $this->ratingQuestions();
        $noteQuestions = $this->noteQuestions();
        $headers = [
            'No.', 'Tanggal', 'Cabang', 'Progress', 'Pemimpin', 'Pengisi', 'Sesi', 'Skor',
            ...array_column($ratingQuestions, 'label'),
            ...array_column($noteQuestions, 'label'),
        ];
        $rows = $this->rows($data['detail_rows'] ?? [], $ratingQuestions, $noteQuestions);
        $branch = $this->selectedBranch();
        $branchLabel = $branch === 'all' ? 'Semua Cabang' : user_branch_label($branch);
        $subtitle = 'Cabang: '.$branchLabel.' | Diekspor: '.now()->format('d/m/Y H:i');

        $errorCode = '';
        $xlsxPath = $this->writer->create(
            $headers,
            $rows,
            $subtitle,
            $errorCode,
            'Jurnal Umpan Balik Anggota',
            'Jurnal Umpan Balik',
        );
        if ($xlsxPath === null) {
            $error = $errorCode === 'zip_unavailable' ? 'export_zip_unavailable' : 'export_failed';

            return redirect()->route('discipleship.member-feedback-recap', $this->redirectParams($request) + ['error' => $error]);
        }

        $branchSlug = sanitize_file_name_component($branch, 'cabang');
        $downloadName = 'jurnal-umpan-balik-'.$branchSlug.'-'.now()->format('Y-m-d').'.xlsx';
        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?: 'jurnal-umpan-balik.xlsx';

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
     * @param  array<int, array<string, mixed>>  $detailRows
     * @param  array<int, array{key:string,label:string}>  $ratingQuestions
     * @param  array<int, array{key:string,label:string}>  $noteQuestions
     * @return \Generator<int, array<int, string|int|float|null>>
     */
    private function rows(array $detailRows, array $ratingQuestions, array $noteQuestions): \Generator
    {
        foreach ($detailRows as $index => $detail) {
            $ratings = collect($detail['rating_rows'] ?? [])->keyBy('question_key');
            $notes = collect($detail['note_rows'] ?? [])->keyBy('note_key');
            $row = [
                $index + 1,
                $this->formattedDate((string) ($detail['submitted_at'] ?? '')),
                (string) ($detail['branch_label'] ?? 'Tanpa cabang'),
                (string) ($detail['group_progress'] ?? '-'),
                (string) ($detail['leader_name'] ?? '-'),
                (string) ($detail['respondent_name'] ?? '-'),
                (string) ($detail['session_label'] ?? '-'),
                isset($detail['score']) ? number_format((float) $detail['score'], 1, ',', '').'/10' : '-',
            ];
            foreach ($ratingQuestions as $question) {
                $rating = $ratings->get($question['key']);
                $row[] = is_array($rating)
                    ? (string) ($rating['score'] ?? '').' / '.(string) ($rating['scale'] ?? '')
                    : '';
            }
            foreach ($noteQuestions as $question) {
                $note = $notes->get($question['key']);
                $row[] = is_array($note) ? (string) ($note['content'] ?? '') : '';
            }

            yield $row;
        }
    }

    /** @return array<int, array{key:string,label:string}> */
    private function ratingQuestions(): array
    {
        $questions = [];
        foreach ($this->questionCatalog->sections() as $section) {
            foreach (($section['ratings'] ?? []) as $rating) {
                $questions[] = [
                    'key' => (string) ($rating['key'] ?? ''),
                    'label' => (string) ($rating['label'] ?? $rating['key'] ?? 'Skor'),
                ];
            }
        }

        return $questions;
    }

    /** @return array<int, array{key:string,label:string}> */
    private function noteQuestions(): array
    {
        $questions = [];
        foreach ($this->questionCatalog->sections() as $section) {
            $key = trim((string) ($section['note_key'] ?? ''));
            if ($key !== '') {
                $questions[] = [
                    'key' => $key,
                    'label' => (string) ($section['note_label'] ?? 'Catatan'),
                ];
            }
        }

        return $questions;
    }

    private function selectedBranch(): string
    {
        return is_effective_central_discipleship_readonly()
            ? normalize_central_recap_branch(central_recap_selected_branch())
            : normalize_user_branch(current_user_branch());
    }

    private function formattedDate(string $value): string
    {
        return trim($value) !== '' ? format_datetime_id($value) : '-';
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
