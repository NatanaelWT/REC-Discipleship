<?php

namespace App\Http\Requests\DifficultQuestions;

use App\Services\DifficultQuestions\DifficultQuestionTextNormalizer;
use App\Support\RuntimeBootstrap;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AnswerDifficultQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);

        return function_exists('can_manage_difficult_questions') && can_manage_difficult_questions();
    }

    protected function prepareForValidation()
    {
        RuntimeBootstrap::boot($this);

        $this->merge([
            'answer_text' => app(DifficultQuestionTextNormalizer::class)
                ->normalize((string) $this->input('answer_text', ''), 8000),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answer_text' => ['required', 'string', 'max:8000'],
        ];
    }

    protected function failedAuthorization()
    {
        $targetPage = function_exists('branch_home_page') && function_exists('current_user_branch')
            ? branch_home_page(current_user_branch())
            : 'discipleship_dashboard';

        throw new HttpResponseException(
            redirect(\App\Services\Routing\CompatibilityRouteMap::pageUrl($targetPage, ['error' => 'access_denied'])),
        );
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            redirect()->route('discipleship.difficult-questions', ['error' => 'missing_answer']),
        );
    }
}
