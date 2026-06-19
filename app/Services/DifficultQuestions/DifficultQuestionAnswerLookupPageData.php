<?php

namespace App\Services\DifficultQuestions;

use App\Models\DifficultQuestion;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class DifficultQuestionAnswerLookupPageData
{
    public function __construct(private DifficultQuestionStatusLabel $statusLabel)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forRequest(Request $request): array
    {
        $lookupHash = trim((string) ($_SESSION['difficult_answer_lookup_hash'] ?? ''));
        $hasLookup = $request->query->has('looked') && $lookupHash !== '';
        $questions = $hasLookup
            ? DifficultQuestion::query()->forLookupHash($lookupHash)->orderByDesc('created_at')->get()
            : collect();

        return [
            'settings' => ['church_name' => app_church_name()],
            'errorCode' => trim((string) $request->query('error', '')),
            'hasLookup' => $hasLookup,
            'matchedQuestionItems' => $questions->map(fn (DifficultQuestion $question): array => $this->lookupItem($question)),
            'matchedQuestionCount' => $questions->count(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function lookupItem(DifficultQuestion $question): array
    {
        $questionText = trim((string) $question->question);
        $answerText = trim((string) $question->answer);
        $status = strtolower(trim((string) $question->status));
        $createdDate = $this->ymdDate($question->created_at);
        $answeredDate = $this->ymdDate($question->answered_at);

        return [
            'questionText' => $questionText,
            'answerText' => $answerText,
            'status' => $status,
            'statusLabel' => $this->statusLabel->label($status),
            'statusClass' => $status === DifficultQuestion::STATUS_ANSWERED && $answerText !== '' ? 'success' : 'warning',
            'createdLabel' => $createdDate !== '' ? $this->indoDate($createdDate) : '-',
            'answeredLabel' => $answeredDate !== '' ? $this->indoDate($answeredDate) : '',
        ];
    }

    private function ymdDate(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            $value = $value->format('Y-m-d');
        }

        return function_exists('normalize_ymd_date')
            ? normalize_ymd_date((string) $value)
            : trim((string) $value);
    }

    private function indoDate(string $value): string
    {
        return function_exists('format_indo_date') ? format_indo_date($value) : $value;
    }
}
