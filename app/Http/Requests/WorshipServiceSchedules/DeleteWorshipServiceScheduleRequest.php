<?php

namespace App\Http\Requests\WorshipServiceSchedules;

use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteWorshipServiceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('worship_penatalayan')
            && $context->canUseAction('delete_worship_penatalayan');
    }

    protected function prepareForValidation()
    {
        RuntimeBootstrap::boot($this);

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
