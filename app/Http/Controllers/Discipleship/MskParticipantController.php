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
use App\Models\Person;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use App\Services\MskParticipants\MskParticipantExportService;
use App\Services\MskParticipants\MskParticipantPageData;
use App\Services\MskParticipants\MskParticipantWriter;
use App\Services\MskParticipants\MskSynchronousImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
                'mskStoreAction' => route('discipleship.msk-classes.store', $this->currentBranchRouteParams()),
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
                ? route('discipleship.msk-classes', ['edit' => $participant->getKey()] + $this->currentBranchRouteParams())
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

    public function import(ImportMskParticipantsRequest $request, MskSynchronousImportService $imports): RedirectResponse|JsonResponse
    {
        try {
            $result = $imports->run($request);
        } catch (MskImportException $exception) {
            $request->attributes->set('discipleship.no_mutation', true);
            if ($request->expectsJson()) {
                $errors = is_array($exception->context['errors'] ?? null)
                    ? array_values($exception->context['errors'])
                    : [];

                return response()->json([
                    'status' => 'failed',
                    'error' => $exception->errorCode,
                    'errors' => $errors,
                    'context' => $exception->context,
                ], $this->importErrorStatus($exception->errorCode))->header('Cache-Control', 'private, no-store');
            }

            $redirect = ['error' => $exception->errorCode];
            $errors = is_array($exception->context['errors'] ?? null) ? $exception->context['errors'] : [];
            if ($errors !== []) {
                $first = is_array($errors[0] ?? null) ? $errors[0] : [];
                $redirect['import_error_count'] = max(count($errors), (int) ($exception->context['error_count'] ?? 0));
                $redirect['import_error_preview'] = $this->importErrorPreview($first);
            }

            return redirect()->route('discipleship.msk-classes', $redirect);
        }

        if ($result['no_op']) {
            $request->attributes->set('discipleship.no_mutation', true);
        }
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'completed',
                ...$result,
                'errors' => [],
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('discipleship.msk-classes', [
            'imported' => 1,
            'import_msk_inserted' => $result['inserted'],
            'import_msk_updated' => $result['updated'],
            'import_msk_unchanged' => $result['unchanged'],
        ]);
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

    /** @return array<string, int> */
    private function currentBranchRouteParams(): array
    {
        $branchId = current_user_branch_id();

        return $branchId !== null ? ['branch_id' => $branchId] : [];
    }

    /** @param array<string,mixed> $error */
    private function importErrorPreview(array $error): string
    {
        $row = max(0, (int) ($error['row'] ?? 0));
        $message = trim((string) ($error['message'] ?? $error['code'] ?? 'Import gagal divalidasi.'));
        $preview = $row > 0 ? 'Baris '.$row.': '.$message : $message;

        return mb_substr($preview, 0, 240);
    }

    private function importErrorStatus(string $errorCode): int
    {
        return match ($errorCode) {
            'import_in_progress' => 409,
            'import_zip_unavailable', 'import_lock_failed', 'import_timeout' => 503,
            'import_stage_failed', 'import_failed' => 500,
            default => 422,
        };
    }
}
