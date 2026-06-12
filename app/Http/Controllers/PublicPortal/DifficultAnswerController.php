<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\DifficultQuestions\LookupDifficultAnswerRequest;
use App\Models\DifficultQuestion;
use App\Services\DifficultQuestions\DifficultQuestionPasswordService;
use App\Support\LegacyRuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DifficultAnswerController extends Controller
{
    public function show(Request $request): View
    {
        LegacyRuntimeBootstrap::boot($request);

        $lookupHash = trim((string) ($_SESSION['difficult_answer_lookup_hash'] ?? ''));
        $hasLookup = $request->query->has('looked') && $lookupHash !== '';

        return view('public.difficult-questions.lookup', [
            'settings' => ['church_name' => CHURCH_NAME],
            'errorCode' => trim((string) $request->query('error', '')),
            'hasLookup' => $hasLookup,
            'matchedQuestions' => $hasLookup
                ? DifficultQuestion::query()->forLookupHash($lookupHash)->orderByDesc('created_at')->get()
                : collect(),
        ]);
    }

    public function lookup(
        LookupDifficultAnswerRequest $request,
        DifficultQuestionPasswordService $passwordService,
    ): RedirectResponse {
        $_SESSION['difficult_answer_lookup_hash'] = $passwordService->lookupHash(
            (string) $request->input('question_password', ''),
        );

        return redirect()->route('public.difficult-question.answer', ['looked' => 1]);
    }
}
