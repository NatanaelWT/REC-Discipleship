<?php

namespace App\Services\Activity;

use Closure;

class ActivityContext
{
    private ?string $requestId = null;

    private int $modelEventSuppressionDepth = 0;

    private bool $auditFailure = false;

    /** @var array<int, Closure(): void> */
    private array $rollbackCallbacks = [];

    /** @var array<int, Closure(): void> */
    private array $commitCallbacks = [];

    /** @var array<int, array<string, mixed>> */
    private array $auditEvents = [];

    public function activate(string $requestId): void
    {
        $this->requestId = $requestId;
        $this->auditFailure = false;
        $this->auditEvents = [];
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function active(): bool
    {
        return $this->requestId !== null;
    }

    public function markAuditFailure(): void
    {
        $this->auditFailure = true;
    }

    public function hasAuditFailure(): bool
    {
        return $this->auditFailure;
    }

    /** @param array<string, mixed> $event */
    public function queueAuditEvent(array $event): void
    {
        $this->auditEvents[] = $event;
    }

    /** @return array<int, array<string, mixed>> */
    public function auditEvents(): array
    {
        return $this->auditEvents;
    }

    public function auditEventCount(): int
    {
        return count($this->auditEvents);
    }

    public function discardAuditEvents(): void
    {
        $this->auditEvents = [];
    }

    public function modelEventsSuppressed(): bool
    {
        return $this->modelEventSuppressionDepth > 0;
    }

    public function withoutModelEvents(Closure $callback): mixed
    {
        $this->modelEventSuppressionDepth++;

        try {
            return $callback();
        } finally {
            $this->modelEventSuppressionDepth--;
        }
    }

    public function onRollback(Closure $callback): void
    {
        $this->rollbackCallbacks[] = $callback;
    }

    public function runRollbackCallbacks(): void
    {
        foreach (array_reverse($this->rollbackCallbacks) as $callback) {
            try {
                $callback();
            } catch (\Throwable) {
                // The database rollback must continue even if filesystem cleanup fails.
            }
        }

        $this->rollbackCallbacks = [];
        $this->commitCallbacks = [];
    }

    public function onCommit(Closure $callback): void
    {
        $this->commitCallbacks[] = $callback;
    }

    public function runCommitCallbacks(): void
    {
        foreach ($this->commitCallbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable) {
                // The request has committed; deferred filesystem cleanup is best effort.
            }
        }

        $this->commitCallbacks = [];
        $this->rollbackCallbacks = [];
    }

    public function clear(): void
    {
        $this->requestId = null;
        $this->modelEventSuppressionDepth = 0;
        $this->auditFailure = false;
        $this->rollbackCallbacks = [];
        $this->commitCallbacks = [];
        $this->auditEvents = [];
    }
}
