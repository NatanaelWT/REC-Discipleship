<?php

namespace App\Http\Controllers\Developer;

use App\Enums\UserAccessRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityRequest;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class DeveloperActivityController extends Controller
{
    private const ACTIVITY_PAGE_SIZE = 100;

    public function index(Request $request): RedirectResponse|View
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $query = ActivityRequest::query();
        $this->applyFilters($query, $request);
        $activitiesPage = $this->activityPage($query, $request);

        return view('developer.activities.index', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_activities',
            'activities' => $activitiesPage['items'],
            'activityPagination' => $activitiesPage['pagination'],
            'filters' => $this->filterValues($request),
            'roleOptions' => collect(UserAccessRole::cases())->mapWithKeys(
                static fn (UserAccessRole $role): array => [$role->value => $role->label()],
            )->all(),
            'branchOptions' => Branch::query()->orderBy('label')->get(['id', 'label']),
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    /**
     * @return array{items:\Illuminate\Support\Collection<int, ActivityRequest>, pagination:array{count:int,newer_url:?string,older_url:?string}}
     */
    private function activityPage(Builder $query, Request $request): array
    {
        $baseQuery = clone $query;
        $cursor = $this->activityCursor($request);
        $direction = (string) ($cursor['direction'] ?? 'older');

        if ($cursor !== null && $direction === 'newer') {
            $this->applyNewerThan($query, $cursor['started_at'], $cursor['id']);
            $items = $query
                ->orderBy('started_at')
                ->orderBy('id')
                ->limit(self::ACTIVITY_PAGE_SIZE + 1)
                ->get();

            $hasMoreNewer = $items->count() > self::ACTIVITY_PAGE_SIZE;
            $items = $items->take(self::ACTIVITY_PAGE_SIZE)->reverse()->values();
            $first = $items->first();
            $last = $items->last();

            return [
                'items' => $items,
                'pagination' => [
                    'count' => $items->count(),
                    'newer_url' => $hasMoreNewer && $first instanceof ActivityRequest
                        ? $this->activityCursorUrl($request, $first, 'newer')
                        : null,
                    'older_url' => $last instanceof ActivityRequest && $this->hasOlderThan($baseQuery, $last)
                        ? $this->activityCursorUrl($request, $last, 'older')
                        : null,
                ],
            ];
        }

        if ($cursor !== null) {
            $this->applyOlderThan($query, $cursor['started_at'], $cursor['id']);
        }

        $items = $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_PAGE_SIZE + 1)
            ->get();

        $hasMoreOlder = $items->count() > self::ACTIVITY_PAGE_SIZE;
        $items = $items->take(self::ACTIVITY_PAGE_SIZE)->values();
        $first = $items->first();
        $last = $items->last();

        return [
            'items' => $items,
            'pagination' => [
                'count' => $items->count(),
                'newer_url' => $cursor !== null && $first instanceof ActivityRequest
                    ? $this->activityCursorUrl($request, $first, 'newer')
                    : null,
                'older_url' => $hasMoreOlder && $last instanceof ActivityRequest
                    ? $this->activityCursorUrl($request, $last, 'older')
                    : null,
            ],
        ];
    }

    /**
     * @return array{started_at:string,id:string,direction:string}|null
     */
    private function activityCursor(Request $request): ?array
    {
        $value = $this->queryString($request, 'activity_cursor');
        if ($value === '') {
            return null;
        }

        $encoded = strtr($value, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            return null;
        }

        $cursor = json_decode($decoded, true);
        if (! is_array($cursor)) {
            return null;
        }

        $startedAt = trim((string) ($cursor['started_at'] ?? ''));
        $id = trim((string) ($cursor['id'] ?? ''));
        $direction = trim((string) ($cursor['direction'] ?? 'older'));
        if ($startedAt === '' || $id === '' || ! in_array($direction, ['older', 'newer'], true)) {
            return null;
        }

        return [
            'started_at' => $startedAt,
            'id' => $id,
            'direction' => $direction,
        ];
    }

    private function activityCursorUrl(Request $request, ActivityRequest $activity, string $direction): string
    {
        $params = $request->query();
        unset($params['activity_cursor'], $params['cursor']);
        $params['activity_cursor'] = $this->encodeActivityCursor([
            'started_at' => $this->activityCursorStartedAt($activity),
            'id' => (string) $activity->getKey(),
            'direction' => $direction,
        ]);

        return route('developer.activities', $params);
    }

    /**
     * @param  array{started_at:string,id:string,direction:string}  $cursor
     */
    private function encodeActivityCursor(array $cursor): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($cursor)), '+/', '-_'), '=');
    }

    private function activityCursorStartedAt(ActivityRequest $activity): string
    {
        $raw = trim((string) $activity->getRawOriginal('started_at'));
        if ($raw !== '') {
            return $raw;
        }

        return $activity->started_at?->setTimezone('UTC')->format('Y-m-d H:i:s.u') ?? '';
    }

    private function hasOlderThan(Builder $baseQuery, ActivityRequest $activity): bool
    {
        $query = clone $baseQuery;
        $this->applyOlderThan($query, $this->activityCursorStartedAt($activity), (string) $activity->getKey());

        return $query->exists();
    }

    private function applyOlderThan(Builder $query, string $startedAt, string $id): void
    {
        $query->where(static function (Builder $nested) use ($startedAt, $id): void {
            $nested->where('started_at', '<', $startedAt)
                ->orWhere(static function (Builder $sameTime) use ($startedAt, $id): void {
                    $sameTime->where('started_at', $startedAt)
                        ->where('id', '<', $id);
                });
        });
    }

    private function applyNewerThan(Builder $query, string $startedAt, string $id): void
    {
        $query->where(static function (Builder $nested) use ($startedAt, $id): void {
            $nested->where('started_at', '>', $startedAt)
                ->orWhere(static function (Builder $sameTime) use ($startedAt, $id): void {
                    $sameTime->where('started_at', $startedAt)
                        ->where('id', '>', $id);
                });
        });
    }

    public function show(Request $request, ActivityRequest $activityRequest): RedirectResponse|View
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return view('developer.activities.show', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_activities',
            'activity' => $activityRequest,
            'backQuery' => trim((string) $request->query('back', '')),
        ]);
    }

    private function guard(Request $request): ?RedirectResponse
    {
        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }
        if (! can_manage_users()) {
            abort(403, 'Akses developer diperlukan.');
        }

        return null;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $exactFilters = [
            'actor_type' => 'actor',
            'username' => 'username',
            'branch_id' => 'branch_id',
            'route_name' => 'route',
            'method' => 'method',
            'outcome' => 'outcome',
            'http_status' => 'status',
            'ip_address' => 'ip',
        ];
        foreach ($exactFilters as $column => $input) {
            $value = $this->queryString($request, $input);
            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        $role = $this->queryString($request, 'role');
        if ($role !== '') {
            $query->where('role', $role);
        }
        if ($role !== UserAccessRole::Developer->value && $this->queryString($request, 'include_developer') !== '1') {
            $query->where(static function (Builder $roles): void {
                $roles->whereNull('role')
                    ->orWhere('role', '!=', UserAccessRole::Developer->value);
            });
        }

        $category = $this->queryString($request, 'category');
        if ($category !== '') {
            $query->where(function (Builder $nested) use ($category): void {
                $nested->where('category', $category);
                if ($this->splitStorage()) {
                    $this->orWhereEvent($nested, static fn ($event) => $event->where('category', $category));
                } else {
                    $nested->orWhereJsonContains('event_categories', $category);
                }
            });
        }

        $action = $this->queryString($request, 'action');
        if ($action !== '') {
            $query->where(function (Builder $nested) use ($action): void {
                $nested->where('action', $action);
                if ($this->splitStorage()) {
                    $this->orWhereEvent($nested, static fn ($event) => $event->where('action', $action));
                } else {
                    $nested->orWhereJsonContains('event_actions', $action);
                }
            });
        }

        $subjectType = $this->queryString($request, 'subject_type');
        $subjectId = $this->queryString($request, 'subject_id');
        if ($subjectType !== '' || $subjectId !== '') {
            $query->where(function (Builder $nested) use ($subjectType, $subjectId): void {
                $nested->where(static function (Builder $requestSubject) use ($subjectType, $subjectId): void {
                    if ($subjectType !== '') {
                        $requestSubject->where('subject_type', $subjectType);
                    }
                    if ($subjectId !== '') {
                        $requestSubject->where('subject_id', $subjectId);
                    }
                });
                if ($this->splitStorage()) {
                    $this->orWhereEvent($nested, static function ($event) use ($subjectType, $subjectId): void {
                        if ($subjectType !== '') {
                            $event->where('subject_type', $subjectType);
                        }
                        if ($subjectId !== '') {
                            $event->where('subject_id', $subjectId);
                        }
                    });
                } else {
                    $nested->orWhere(static function (Builder $eventSubjects) use ($subjectType, $subjectId): void {
                        if ($subjectType !== '') {
                            $eventSubjects->whereJsonContains('event_subject_types', $subjectType);
                        }
                        if ($subjectId !== '') {
                            $eventSubjects->whereJsonContains('event_subject_ids', $subjectId);
                        }
                    });
                }
            });
        }

        $from = $this->filterDate($this->queryString($request, 'from'), false);
        if ($from instanceof CarbonImmutable) {
            $query->where('started_at', '>=', $from);
        }
        $to = $this->filterDate($this->queryString($request, 'to'), true);
        if ($to instanceof CarbonImmutable) {
            $query->where('started_at', '<=', $to);
        }

        $search = $this->queryString($request, 'q');
        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $needle = '%'.addcslashes($search, '%_\\').'%';
                $nested->where('username', 'like', $needle)
                    ->orWhere('route_name', 'like', $needle)
                    ->orWhere('action', 'like', $needle)
                    ->orWhere('path', 'like', $needle)
                    ->orWhere('subject_id', 'like', $needle)
                    ->orWhere('ip_address', 'like', $needle);
                if ($this->splitStorage()) {
                    $this->orWhereEvent($nested, static function ($event) use ($needle): void {
                        $event->where('action', 'like', $needle)
                            ->orWhere('subject_type', 'like', $needle)
                            ->orWhere('subject_id', 'like', $needle)
                            ->orWhere('subject_label', 'like', $needle)
                            ->orWhere('description', 'like', $needle);
                    });
                } else {
                    $nested->orWhere('event_text', 'like', $needle);
                }
            });
        }
    }

    private function categoryOptions()
    {
        $categories = ActivityRequest::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        if ($this->splitStorage()) {
            $categories = $categories->merge(
                DB::table('audit_events')->whereNotNull('category')->distinct()->pluck('category'),
            );
        } else {
            $categories = $categories->merge(
                ActivityRequest::query()
                    ->whereNotNull('event_categories')
                    ->get(['event_categories'])
                    ->flatMap(static fn (ActivityRequest $activity) => is_array($activity->event_categories) ? $activity->event_categories : []),
            );
        }

        return $categories
            ->filter(static fn ($category): bool => trim((string) $category) !== '')
            ->unique()
            ->sort()
            ->values();
    }

    private function orWhereEvent(Builder $query, \Closure $callback): void
    {
        $query->orWhereExists(function ($event) use ($callback): void {
            $event->selectRaw('1')
                ->from('audit_events')
                ->whereColumn('audit_events.request_id', 'request_activities.id');
            $callback($event);
        });
    }

    private function splitStorage(): bool
    {
        return config('activity.storage', 'legacy') === 'split';
    }

    private function filterDate(string $value, bool $endOfDay): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value, app_timezone());
            $date = $endOfDay ? $date->endOfDay() : $date->startOfDay();

            return $date->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, string> */
    private function filterValues(Request $request): array
    {
        $values = [];
        foreach (['q', 'from', 'to', 'actor', 'username', 'role', 'branch_id', 'category', 'action', 'route', 'method', 'outcome', 'status', 'subject_type', 'subject_id', 'ip', 'include_developer'] as $key) {
            $value = $request->query($key, '');
            $values[$key] = is_scalar($value) ? trim((string) $value) : '';
        }
        $values['include_developer'] = $values['include_developer'] === '1' ? '1' : '';

        return $values;
    }

    private function queryString(Request $request, string $key): string
    {
        $value = $request->query($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
