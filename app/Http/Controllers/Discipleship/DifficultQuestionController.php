<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\DifficultQuestions\AnswerDifficultQuestionRequest;
use App\Models\DifficultQuestion;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\DifficultQuestions\DifficultQuestionAdminPageData;
use App\Services\DifficultQuestions\DifficultQuestionTextNormalizer;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DifficultQuestionController extends Controller
{
    public function index(
        Request $request,
        DifficultQuestionAdminPageData $pageData,
        CurrentDiscipleshipScope $scope,
    ): View {
        RuntimeBootstrap::boot($request);

        $pageTitle = 'Pertanyaan Sulit';
        $data = [
            ...$pageData->forRequest($request),
            'pageTitle' => $pageTitle,
        ];

        if ($request->header('X-Discipleship-Fragment') === 'tab') {
            return view('discipleship.difficult-questions.panel', $data);
        }

        return view('discipleship.journals.workspace', [
            ...$data,
            'activeTab' => 'questions',
            'currentPage' => 'difficult_questions_admin',
            'panelView' => 'discipleship.difficult-questions.panel',
            'tabBranchId' => $this->tabBranchId($request, $scope),
        ]);
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

        if (! can_manage_difficult_questions()) {
            return redirect()->route('discipleship.difficult-questions', ['error' => 'access_denied']);
        }

        $questionId = (int) $request->input('id', 0);
        if ($questionId < 1) {
            return redirect()->route('discipleship.difficult-questions', ['error' => 'missing_question']);
        }

        $answerText = $normalizer->normalize((string) $request->input('answer_text', ''), 8000);
        if ($answerText === '') {
            return redirect()->route('discipleship.difficult-questions', ['error' => 'missing_answer']);
        }

        $question = DifficultQuestion::query()->find($questionId);
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

    private function tabBranchId(Request $request, CurrentDiscipleshipScope $scope): int|string|null
    {
        if (! $request->query->has('branch_id') && ! $request->query->has('rekap_cabang')) {
            return null;
        }

        return $scope->includesAllBranches()
            ? 'all'
            : $scope->selectedBranchId();
    }
}
