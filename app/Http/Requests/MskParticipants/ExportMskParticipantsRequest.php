<?php

namespace App\Http\Requests\MskParticipants;

use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExportMskParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('msk_classes')
            && $context->canUseAction('export_pemuridan_excel');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function batchMonth(): string
    {
        $input = trim((string) $this->input('batch_month', ''));
        if ($input === '') {
            return '';
        }

        return strtolower($input) === 'all' ? 'all' : import_normalize_month_strict($input);
    }

    public function search(): string
    {
        $value = trim((string) $this->input('q', ''));

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
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
