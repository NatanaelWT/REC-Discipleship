<?php

namespace App\Services\Activity;

use App\Models\ActivityEvent;
use App\Models\ActivityRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class ActivityRecorder
{
    public function __construct(
        private readonly ActivityContext $context,
        private readonly SensitiveDataSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $category,
        string $action,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        ?string $subjectLabel = null,
        ?string $description = null,
        ?array $before = null,
        ?array $after = null,
        array $metadata = [],
    ): ?ActivityEvent {
        $requestId = $this->context->requestId();
        if ($requestId === null) {
            return null;
        }

        $cleanBefore = is_array($before) ? $this->sanitizer->sanitize($before) : null;
        $cleanAfter = is_array($after) ? $this->sanitizer->sanitize($after) : null;

        try {
            $entry = [
                'id' => (string) Str::ulid(),
                'request_id' => $requestId,
                'category' => trim($category) ?: 'data',
                'action' => trim($action) ?: 'changed',
                'subject_type' => $subjectType !== null ? trim($subjectType) : null,
                'subject_id' => $subjectId !== null ? (string) $subjectId : null,
                'subject_label' => $subjectLabel !== null ? trim($subjectLabel) : null,
                'description' => $description !== null ? trim($description) : null,
                'before_values' => $cleanBefore,
                'after_values' => $cleanAfter,
                'changed_values' => $this->changes($cleanBefore, $cleanAfter),
                'metadata' => $metadata !== [] ? $this->sanitizer->sanitize($metadata) : null,
                'occurred_at' => CarbonImmutable::now('UTC'),
            ];

            if ($this->splitStorage()) {
                $this->context->queueAuditEvent($entry);
                $event = new ActivityEvent($entry);
                $event->exists = false;

                return $event;
            }

            $activity = ActivityRequest::query()->find($requestId);
            if (! $activity instanceof ActivityRequest) {
                throw new \RuntimeException('Activity request tidak ditemukan untuk event audit.');
            }

            unset($entry['id']);

            return $activity->appendEventEntry($entry);
        } catch (Throwable $exception) {
            $this->context->markAuditFailure();
            throw $exception;
        }
    }

    /** Persist all events with one bulk insert inside the caller's transaction. */
    public function flush(): int
    {
        $events = $this->context->auditEvents();
        if (! $this->splitStorage() || $events === []) {
            return 0;
        }

        try {
            $rows = array_map(static function (array $event): array {
                $model = new ActivityEvent;
                $model->forceFill($event);

                return $model->getAttributes();
            }, $events);
            ActivityEvent::query()->insert($rows);
            $this->context->discardAuditEvents();

            return count($rows);
        } catch (Throwable $exception) {
            $this->context->markAuditFailure();
            throw $exception;
        }
    }

    public function recordModel(string $operation, Model $model): ?ActivityEvent
    {
        if (! $this->context->active() || $this->context->modelEventsSuppressed()) {
            return null;
        }

        $attributes = $model->getAttributes();
        $before = null;
        $after = null;
        if ($operation === 'created') {
            $after = $attributes;
        } elseif ($operation === 'deleted') {
            $before = $attributes;
        } else {
            $changedKeys = array_keys($model->getChanges());
            $before = array_intersect_key($model->getOriginal(), array_flip($changedKeys));
            $after = array_intersect_key($attributes, array_flip($changedKeys));
            if ($after === []) {
                return null;
            }
        }

        $table = $model->getTable();
        $label = $this->modelLabel($model);

        return $this->record(
            'data',
            $table.'.'.$operation,
            $table,
            $model->getKey(),
            $label,
            ucfirst($operation).' '.$table.($label !== null ? ': '.$label : ''),
            $before,
            $after,
        );
    }

    public function withoutModelEvents(\Closure $callback): mixed
    {
        return $this->context->withoutModelEvents($callback);
    }

    public function onRollback(\Closure $callback): void
    {
        if ($this->context->active()) {
            $this->context->onRollback($callback);
        }
    }

    public function onCommit(\Closure $callback): void
    {
        if ($this->context->active()) {
            $this->context->onCommit($callback);
        }
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function changes(?array $before, ?array $after): ?array
    {
        if ($before === null && $after === null) {
            return null;
        }

        $changes = [];
        foreach (array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? []))) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $changes[$key] = ['before' => $old, 'after' => $new];
            }
        }

        return $changes !== [] ? $changes : null;
    }

    private function modelLabel(Model $model): ?string
    {
        foreach (['username', 'full_name', 'name', 'title', 'month', 'key'] as $field) {
            $value = trim((string) ($model->getAttribute($field) ?? ''));
            if ($value !== '') {
                return function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255);
            }
        }

        return null;
    }

    private function splitStorage(): bool
    {
        return config('activity.storage', 'legacy') === 'split';
    }
}
