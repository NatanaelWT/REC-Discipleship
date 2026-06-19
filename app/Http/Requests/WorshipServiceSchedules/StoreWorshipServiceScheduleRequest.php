<?php

namespace App\Http\Requests\WorshipServiceSchedules;

use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use App\Services\WorshipServiceSchedules\WorshipServiceScheduleNormalizer;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreWorshipServiceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('worship_penatalayan')
            && $context->canUseAction('save_worship_penatalayan');
    }

    protected function prepareForValidation()
    {
        RuntimeBootstrap::boot($this);

        $this->merge([
            'month' => normalize_month_value((string) $this->input('month', date('Y-m'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'title' => ['nullable', 'string', 'max:255'],
            'update_note' => ['nullable', 'string'],
            'row_labels' => ['nullable', 'array'],
            'assignments' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scheduleRecord(): array
    {
        return app(WorshipServiceScheduleNormalizer::class)->fromRequestInput(
            (string) $this->input('month', date('Y-m')),
            (string) $this->input('title', ''),
            (string) $this->input('update_note', ''),
            is_array($this->input('row_labels')) ? $this->input('row_labels') : [],
            is_array($this->input('assignments')) ? $this->input('assignments') : [],
        );
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
