<?php

namespace App\Http\Requests\Settings;

use App\Services\Auth\CurrentUserContext;
use App\Support\RuntimeBootstrap;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSettingsPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);

        return app(CurrentUserContext::class)->isLoggedIn();
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6'],
            'new_password_confirm' => ['required', 'same:new_password'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()->route('auth.login'),
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        $failed = $validator->failed();
        $error = 'missing_pw_field';

        if (isset($failed['current_password']['Required']) || isset($failed['new_password']['Required']) || isset($failed['new_password_confirm']['Required'])) {
            $error = 'missing_pw_field';
        } elseif (isset($failed['new_password_confirm']['Same'])) {
            $error = 'pw_mismatch';
        } elseif (isset($failed['new_password']['Min'])) {
            $error = 'pw_short';
        }

        throw new HttpResponseException(
            redirect()->route('settings', ['error' => $error]),
        );
    }
}
