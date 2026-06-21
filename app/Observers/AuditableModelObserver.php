<?php

namespace App\Observers;

use App\Services\Activity\ActivityContext;
use App\Services\Activity\ActivityRecorder;
use Illuminate\Database\Eloquent\Model;

class AuditableModelObserver
{
    public function created(Model $model): void
    {
        $this->record('created', $model);
    }

    public function updated(Model $model): void
    {
        $this->record('updated', $model);
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model);
    }

    private function record(string $operation, Model $model): void
    {
        try {
            app(ActivityRecorder::class)->recordModel($operation, $model);
        } catch (\Throwable $exception) {
            app(ActivityContext::class)->markAuditFailure();
            throw $exception;
        }
    }
}
