<?php

namespace App\Http\Requests\SpiritualJourney;

use App\Models\MskParticipant;
use App\Services\Routing\CompatibilityRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSpiritualJourneyBridgeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);

        return is_logged_in()
            && branch_can_access_page(current_user_branch(), 'spiritual_journey')
            && branch_can_use_action(current_user_branch(), 'save_journey_bridge_status')
            && ! is_effective_central_discipleship_readonly();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'string'],
            'journey_bridge_status' => ['required', 'string', 'in:belum,sudah_rg,sudah_kgap,ikut_keduanya'],
        ];
    }

    public function participantPublicId(): string
    {
        $participant = $this->route('participant');
        if ($participant instanceof MskParticipant) {
            return trim((string) $participant->public_id);
        }

        if (is_string($participant) || is_int($participant)) {
            return trim((string) $participant);
        }

        return trim((string) $this->input('id', ''));
    }

    public function status(): string
    {
        return normalize_journey_bridge_status((string) $this->input('journey_bridge_status', 'belum'));
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
