<?php

namespace App\Services\DifficultQuestions;

class DifficultQuestionTextNormalizer
{
    public function normalize(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', ' ', $value) ?? $value;
        $value = trim($value);

        if ($maxLength > 0 && strlen($value) > $maxLength) {
            $value = trim(substr($value, 0, $maxLength));
        }

        return $value;
    }
}
