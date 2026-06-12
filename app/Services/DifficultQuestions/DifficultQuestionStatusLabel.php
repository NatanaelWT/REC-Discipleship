<?php

namespace App\Services\DifficultQuestions;

use App\Models\DifficultQuestion;

class DifficultQuestionStatusLabel
{
    public function label(string $status): string
    {
        return strtolower(trim($status)) === DifficultQuestion::STATUS_ANSWERED
            ? 'Sudah Dijawab'
            : 'Menunggu Jawaban';
    }
}
