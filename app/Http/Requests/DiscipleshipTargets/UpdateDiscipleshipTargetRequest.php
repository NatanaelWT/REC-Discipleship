<?php

namespace App\Http\Requests\DiscipleshipTargets;

use App\Services\Auth\CurrentUserContext;
use App\Services\DiscipleshipTargets\DiscipleshipTargetNormalizer;
use App\Services\Routing\AppPageRouteMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDiscipleshipTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('discipleship_targets')
            && $context->canUseAction('save_discipleship_targets');
    }

    protected function prepareForValidation()
    {
        $normalized = app(DiscipleshipTargetNormalizer::class)->normalizeFormValues([
            'dg_total_people' => $this->input('target_dg_total_people', ''),
            'msk_completed' => $this->input('target_msk_completed', ''),
            'dg1_people' => $this->input('target_dg1_people', ''),
            'dg2_people' => $this->input('target_dg2_people', ''),
            'dg3_people' => $this->input('target_dg3_people', ''),
        ]);

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'camp_gap_participant_target' => ['required', 'integer', 'min:0', 'max:1000000'],
            'msk_completion_target' => ['required', 'integer', 'min:0', 'max:1000000'],
            'dg1_completion_target' => ['required', 'integer', 'min:0', 'max:1000000'],
            'dg2_completion_target' => ['required', 'integer', 'min:0', 'max:1000000'],
            'dg3_completion_target' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function targetValues(): array
    {
        return [
            'camp_gap_participant_target' => (int) $this->input('camp_gap_participant_target', 50),
            'msk_completion_target' => (int) $this->input('msk_completion_target', 50),
            'dg1_completion_target' => (int) $this->input('dg1_completion_target', 50),
            'dg2_completion_target' => (int) $this->input('dg2_completion_target', 50),
            'dg3_completion_target' => (int) $this->input('dg3_completion_target', 50),
        ];
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
