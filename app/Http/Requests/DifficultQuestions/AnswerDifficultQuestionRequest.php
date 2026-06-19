<?php

namespace App\Http\Requests\DifficultQuestions;

use App\Services\Auth\CurrentUserContext;
use App\Services\DifficultQuestions\DifficultQuestionTextNormalizer;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AnswerDifficultQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);
        $context = app(CurrentUserContext::class);

        return $context->canAccessPage('difficult_questions_admin');
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
        $context = app(CurrentUserContext::class);

        throw new HttpResponseException(
            redirect(AppPageRouteMap::pageUrl($context->homePage(), ['error' => 'access_denied'])),
        );
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            redirect()->route('discipleship.difficult-questions', ['error' => 'missing_answer']),
        );
    }
}
