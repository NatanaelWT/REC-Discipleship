<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\DifficultQuestions\LookupDifficultAnswerRequest;
use App\Services\DifficultQuestions\DifficultQuestionAnswerLookupPageData;
use App\Services\DifficultQuestions\DifficultQuestionPasswordService;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DifficultAnswerController extends Controller
{
    public function show(Request $request, DifficultQuestionAnswerLookupPageData $pageData): View
    {
        RuntimeBootstrap::boot($request);

        return view('public.difficult-questions.lookup', $pageData->forRequest($request));
    }

    public function lookup(
        LookupDifficultAnswerRequest $request,
        DifficultQuestionPasswordService $passwordService,
    ): RedirectResponse {
        session()->put('difficult_answer_lookup_hash', $passwordService->lookupHash(
            (string) $request->input('question_password', ''),
        ));

        return redirect()->route('public.difficult-question.answer', ['looked' => 1]);
    }
}
