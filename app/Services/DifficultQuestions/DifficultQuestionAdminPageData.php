<?php

namespace App\Services\DifficultQuestions;

use App\Models\DifficultQuestion;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DifficultQuestionAdminPageData
{
    public function __construct(private DifficultQuestionStatusLabel $statusLabel) {}

    /**
     * @return array<string, mixed>
     */
    public function forRequest(Request $request): array
    {
        $questionMonthFilter = $this->normalizedMonth((string) $request->query('month', ''));
        $questionSearch = trim((string) $request->query('q', ''));
        $questions = DifficultQuestion::query()
            ->when($questionMonthFilter !== '', static function ($query) use ($questionMonthFilter): void {
                $start = Carbon::createFromFormat('Y-m', $questionMonthFilter)->startOfMonth();
                $query->whereBetween('created_at', [
                    $start->toDateTimeString(),
                    $start->copy()->endOfMonth()->toDateTimeString(),
                ]);
            })
            ->when($questionSearch !== '', static function ($query) use ($questionSearch): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $questionSearch).'%';
                $query->where(static function ($query) use ($like): void {
                    $query->where('asker_name', 'like', $like)
                        ->orWhere('asker_whatsapp', 'like', $like)
                        ->orWhere('question', 'like', $like)
                        ->orWhere('answer', 'like', $like)
                        ->orWhere('answered_by_username', 'like', $like);
                });
            })
            ->pendingFirst()
            ->get();
        $questionItems = $questions->map(fn (DifficultQuestion $question): array => $this->adminItem($question));

        return [
            'settings' => ['church_name' => app_church_name()],
            'questionItems' => $questionItems,
            'pendingQuestionCount' => $questions->where('status', DifficultQuestion::STATUS_PENDING)->count(),
            'answeredQuestionCount' => $questions->where('status', DifficultQuestion::STATUS_ANSWERED)->count(),
            'whatsappQuestionCount' => $questions->filter(static fn (DifficultQuestion $question): bool => normalize_whatsapp_digits((string) ($question->asker_whatsapp ?? '')) !== '')->count(),
            'totalQuestionCount' => $questions->count(),
            'questionMonthFilter' => $questionMonthFilter,
            'questionSearch' => $questionSearch,
            'canAnswerDifficultQuestions' => can_manage_difficult_questions(),
            'errorCode' => trim((string) $request->query('error', '')),
            'errorMessages' => $this->errorMessages(),
            'answered' => $request->query->has('answered'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function errorMessages(): array
    {
        return [
            'missing_question' => 'Pertanyaan yang akan dijawab tidak ditemukan.',
            'missing_answer' => 'Isi jawaban terlebih dahulu.',
            'question_not_found' => 'Data pertanyaan tidak ditemukan.',
            'save_failed' => 'Jawaban gagal disimpan. Coba ulangi lagi.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminItem(DifficultQuestion $question): array
    {
        $questionId = (int) $question->getKey();
        $questionText = trim((string) $question->question);
        $answerText = trim((string) $question->answer);
        $askerName = trim((string) $question->asker_name);
        $askerWhatsappDigits = normalize_whatsapp_digits((string) ($question->asker_whatsapp ?? ''));
        $answeredBy = trim((string) $question->answered_by_username);
        $status = strtolower(trim((string) $question->status));

        if ($askerName === '') {
            $askerName = 'Anonim';
        }

        if ($questionText === '') {
            $questionText = '(Pertanyaan kosong)';
        }

        return [
            'model' => $question,
            'id' => $questionId,
            'questionText' => $questionText,
            'answerText' => $answerText,
            'askerName' => $askerName,
            'askerWhatsapp' => $askerWhatsappDigits !== '' ? '+'.$askerWhatsappDigits : '',
            'askerWhatsappUrl' => $askerWhatsappDigits !== '' ? 'https://wa.me/'.$askerWhatsappDigits : '',
            'createdAt' => $this->dateTimeLabel($question->created_at),
            'answeredAt' => $this->dateTimeLabel($question->answered_at),
            'answeredBy' => $answeredBy,
            'statusLabel' => $this->statusLabel->label($status),
            'statusClass' => $status === DifficultQuestion::STATUS_ANSWERED ? 'badge success' : 'badge warning',
            'answerButtonLabel' => $answerText === '' ? 'Simpan Jawaban' : 'Perbarui Jawaban',
        ];
    }

    private function dateTimeLabel(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            $value = $value->toIso8601String();
        }

        return function_exists('format_datetime_id')
            ? format_datetime_id((string) $value)
            : ((string) $value !== '' ? (string) $value : '-');
    }

    private function normalizedMonth(string $value): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m', $value)->format('Y-m');
        } catch (\Throwable) {
            return '';
        }
    }
}
