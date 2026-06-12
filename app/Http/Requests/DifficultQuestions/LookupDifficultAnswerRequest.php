<?php

namespace App\Http\Requests\DifficultQuestions;

use App\Support\LegacyRuntimeBootstrap;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LookupDifficultAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        LegacyRuntimeBootstrap::boot($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question_password' => ['required', 'string', 'min:4'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        unset($_SESSION['difficult_answer_lookup_hash']);

        throw new HttpResponseException(
            redirect()->route('public.difficult-question.answer', ['error' => 'password_required']),
        );
    }
}
