<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpiritualJourney\UpdateSpiritualJourneyBridgeStatusRequest;
use App\Models\MskParticipant;
use App\Services\Routing\AppPageRouteMap;
use App\Services\SpiritualJourney\SpiritualJourneyBridgeStatusService;
use App\Services\SpiritualJourney\SpiritualJourneyPageData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SpiritualJourneyController extends Controller
{
    public function index(Request $request, SpiritualJourneyPageData $pageData): RedirectResponse|Response
    {
        RuntimeBootstrap::boot($request);

        if (trim((string) $request->input('action', '')) === 'logout') {
            destroy_current_session();

            return redirect()->route('home');
        }

        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }

        if (! branch_can_access_page(current_user_branch(), 'spiritual_journey')) {
            return redirect(AppPageRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied']));
        }

        return response(view('discipleship.spiritual-journey.index', $pageData->forCurrentContext($request))->render());
    }

    public function updateBridgeStatus(
        UpdateSpiritualJourneyBridgeStatusRequest $request,
        MskParticipant $participant,
        SpiritualJourneyBridgeStatusService $service,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        return $this->updateStatusAndRedirect($service, trim((string) $participant->public_id), $request->status());
    }

    public function updateBridgeStatusFromForm(
        UpdateSpiritualJourneyBridgeStatusRequest $request,
        SpiritualJourneyBridgeStatusService $service,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        return $this->updateStatusAndRedirect($service, $request->participantPublicId(), $request->status());
    }

    private function updateStatusAndRedirect(
        SpiritualJourneyBridgeStatusService $service,
        string $participantPublicId,
        string $status,
    ): RedirectResponse {
        if (! $service->update($participantPublicId, $status)) {
            return redirect()->route('discipleship.spiritual-journey');
        }

        return redirect()->route('discipleship.spiritual-journey', ['saved' => 1]);
    }
}
