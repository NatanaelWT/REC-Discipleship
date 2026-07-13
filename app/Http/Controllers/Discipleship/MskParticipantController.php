<?php

namespace App\Http\Controllers\Discipleship;

use App\Exceptions\MskImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MskParticipants\DeactivateMskParticipantRequest;
use App\Http\Requests\MskParticipants\ExportMskParticipantsRequest;
use App\Http\Requests\MskParticipants\ImportMskParticipantsRequest;
use App\Http\Requests\MskParticipants\MskParticipantWriteRequest;
use App\Http\Requests\MskParticipants\ReactivateMskParticipantRequest;
use App\Http\Requests\MskParticipants\StoreMskParticipantRequest;
use App\Http\Requests\MskParticipants\UpdateMskParticipantRequest;
use App\Http\Requests\MskParticipants\UpdateMskParticipantSessionsRequest;
use App\Models\MskImportJob;
use App\Models\Person;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\MskParticipants\MskImportBatchProcessor;
use App\Services\MskParticipants\MskImportCoordinator;
use App\Services\MskParticipants\MskParticipantExportService;
use App\Services\MskParticipants\MskParticipantPageData;
use App\Services\MskParticipants\MskParticipantWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MskParticipantController extends Controller
{
    public function index(
        Request $request,
        MskParticipantPageData $pageData,
        CurrentDiscipleshipScope $scope,
    ): RedirectResponse|Response|View {
        $pageTitle = 'Kelas MSK';
        $data = [
            ...$pageData->forCurrentContext($request),
            'pageTitle' => $pageTitle,
            'renderAsTabPanel' => true,
        ];

        if ($request->header('X-Discipleship-Fragment') === 'tab') {
            return response(view('discipleship.msk-participants.index', $data)->render());
        }

        return view('discipleship.journey.workspace', [
            ...$data,
            'activeTab' => 'msk',
            'currentPage' => 'msk_classes',
            'panelView' => 'discipleship.msk-participants.index',
            'tabBranchId' => $this->tabBranchId($request, $scope),
        ]);
    }

    public function rows(Request $request, MskParticipantPageData $pageData): JsonResponse
    {
        $data = $pageData->paginatedRowsForCurrentContext($request);
        $participants = is_array($data['participantsFilteredByBatch'] ?? null)
            ? $data['participantsFilteredByBatch']
            : [];

        return response()->json([
            'html' => view('discipleship.msk-participants.partials.rows', $data)->render(),
            'stats' => [
                'filter' => (string) ($data['batchMonthFilterLabel'] ?? '-'),
                'total' => (int) ($data['totalParticipantsFiltered'] ?? 0),
                'complete' => (int) ($data['completedParticipantsFiltered'] ?? 0),
                'progress' => (int) ($data['inProgressParticipantsFiltered'] ?? 0),
            ],
            'has_more' => (bool) ($data['hasMoreMskRows'] ?? false),
            'next_cursor' => $data['nextMskCursor'] ?? null,
            'empty' => count($participants) === 0,
            'empty_message' => (string) ($data['mskEmptyMessage'] ?? 'Peserta tidak ditemukan.'),
        ]);
    }

    public function detail(Request $request, Person $participant, MskParticipantPageData $pageData): JsonResponse
    {
        $detail = $pageData->detailForCurrentContext($request, (int) $participant->getKey());
        if ($detail === null) {
            abort(404);
        }

        $row = is_array($detail['participant'] ?? null) ? $detail['participant'] : [];
        $title = trim((string) ($row['full_name'] ?? 'Peserta MSK')) ?: 'Peserta MSK';
        $mode = $request->query('mode') === 'edit' ? 'edit' : 'view';
        if ($mode === 'edit') {
            abort_if((bool) ($detail['centralReadOnly'] ?? false), 403);
            $html = view('discipleship.msk-participants.partials.form', [
                'participant' => $row,
                'batchMonth' => (string) ($detail['batchMonthFilterParam'] ?? ''),
                'closeActionAttr' => 'data-msk-edit-close',
                'mskStoreAction' => route('discipleship.msk-classes.store'),
            ])->render();
            $title = 'Edit Peserta MSK: '.$title;
        } else {
            $html = view('discipleship.msk-participants.profile', [
                'profile' => is_array($detail['profile'] ?? null) ? $detail['profile'] : [],
            ])->render();
        }

        return response()->json([
            'title' => $title,
            'html' => $html,
            'edit_url' => ! (bool) ($detail['centralReadOnly'] ?? false)
                ? route('discipleship.msk-classes', ['edit' => $participant->getKey()])
                : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        StoreMskParticipantRequest $request,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        return $this->saveParticipantFromRequest($request, $writer);
    }

    public function update(
        UpdateMskParticipantRequest $request,
        Person $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        $request->merge(['id' => $participant->getKey()]);

        return $this->saveParticipantFromRequest($request, $writer);
    }

    public function updateSessions(
        UpdateMskParticipantSessionsRequest $request,
        Person $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
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
        Person $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        $batchMonthParam = $this->batchMonthFilterParam($request);

        $result = $writer->setStatus($participant, 'inactive');
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', ['error' => 'invalid_msk_participant'] + $batchMonthParam);
        }

        return redirect()->route('discipleship.msk-classes', ['deleted' => 1] + $batchMonthParam);
    }

    public function reactivate(
        ReactivateMskParticipantRequest $request,
        Person $participant,
        MskParticipantWriter $writer,
    ): RedirectResponse {
        $batchMonthParam = $this->batchMonthFilterParam($request);

        $result = $writer->setStatus($participant, 'active');
        if ($result['error'] !== '') {
            return redirect()->route('discipleship.msk-classes', ['error' => 'invalid_msk_participant'] + $batchMonthParam);
        }

        return redirect()->route('discipleship.msk-classes', ['reactivated' => 1] + $batchMonthParam);
    }

    public function import(ImportMskParticipantsRequest $request, MskImportCoordinator $imports): RedirectResponse|JsonResponse
    {
        try {
            $job = $imports->start($request);
        } catch (MskImportException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $exception->errorCode, 'context' => $exception->context], 422);
            }

            return redirect()->route('discipleship.msk-classes', ['error' => $exception->errorCode]);
        }

        $payload = $this->importJobPayload($job);
        $request->session()->put('msk_import_job_id', (string) $job->getKey());
        if ($request->expectsJson()) {
            return response()->json($payload, $job->isTerminal() ? 200 : 202)->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('discipleship.msk-classes', ['import_job' => $job->getKey()]);
    }

    public function importStatus(
        ImportMskParticipantsRequest $request,
        MskImportJob $importJob,
        MskImportBatchProcessor $processor,
    ): JsonResponse {
        $this->authorizeImportJob($importJob);

        $status = $processor->status($importJob->fresh());
        if ((bool) ($status['terminal'] ?? false)) {
            $request->session()->forget('msk_import_job_id');
        }

        return response()->json([
            ...$this->importJobPayload($importJob),
            ...$status,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function importBatch(
        ImportMskParticipantsRequest $request,
        MskImportJob $importJob,
        MskImportBatchProcessor $processor,
    ): JsonResponse {
        $this->authorizeImportJob($importJob);
        $validated = $request->validate([
            'batch_token' => ['required', 'string', 'max:100'],
        ]);
        $result = $processor->process($importJob, (string) $validated['batch_token']);
        if ((bool) ($result['terminal'] ?? false)) {
            $request->session()->forget('msk_import_job_id');
        }

        return response()->json([
            ...$this->importJobPayload($importJob),
            ...$result,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function export(ExportMskParticipantsRequest $request, MskParticipantExportService $exporter): BinaryFileResponse|RedirectResponse
    {
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

    private function tabBranchId(Request $request, CurrentDiscipleshipScope $scope): int|string|null
    {
        if (! $request->query->has('branch_id') && ! $request->query->has('rekap_cabang')) {
            return null;
        }

        return $scope->includesAllBranches()
            ? 'all'
            : $scope->selectedBranchId();
    }

    private function authorizeImportJob(MskImportJob $job): void
    {
        abort_unless((int) $job->user_id === (int) Auth::id()
            && (int) $job->branch_id === (int) current_user_branch_id(), 404);
    }

    /** @return array{id:string,status:string,status_url:string,batch_url:string} */
    private function importJobPayload(MskImportJob $job): array
    {
        return [
            'id' => (string) $job->getKey(),
            'status' => (string) $job->status,
            'status_url' => route('discipleship.msk-classes.import-status', ['importJob' => $job->getKey()]),
            'batch_url' => route('discipleship.msk-classes.import-batch', ['importJob' => $job->getKey()]),
        ];
    }
}
