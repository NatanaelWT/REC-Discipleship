<?php

namespace App\Models;

use App\Casts\UtcDateTimeCast;
use App\Support\Activity\ActivityEventEntries;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ActivityRequest extends Model
{
    use HasUlids;

    protected $table = 'aktivitas';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'query_data' => 'array',
            'input_data' => 'array',
            'event_entries' => 'array',
            'event_categories' => 'array',
            'event_actions' => 'array',
            'event_subject_types' => 'array',
            'event_subject_ids' => 'array',
            'started_at' => UtcDateTimeCast::class,
            'completed_at' => UtcDateTimeCast::class,
            'occurred_at' => UtcDateTimeCast::class,
            'duration_ms' => 'decimal:3',
            'response_ms' => 'decimal:3',
            'is_page_view' => 'boolean',
            'is_bot' => 'boolean',
            'is_prefetch' => 'boolean',
            'events_count' => 'integer',
        ];
    }

    public function events(): ActivityEventEntries
    {
        return new ActivityEventEntries($this->eventModels());
    }

    /** @return Collection<int, ActivityEvent> */
    public function getEventsAttribute(): Collection
    {
        return $this->eventModels();
    }

    /** @param array<string, mixed> $entry */
    public function appendEventEntry(array $entry): ActivityEvent
    {
        $entries = $this->normalizedEventEntries();
        $entry['id'] = $entry['id'] ?? (count($entries) + 1);
        $entry['request_id'] = (string) $this->getKey();
        $entries[] = $entry;

        $this->forceFill($this->eventSummary($entries))->save();

        return new ActivityEvent($entry);
    }

    public function hasPageView(): bool
    {
        return (bool) ($this->is_page_view ?? false);
    }

    /** @return Collection<int, ActivityEvent> */
    private function eventModels(): Collection
    {
        return collect($this->normalizedEventEntries())
            ->map(function (array $entry): ActivityEvent {
                $event = new ActivityEvent($entry);
                $event->exists = false;

                return $event;
            })
            ->values();
    }

    /** @return array<int, array<string, mixed>> */
    private function normalizedEventEntries(): array
    {
        $entries = $this->event_entries;
        if (! is_array($entries)) {
            return [];
        }

        return array_values(array_filter($entries, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function eventSummary(array $entries): array
    {
        $categories = [];
        $actions = [];
        $subjectTypes = [];
        $subjectIds = [];
        $textParts = [];

        foreach ($entries as $entry) {
            $category = trim((string) ($entry['category'] ?? ''));
            if ($category !== '') {
                $categories[$category] = $category;
            }
            $action = trim((string) ($entry['action'] ?? ''));
            if ($action !== '') {
                $actions[$action] = $action;
            }
            $subjectType = trim((string) ($entry['subject_type'] ?? ''));
            if ($subjectType !== '') {
                $subjectTypes[$subjectType] = $subjectType;
            }
            $subjectId = trim((string) ($entry['subject_id'] ?? ''));
            if ($subjectId !== '') {
                $subjectIds[$subjectId] = $subjectId;
            }

            foreach (['action', 'subject_type', 'subject_id', 'subject_label', 'description'] as $key) {
                $value = trim((string) ($entry[$key] ?? ''));
                if ($value !== '') {
                    $textParts[] = $value;
                }
            }
        }

        return [
            'event_entries' => array_values($entries),
            'events_count' => count($entries),
            'event_categories' => array_values($categories),
            'event_actions' => array_values($actions),
            'event_subject_types' => array_values($subjectTypes),
            'event_subject_ids' => array_values($subjectIds),
            'event_text' => trim(implode("\n", array_unique($textParts))) ?: null,
        ];
    }
}
