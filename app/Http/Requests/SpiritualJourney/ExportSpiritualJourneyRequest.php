<?php

namespace App\Http\Requests\SpiritualJourney;

use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExportSpiritualJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('spiritual_journey')
            && $context->canUseAction('export_spiritual_journey_excel');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'journey_filter' => ['nullable', 'in:all,dg_without_kgap'],
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
