<?php

namespace App\Http\Requests\DiscipleshipPeople;

use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExportDiscipleshipPeopleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('people_list')
            && $context->canUseAction('export_people_excel');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'progress' => ['nullable', 'in:all,active_dg1,complete_dg1,active_dg2,complete_dg2,active_dg3,complete_dg3'],
        ];
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
