<?php

namespace App\Http\Requests\WorshipServiceSchedules;

use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteWorshipServiceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);

        return is_logged_in()
            && branch_can_access_page(current_user_branch(), 'worship_penatalayan')
            && branch_can_use_action(current_user_branch(), 'delete_worship_penatalayan');
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
        if (! is_logged_in()) {
            throw new HttpResponseException(redirect()->route('auth.login'));
        }

        throw new HttpResponseException(
            redirect(AppPageRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied'])),
        );
    }
}
