<?php

function difficult_question_status_label(string $status): string {
    $status = strtolower(trim($status));
    if ($status === 'answered') {
        return 'Sudah Dijawab';
    }
    return 'Menunggu Jawaban';
}
