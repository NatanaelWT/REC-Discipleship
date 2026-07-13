<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpiritualJourney\UpdateSpiritualJourneyBridgeStatusRequest;
use App\Models\Person;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\SpiritualJourney\SpiritualJourneyBridgeStatusService;
use App\Services\SpiritualJourney\SpiritualJourneyPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SpiritualJourneyController extends Controller
{
    public function index(
        Request $request,
        SpiritualJourneyPageData $pageData,
        CurrentDiscipleshipScope $scope,
    ): Response|View {
        $pageTitle = 'Spiritual Journey';
        $data = [
            ...$pageData->forCurrentContext($request),
            'pageTitle' => $pageTitle,
            'renderAsTabPanel' => true,
        ];

        if ($request->header('X-Discipleship-Fragment') === 'tab') {
            return response(view('discipleship.spiritual-journey.index', $data)->render());
        }

        return view('discipleship.journey.workspace', [
            ...$data,
            'activeTab' => 'spiritual',
            'currentPage' => 'spiritual_journey',
            'panelView' => 'discipleship.spiritual-journey.index',
            'tabBranchId' => $this->tabBranchId($request, $scope),
        ]);
    }

    public function rows(Request $request, SpiritualJourneyPageData $pageData): JsonResponse
    {
        $data = $pageData->paginatedRowsForCurrentContext($request);
        $stats = is_array($data['spiritualJourneyStats'] ?? null) ? $data['spiritualJourneyStats'] : [];

        return response()->json([
            'html' => view('discipleship.spiritual-journey.partials.rows', [
                'rows' => $data['spiritualJourneyRows'] ?? [],
            ])->render(),
            'stats' => [
                'dg1' => (int) ($stats['completed_dg1'] ?? 0),
                'kgap' => (int) ($stats['following_kgap'] ?? 0),
                'dg2' => (int) ($stats['completed_dg2'] ?? 0),
                'dg3' => (int) ($stats['completed_dg3'] ?? 0),
            ],
            'has_more' => (bool) ($data['hasMoreSpiritualJourneyRows'] ?? false),
            'next_cursor' => $data['nextSpiritualJourneyCursor'] ?? null,
            'empty' => count($data['spiritualJourneyRows'] ?? []) === 0,
            'empty_message' => (string) ($data['spiritualJourneyEmptyMessage'] ?? 'Peserta tidak ditemukan.'),
        ]);
    }

    public function detail(Request $request, Person $participant, SpiritualJourneyPageData $pageData): JsonResponse
    {
        $detail = $pageData->detailForCurrentContext($request, (int) $participant->getKey());
        if ($detail === null) {
            abort(404);
        }

        $row = is_array($detail['participant'] ?? null) ? $detail['participant'] : [];
        $title = trim((string) ($row['full_name'] ?? 'Profil Peserta')) ?: 'Profil Peserta';

        return response()->json([
            'title' => $title,
            'html' => view('discipleship.msk-participants.profile', [
                'profile' => is_array($detail['profile'] ?? null) ? $detail['profile'] : [],
            ])->render(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function updateBridgeStatus(
        UpdateSpiritualJourneyBridgeStatusRequest $request,
        Person $participant,
        SpiritualJourneyBridgeStatusService $service,
    ): RedirectResponse {
        return $this->updateStatusAndRedirect($service, (int) $participant->getKey(), $request->status());
    }

    public function updateBridgeStatusFromForm(
        UpdateSpiritualJourneyBridgeStatusRequest $request,
        SpiritualJourneyBridgeStatusService $service,
    ): RedirectResponse {
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

    private function tabBranchId(Request $request, CurrentDiscipleshipScope $scope): int|string|null
    {
        if (! $request->query->has('branch_id') && ! $request->query->has('rekap_cabang')) {
            return null;
        }

        return $scope->includesAllBranches()
            ? 'all'
            : $scope->selectedBranchId();
    }
}
