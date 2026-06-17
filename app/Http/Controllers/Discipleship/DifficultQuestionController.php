<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DifficultQuestions\AnswerDifficultQuestionRequest;
use App\Models\DifficultQuestion;
use App\Services\DifficultQuestions\DifficultQuestionAdminPageData;
use App\Services\DifficultQuestions\DifficultQuestionTextNormalizer;
use App\Services\Routing\CompatibilityRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DifficultQuestionController extends Controller
{
    public function index(Request $request, DifficultQuestionAdminPageData $pageData): RedirectResponse|View
    {
        $pageQuery = trim((string) $request->query('page', ''));
        if ($pageQuery !== '' && CompatibilityRouteMap::hasPage($pageQuery)) {
            return redirect()->away($request->getSchemeAndHttpHost() . CompatibilityRouteMap::pageUrl($pageQuery, $request->query()));
        }

        RuntimeBootstrap::boot($request);

        if (! can_manage_difficult_questions()) {
            return redirect(CompatibilityRouteMap::pageUrl(
                branch_home_page(current_user_branch()),
                ['error' => 'access_denied'],
            ));
        }

        return view('discipleship.difficult-questions.index', $pageData->forRequest($request));
    }

    public function answer(
        AnswerDifficultQuestionRequest $request,
        DifficultQuestion $difficultQuestion,
    ): RedirectResponse {
        $this->saveAnswer($difficultQuestion, (string) $request->input('answer_text', ''));

        return redirect()->route('discipleship.difficult-questions', ['answered' => 1]);
    }

    public function answerFromForm(Request $request, DifficultQuestionTextNormalizer $normalizer): RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        if (trim((string) $request->input('action', '')) === 'logout') {
            destroy_current_session();

            return redirect('/index.php');
        }

        if (! can_manage_difficult_questions()) {
            return redirect(CompatibilityRouteMap::pageUrl(
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
