<?php

function difficult_question_password_lookup(string $password): string {
    $password = trim($password);
    if ($password === '') {
        return '';
    }
    return hash('sha256', 'difficult-question-access|' . $password);
}
