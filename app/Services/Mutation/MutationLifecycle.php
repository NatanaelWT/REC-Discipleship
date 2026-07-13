<?php

namespace App\Services\Mutation;

use Closure;
use Throwable;

class MutationLifecycle
{
    private bool $active = false;

    /** @var array<int, Closure(): void> */
    private array $rollbackCallbacks = [];

    /** @var array<int, Closure(): void> */
    private array $commitCallbacks = [];

    public function begin(): void
    {
        $this->active = true;
        $this->rollbackCallbacks = [];
        $this->commitCallbacks = [];
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function onRollback(Closure $callback): void
    {
        if ($this->active) {
            $this->rollbackCallbacks[] = $callback;
        }
    }

    public function onCommit(Closure $callback): void
    {
        if ($this->active) {
            $this->commitCallbacks[] = $callback;
        }
    }

    public function runRollbackCallbacks(): void
    {
        foreach (array_reverse($this->rollbackCallbacks) as $callback) {
            try {
                $callback();
            } catch (Throwable) {
                // Database rollback must not be interrupted by best-effort file cleanup.
            }
        }

        $this->clear();
    }

    public function runCommitCallbacks(): void
    {
        foreach ($this->commitCallbacks as $callback) {
            try {
                $callback();
            } catch (Throwable) {
                // The database is committed; deferred file cleanup remains best effort.
            }
        }

        $this->clear();
    }

    public function clear(): void
    {
        $this->active = false;
        $this->rollbackCallbacks = [];
        $this->commitCallbacks = [];
    }
}
