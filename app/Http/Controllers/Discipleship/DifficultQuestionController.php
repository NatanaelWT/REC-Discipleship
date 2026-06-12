<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DifficultQuestions\AnswerDifficultQuestionRequest;
use App\Models\DifficultQuestion;
use App\Services\DifficultQuestions\DifficultQuestionTextNormalizer;
use App\Services\Legacy\LegacyRouteMap;
use App\Support\LegacyRuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DifficultQuestionController extends Controller
{
    public function index(Request $request): RedirectResponse|View
    {
        $legacyPage = trim((string) $request->query('page', ''));
        if ($legacyPage !== '' && LegacyRouteMap::hasPage($legacyPage)) {
            return redirect()->away($request->getSchemeAndHttpHost() . LegacyRouteMap::pageUrl($legacyPage, $request->query()));
        }

        LegacyRuntimeBootstrap::boot($request);

        if (! can_manage_difficult_questions()) {
            return redirect(LegacyRouteMap::pageUrl(
                branch_home_page(current_user_branch()),
                ['error' => 'access_denied'],
            ));
        }

        $questions = DifficultQuestion::query()->pendingFirst()->get();

        return view('discipleship.difficult-questions.index', [
            'settings' => ['church_name' => CHURCH_NAME],
            'questions' => $questions,
            'pendingQuestionCount' => $questions->where('status', DifficultQuestion::STATUS_PENDING)->count(),
            'answeredQuestionCount' => $questions->where('status', DifficultQuestion::STATUS_ANSWERED)->count(),
            'errorCode' => trim((string) $request->query('error', '')),
            'answered' => $request->query->has('answered'),
        ]);
    }

    public function answer(
        AnswerDifficultQuestionRequest $request,
        DifficultQuestion $difficultQuestion,
    ): RedirectResponse {
        $this->saveAnswer($difficultQuestion, (string) $request->input('answer_text', ''));

        return redirect()->route('discipleship.difficult-questions', ['answered' => 1]);
    }

    public function answerLegacy(Request $request, DifficultQuestionTextNormalizer $normalizer): RedirectResponse
    {
        LegacyRuntimeBootstrap::boot($request);

        if (trim((string) $request->input('action', '')) === 'logout') {
            destroy_current_session();

            return redirect('/index.php');
        }

        if (! can_manage_difficult_questions()) {
            return redirect(LegacyRouteMap::pageUrl(
                branch_home_page(current_user_branch()),
                ['error' => 'access_denied'],
            ));
        }

        $publicId = trim((string) $request->input('id', ''));
        if ($publicId === '') {
            return redirect()->route('discipleship.difficult-questions', ['error' => 'missing_question']);
        }

        $answerText = $normalizer->normalize((string) $request->input('answer_text', ''), 8000);
        if ($answerText === '') {
            return redirect()->route('discipleship.difficult-questions', ['error' => 'missing_answer']);
        }

        $question = DifficultQuestion::query()->where('public_id', $publicId)->first();
        if (! $question instanceof DifficultQuestion) {
            return redirect()->route('discipleship.difficult-questions', ['error' => 'question_not_found']);
        }

        $this->saveAnswer($question, $answerText);

        return redirect()->route('discipleship.difficult-questions', ['answered' => 1]);
    }

    private function saveAnswer(DifficultQuestion $question, string $answerText): void
    {
        $question->forceFill([
            'answer' => $answerText,
            'status' => DifficultQuestion::STATUS_ANSWERED,
            'answered_by_username' => current_username(),
            'answered_at' => now(),
            'updated_at' => now(),
        ])->save();
    }
}
