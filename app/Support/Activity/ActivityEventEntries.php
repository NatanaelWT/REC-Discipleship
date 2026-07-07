<?php

namespace App\Support\Activity;

use App\Models\ActivityEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class ActivityEventEntries
{
    /** @param Collection<int, ActivityEvent> $events */
    public function __construct(private readonly Collection $events) {}

    public function where(string $key, mixed $operator = null, mixed $value = null): self
    {
        $expected = func_num_args() >= 3 ? $value : $operator;

        return new self($this->events->filter(
            static fn (ActivityEvent $event): bool => data_get($event, $key) === $expected,
        )->values());
    }

    public function exists(): bool
    {
        return $this->events->isNotEmpty();
    }

    public function first(): ?ActivityEvent
    {
        return $this->events->first();
    }

    public function firstOrFail(): ActivityEvent
    {
        $event = $this->first();
        if ($event instanceof ActivityEvent) {
            return $event;
        }

        throw (new ModelNotFoundException)->setModel(ActivityEvent::class);
    }

    /** @return Collection<int, ActivityEvent> */
    public function get(): Collection
    {
        return $this->events;
    }

    public function count(): int
    {
        return $this->events->count();
    }
}
