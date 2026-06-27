<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpiritualJourney\UpdateSpiritualJourneyBridgeStatusRequest;
use App\Models\MskParticipant;
use App\Services\SpiritualJourney\SpiritualJourneyBridgeStatusService;
use App\Services\SpiritualJourney\SpiritualJourneyPageData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SpiritualJourneyController extends Controller
{
    public function index(Request $request, SpiritualJourneyPageData $pageData): Response
    {
        RuntimeBootstrap::boot($request);

        return response(view('discipleship.spiritual-journey.index', $pageData->forCurrentContext($request))->render());
    }

    public function rows(Request $request, SpiritualJourneyPageData $pageData): JsonResponse
    {
        RuntimeBootstrap::boot($request);

        $data = $pageData->paginatedRowsForCurrentContext($request);
        $stats = is_array($data['spiritualJourneyStats'] ?? null) ? $data['spiritualJourneyStats'] : [];

        return response()->json([
            'html' => view('discipleship.spiritual-journey.partials.rows', [
                'rows' => $data['spiritualJourneyRows'] ?? [],
            ])->render(),
            'templates_html' => view('discipleship.spiritual-journey.partials.view-templates', [
                'participantProfiles' => $data['participantProfiles'] ?? [],
                'mskClasses' => $data['mskClasses'] ?? [],
            ])->render(),
            'has_more' => (bool) ($data['hasMoreSpiritualJourneyRows'] ?? false),
            'next_page' => $data['nextSpiritualJourneyPage'] ?? null,
            'stats' => [
                'dg1' => (int) ($stats['completed_dg1'] ?? 0),
                'kgap' => (int) ($stats['following_kgap'] ?? 0),
                'dg2' => (int) ($stats['completed_dg2'] ?? 0),
                'dg3' => (int) ($stats['completed_dg3'] ?? 0),
            ],
            'empty_message' => (string) ($data['spiritualJourneyEmptyMessage'] ?? 'Peserta tidak ditemukan.'),
        ]);
    }

    public function updateBridgeStatus(
        UpdateSpiritualJourneyBridgeStatusRequest $request,
        MskParticipant $participant,
        SpiritualJourneyBridgeStatusService $service,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        return $this->updateStatusAndRedirect($service, (int) $participant->getKey(), $request->status());
    }

    public function updateBridgeStatusFromForm(
        UpdateSpiritualJourneyBridgeStatusRequest $request,
        SpiritualJourneyBridgeStatusService $service,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        return $this->updateStatusAndRedirect($service, $request->participantId(), $request->status());
    }

    private function updateStatusAndRedirect(
        SpiritualJourneyBridgeStatusService $service,
        int $participantId,
        string $status,
    ): RedirectResponse {
        if (! $service->update($participantId, $status)) {
            return redirect()->route('discipleship.spiritual-journey');
        }

        return redirect()->route('discipleship.spiritual-journey', ['saved' => 1]);
    }
}
