<?php

namespace App\Http\Requests\MskParticipants;

use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExportMskParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);

        return is_logged_in()
            && branch_can_access_page(current_user_branch(), 'msk_classes')
            && branch_can_use_action(current_user_branch(), 'export_pemuridan_excel');
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

    protected function failedAuthorization(): void
    {
        if (! is_logged_in()) {
            throw new HttpResponseException(redirect()->route('auth.login'));
        }

        throw new HttpResponseException(
            redirect(AppPageRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied'])),
        );
    }
}
