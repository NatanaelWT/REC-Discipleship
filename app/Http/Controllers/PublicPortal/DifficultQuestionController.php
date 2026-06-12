<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\DifficultQuestions\StoreDifficultQuestionRequest;
use App\Models\DifficultQuestion;
use App\Services\DifficultQuestions\DifficultQuestionPasswordService;
use App\Support\LegacyRuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DifficultQuestionController extends Controller
{
    public function create(Request $request): View
    {
        LegacyRuntimeBootstrap::boot($request);

        return view('public.difficult-questions.create', [
            'settings' => ['church_name' => CHURCH_NAME],
            'old' => is_array($_SESSION['difficult_question_old'] ?? null) ? $_SESSION['difficult_question_old'] : [],
            'errorCode' => trim((string) $request->query('error', '')),
            'submitted' => $request->query->has('submitted'),
        ]);
    }

    public function store(
        StoreDifficultQuestionRequest $request,
        DifficultQuestionPasswordService $passwordService,
    ): RedirectResponse {
        try {
            $password = trim((string) $request->input('question_password', ''));
            $now = now();

            DifficultQuestion::query()->create([
                'public_id' => $this->generatePublicId(),
                'asker_name' => trim((string) $request->input('asker_name', '')) ?: null,
                'question' => (string) $request->input('question_text', ''),
                'password_hash' => $passwordService->passwordHash($password),
                'password_lookup_hash' => $passwordService->lookupHash($password),
                'status' => DifficultQuestion::STATUS_PENDING,
                'answer' => null,
                'answered_by_username' => null,
                'answered_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable) {
            return redirect()->route('public.difficult-question.submit', ['error' => 'save_failed']);
        }

        unset($_SESSION['difficult_question_old']);

        return redirect()->route('public.difficult-question.submit', ['submitted' => 1]);
    }

    private function generatePublicId(): string
    {
        do {
            $id = 'dq_' . bin2hex(random_bytes(4));
        } while (DifficultQuestion::query()->where('public_id', $id)->exists());

        return $id;
    }
}
