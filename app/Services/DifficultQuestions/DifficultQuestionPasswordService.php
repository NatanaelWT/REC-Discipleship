<?php

namespace App\Services\DifficultQuestions;

class DifficultQuestionPasswordService
{
    public function lookupHash(string $password): string
    {
        $password = trim($password);
        if ($password === '') {
            return '';
        }

        return hash('sha256', 'difficult-question-access|' . $password);
    }

    public function passwordHash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
