<?php

namespace App\Http\Requests\DiscipleshipTargets;

use App\Services\DiscipleshipTargets\DiscipleshipTargetNormalizer;
use App\Services\Routing\CompatibilityRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDiscipleshipTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);

        return is_logged_in()
            && branch_can_access_page(current_user_branch(), 'discipleship_targets')
            && branch_can_use_action(current_user_branch(), 'save_discipleship_targets');
    }

    protected function prepareForValidation()
    {
        RuntimeBootstrap::boot($this);

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
        if (! is_logged_in()) {
            throw new HttpResponseException(redirect()->route('auth.login'));
        }

        throw new HttpResponseException(
            redirect(CompatibilityRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied'])),
        );
    }
}
