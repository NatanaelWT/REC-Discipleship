<?php

namespace App\Http\Requests\WorshipServiceSchedules;

use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteWorshipServiceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('worship_penatalayan')
            && $context->canUseAction('delete_worship_penatalayan');
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'month' => normalize_month_value((string) ($this->route('month') ?? $this->input('month', date('Y-m')))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }

    public function scheduleMonth(): string
    {
        return (string) $this->input('month', date('Y-m'));
    }

    protected function failedAuthorization()
    {
        $context = app(CurrentUserContext::class);
        if (! $context->isLoggedIn()) {
            throw new HttpResponseException(redirect()->route('auth.login'));
        }

        throw new HttpResponseException(
            redirect(AppPageRouteMap::pageUrl($context->homePage(), ['error' => 'access_denied'])),
        );
    }
}
