<?php

namespace App\Http\Requests\SpiritualJourney;

use App\Models\MskParticipant;
use App\Services\Auth\CurrentUserContext;
use App\Services\Routing\AppPageRouteMap;
use App\Support\RuntimeBootstrap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSpiritualJourneyBridgeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        RuntimeBootstrap::boot($this);
        $context = app(CurrentUserContext::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('spiritual_journey')
            && $context->canUseAction('save_journey_bridge_status')
            && ! $context->isDiscipleshipPreviewReadonly();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'min:1'],
            'journey_bridge_status' => ['required', 'string', 'in:belum,sudah_rg,sudah_kgap,ikut_keduanya'],
        ];
    }

    public function participantId(): int
    {
        $participant = $this->route('participant');
        if ($participant instanceof MskParticipant) {
            return (int) $participant->getKey();
        }

        if (is_string($participant) || is_int($participant)) {
            return (int) $participant;
        }

        return (int) $this->input('id', 0);
    }

    public function status(): string
    {
        return normalize_journey_bridge_status((string) $this->input('journey_bridge_status', 'belum'));
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
