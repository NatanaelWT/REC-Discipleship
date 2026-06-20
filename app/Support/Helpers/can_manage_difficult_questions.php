<?php

use App\Services\Auth\CurrentUserContext;

function can_manage_difficult_questions(): bool
{
    $context = app(CurrentUserContext::class);

    return $context->isLoggedIn()
        && $context->canAccessPage('difficult_questions_admin')
        && $context->canUseAction('save_difficult_question_answer');
}
