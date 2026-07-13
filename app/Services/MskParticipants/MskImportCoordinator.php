<?php

namespace App\Services\MskParticipants;

use App\Exceptions\MskImportException;
use App\Http\Requests\MskParticipants\ImportMskParticipantsRequest;
use App\Models\MskImportJob;
use App\Services\Mutation\MutationLifecycle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MskImportCoordinator
{
    public function __construct(
        private readonly MskImportSpreadsheetStager $stager,
        private readonly MutationLifecycle $lifecycle,
    ) {}

    public function start(ImportMskParticipantsRequest $request): MskImportJob
    {
        $userId = (int) Auth::id();
        $branchId = (int) current_user_branch_id();
        if ($userId < 1 || $branchId < 1) {
            throw new MskImportException('access_denied');
        }

        $token = trim((string) $request->input('idempotency_token'));
        if ($token !== '') {
            $existing = MskImportJob::query()
                ->where('user_id', $userId)
                ->where('branch_id', $branchId)
                ->where('idempotency_token', $token)
                ->first();
            if ($existing instanceof MskImportJob) {
                return $existing;
            }
        } else {
            $token = (string) Str::ulid();
        }
        if (strlen($token) > 100) {
            throw new MskImportException('import_invalid_token');
        }

        $file = $request->file('import_pemuridan_excel');
        if ($file === null) {
            throw new MskImportException('import_missing_file');
        }
        if (! $file->isValid()) {
            throw new MskImportException('import_upload_failed');
        }
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new MskImportException('import_invalid_file_type');
        }
        $size = (int) ($file->getSize() ?? 0);
        if ($size < 1 || $size > (int) config('msk_import.max_file_bytes', 10 * 1024 * 1024)) {
            throw new MskImportException('import_file_too_large', ['size_bytes' => $size]);
        }

        try {
            $job = MskImportJob::query()->create([
                'user_id' => $userId,
                'branch_id' => $branchId,
                'active_branch_id' => $branchId,
                'idempotency_token' => $token,
                'status' => 'pending',
                'source_name' => $file->getClientOriginalName(),
                'source_sha256' => hash_file('sha256', $file->getRealPath()),
                'source_size' => $size,
            ]);
        } catch (QueryException $exception) {
            $existing = MskImportJob::query()
                ->where('user_id', $userId)
                ->where('branch_id', $branchId)
                ->where('idempotency_token', $token)
                ->first();
            if ($existing instanceof MskImportJob) {
                return $existing;
            }
            if (MskImportJob::query()->where('active_branch_id', $branchId)->exists()) {
                throw new MskImportException('import_in_progress');
            }
            throw $exception;
        }

        $disk = Storage::disk((string) config('msk_import.disk', 'local'));
        $sourcePath = 'imports/msk/'.$job->getKey().'/source.xlsx';
        $stagedPath = 'imports/msk/'.$job->getKey().'/rows.jsonl';
        $this->lifecycle->onRollback(static fn () => $disk->delete([$sourcePath, $stagedPath]));
        try {
            if (! $disk->putFileAs(dirname($sourcePath), $file, basename($sourcePath))) {
                throw new MskImportException('import_upload_failed');
            }
            $job->source_path = $sourcePath;
            $job->save();
            $staged = $this->stager->stage($job, $disk->path($sourcePath));
            $job->forceFill([
                'staged_path' => $staged['errors'] === [] ? $staged['staged_path'] : null,
                'total_rows' => $staged['total_rows'],
                'errors' => $staged['errors'] !== [] ? $staged['errors'] : null,
                'status' => $staged['errors'] === [] ? 'pending' : 'failed',
                'active_branch_id' => $staged['errors'] === [] ? $branchId : null,
                'completed_at' => $staged['errors'] === [] ? null : now(),
            ])->save();
            if ($job->status === 'failed') {
                $disk->delete($sourcePath);
            }

            return $job->refresh();
        } catch (Throwable $exception) {
            $disk->delete([$sourcePath, $stagedPath]);
            $error = $exception instanceof MskImportException ? $exception : new MskImportException('import_invalid_excel');
            $job->forceFill([
                'status' => 'failed',
                'active_branch_id' => null,
                'errors' => [['code' => $error->errorCode, 'row' => 0, 'message' => $error->errorCode]],
                'completed_at' => now(),
            ])->save();
            throw $error;
        }
    }
}
