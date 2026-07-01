<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Http\Requests\MskParticipants\DeactivateMskParticipantRequest;
use App\Http\Requests\MskParticipants\ExportMskParticipantsRequest;
use App\Http\Requests\MskParticipants\ImportMskParticipantsRequest;
use App\Http\Requests\MskParticipants\MskParticipantWriteRequest;
use App\Http\Requests\MskParticipants\ReactivateMskParticipantRequest;
use App\Http\Requests\MskParticipants\StoreMskParticipantRequest;
use App\Http\Requests\MskParticipants\UpdateMskParticipantRequest;
use App\Http\Requests\MskParticipants\UpdateMskParticipantSessionsRequest;
use App\Models\MskParticipant;
use App\Services\MskParticipants\MskParticipantExportService;
use App\Services\MskParticipants\MskParticipantImportService;
use App\Services\MskParticipants\MskParticipantPageData;
use App\Services\MskParticipants\MskParticipantWriter;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MskParticipantController extends Controller
{
    public function index(Request $request, MskParticipantPageData $pageData): RedirectResponse|Response
    {
        RuntimeBootstrap::boot($request);

        return response(view('discipleship.msk-participants.index', $pageData->forCurrentContext($request))->render());
    }

    public function rows(Request $request, MskParticipantPageData $pageData): JsonResponse
    {
        RuntimeBootstrap::boot($request);

        $data = $pageData->paginatedRowsForCurrentContext($request);
        $participants = is_array($data['participantsFilteredByBatch'] ?? null)
            ? $data['participantsFilteredByBatch']
            : [];

        return response()->json([
            'html' => view('discipleship.msk-participants.partials.rows', $data)->render(),
            'templates_html' => view('discipleship.msk-participants.partials.view-templates', [
                'participantsFilteredByBatch' => $participants,
                'participantProfiles' => $data['participantProfiles'] ?? [],
                'centralReadOnly' => (bool) ($data['centralReadOnly'] ?? false),
                'batchMonthFilterParam' => (string) ($data['batchMonthFilterParam'] ?? ''),
            ])->render(),
            'has_more' => (bool) ($data['hasMoreMskRows'] ?? false),
            'next_page' => $data['nextMskPage'] ?? null,
            'stats' => [
                'filter' => (string) ($data['batchMonthFilterLabel'] ?? '-'),
                'total' => (int) ($data['totalParticipantsFiltered'] ?? 0),
                'complete' => (int) ($data['completedParticipantsFiltered'] ?? 0),
                'progress' => (int) ($data['inProgressParticipantsFiltered'] ?? 0),
            ],
            'empty_message' => (string) ($data['mskEmptyMessage'] ?? 'Peserta tidak ditemukan.'),
        ]);
    }

    public function store(
        StoreMskParticipantRequest $request,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        return $this->saveParticipantFromRequest($request, $writer);
    }

    public function update(
        UpdateMskParticipantRequest $request,
        MskParticipant $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);
        $request->merge(['id' => $participant->getKey()]);

        return $this->saveParticipantFromRequest($request, $writer);
    }

    public function updateSessions(
        UpdateMskParticipantSessionsRequest $request,
        MskParticipant $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);

        $result = $writer->updateSessions($participant, $request->payload()['session_numbers']);
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', ['error' => 'invalid_msk_participant']);
        }

        $redirectParams = ['msk_session_saved' => 1];
        if ($result['auto_converted']) {
            $redirectParams['converted'] = 1;
        }

        return redirect()->route('discipleship.msk-classes', $redirectParams);
    }

    public function deactivate(
        DeactivateMskParticipantRequest $request,
        MskParticipant $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);
        $batchMonthParam = $this->batchMonthFilterParam($request);

        $result = $writer->setStatus($participant, 'inactive');
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', ['error' => 'invalid_msk_participant'] + $batchMonthParam);
        }

        return redirect()->route('discipleship.msk-classes', ['deleted' => 1] + $batchMonthParam);
    }

    public function reactivate(
        ReactivateMskParticipantRequest $request,
        MskParticipant $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        RuntimeBootstrap::boot($request);
        $batchMonthParam = $this->batchMonthFilterParam($request);

        $result = $writer->setStatus($participant, 'active');
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', ['error' => 'invalid_msk_participant'] + $batchMonthParam);
        }

        return redirect()->route('discipleship.msk-classes', ['reactivated' => 1] + $batchMonthParam);
    }

    public function import(ImportMskParticipantsRequest $request, MskParticipantImportService $importer): RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        $redirectParams = $importer->import($request);

        return redirect()->route('discipleship.msk-classes', $redirectParams);
    }

    public function export(ExportMskParticipantsRequest $request, MskParticipantExportService $exporter): BinaryFileResponse|RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        return $exporter->export($request);
    }

    private function saveParticipantFromRequest(MskParticipantWriteRequest $request, MskParticipantWriter $writer): RedirectResponse
    {
        $payload = $request->payload();
        $redirectParams = $this->baseRedirectParams(
            (int) ($payload['id'] ?? 0),
            (string) ($payload['return_batch_month'] ?? $payload['batch_month'] ?? ''),
        );

        if ($payload['full_name'] === '') {
            return redirect()->route('discipleship.msk-classes', $redirectParams + ['error' => 'missing_msk_name']);
        }

        if (($payload['batch_month_input'] ?? '') === '' || ($payload['batch_month'] ?? '') === '') {
            return redirect()->route('discipleship.msk-classes', $redirectParams + ['error' => 'invalid_msk_batch_month']);
        }

        if (($payload['birth_date_input'] ?? '') !== '' && ($payload['birth_date'] ?? '') === '') {
            return redirect()->route('discipleship.msk-classes', $redirectParams + ['error' => 'invalid_msk_birth_date']);
        }

        if (($payload['email'] ?? '') !== '' && filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->route('discipleship.msk-classes', $redirectParams + ['error' => 'invalid_msk_email']);
        }

        $result = $writer->save($request);
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', $redirectParams + ['error' => $result['error']]);
        }

        unset($redirectParams['edit']);
        $redirectParams['batch_month'] = $result['batch_month'] !== '' ? $result['batch_month'] : (string) ($payload['batch_month'] ?? '');
        $redirectParams['saved'] = 1;
        if ($result['auto_converted']) {
            $redirectParams['converted'] = 1;
        }

        return redirect()->route('discipleship.msk-classes', $redirectParams);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function baseRedirectParams(int $id, string $batchMonth): array
    {
        $params = [];
        if ($id > 0) {
            $params['edit'] = $id;
        }
        if (strtolower($batchMonth) === 'all') {
            $params['batch_month'] = 'all';
        } else {
            $batchMonth = import_normalize_month_strict($batchMonth);
            if ($batchMonth !== '') {
                $params['batch_month'] = $batchMonth;
            }
        }

        return $params;
    }

    /**
     * @return array<string, string>
     */
    private function batchMonthFilterParam(Request $request): array
    {
        $batchMonthInput = trim((string) $request->input('batch_month', ''));
        if ($batchMonthInput === '') {
            return [];
        }

        if (strtolower($batchMonthInput) === 'all') {
            return ['batch_month' => 'all'];
        }

        $batchMonth = import_normalize_month_strict($batchMonthInput);

        return $batchMonth !== '' ? ['batch_month' => $batchMonth] : [];
    }
}
