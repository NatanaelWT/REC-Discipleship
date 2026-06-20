<?php

namespace App\Services\DifficultQuestions;

use App\Models\DifficultQuestion;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class DifficultQuestionAdminPageData
{
    public function __construct(private DifficultQuestionStatusLabel $statusLabel)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forRequest(Request $request): array
    {
        $questions = DifficultQuestion::query()->pendingFirst()->get();
        $questionItems = $questions->map(fn (DifficultQuestion $question): array => $this->adminItem($question));

        return [
            'settings' => ['church_name' => app_church_name()],
            'questionItems' => $questionItems,
            'pendingQuestionCount' => $questions->where('status', DifficultQuestion::STATUS_PENDING)->count(),
            'answeredQuestionCount' => $questions->where('status', DifficultQuestion::STATUS_ANSWERED)->count(),
            'totalQuestionCount' => $questions->count(),
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
        $publicId = trim((string) $question->public_id);
        $questionText = trim((string) $question->question);
        $answerText = trim((string) $question->answer);
        $askerName = trim((string) $question->asker_name);
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
            'publicId' => $publicId,
            'questionText' => $questionText,
            'answerText' => $answerText,
            'askerName' => $askerName,
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
}
