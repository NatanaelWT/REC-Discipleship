<?php

namespace App\Http\Requests\DiscipleshipDashboard;

use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDashboardMskSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && ! $context->isCentralDiscipleshipReadonly()
            && $context->canAccessPage('discipleship_dashboard')
            && $context->canUseAction('save_msk_sessions');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'string'],
            'session_numbers' => ['nullable', 'array'],
        ];
    }

    public function participantPublicId(): string
    {
        return trim((string) $this->input('id', ''));
    }

    /**
     * @return array<int, int>
     */
    public function sessionNumbers(): array
    {
        return normalize_msk_session_numbers($this->input('session_numbers', []));
    }

    protected function failedAuthorization(): void
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
