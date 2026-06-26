<?php

namespace App\Http\Requests\DifficultQuestions;

use App\Services\DifficultQuestions\DifficultQuestionTextNormalizer;
use App\Support\RuntimeBootstrap;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDifficultQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        RuntimeBootstrap::boot($this);

        $normalizer = app(DifficultQuestionTextNormalizer::class);
        $askerName = $normalizer->normalize((string) $this->input('asker_name', ''), 120);
        $askerWhatsapp = normalize_whatsapp_digits((string) $this->input('asker_whatsapp', ''));
        $questionText = $normalizer->normalize((string) $this->input('question_text', ''), 6000);

        session()->put('difficult_question_old', [
            'asker_name' => $askerName,
            'asker_whatsapp' => $askerWhatsapp,
            'question_text' => $questionText,
        ]);

        $this->merge([
            'asker_name' => $askerName,
            'asker_whatsapp' => $askerWhatsapp,
            'question_text' => $questionText,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asker_name' => ['nullable', 'string', 'max:120'],
            'asker_whatsapp' => ['nullable', 'string', 'max:20'],
            'question_text' => ['required', 'string', 'max:6000'],
            'question_password' => ['required', 'string', 'min:4'],
            'question_password_confirm' => ['required', 'same:question_password'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $error = 'save_failed';

        if ($errors->has('question_text')) {
            $error = 'missing_question';
        } elseif ($errors->has('asker_whatsapp')) {
            $error = 'invalid_whatsapp';
        } elseif ($errors->has('question_password')) {
            $error = 'password_short';
        } elseif ($errors->has('question_password_confirm')) {
            $error = 'password_mismatch';
        }

        throw new HttpResponseException(
            redirect()->route('public.difficult-question.submit', ['error' => $error]),
        );
    }
}
