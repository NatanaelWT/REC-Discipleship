<?php

namespace App\Http\Requests\DiscipleshipDashboard;

use App\Services\Routing\CompatibilityRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDashboardMskSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);

        return is_logged_in()
            && ! is_effective_central_discipleship_readonly()
            && branch_can_access_page(current_user_branch(), 'discipleship_dashboard')
            && branch_can_use_action(current_user_branch(), 'save_msk_sessions');
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
        if (! is_logged_in()) {
            throw new HttpResponseException(redirect()->route('auth.login'));
        }

        throw new HttpResponseException(
            redirect(CompatibilityRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied'])),
        );
    }
}
