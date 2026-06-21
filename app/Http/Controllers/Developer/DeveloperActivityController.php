<?php

namespace App\Http\Controllers\Developer;

use App\Enums\UserAccessRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\ActivityRequest;
use App\Models\Branch;
use App\Support\RuntimeBootstrap;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DeveloperActivityController extends Controller
{
    public function index(Request $request): RedirectResponse|View
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $query = ActivityRequest::query()->withCount('events');
        $this->applyFilters($query, $request);

        return view('developer.activities.index', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_activities',
            'activities' => $query->orderByDesc('started_at')->orderByDesc('id')->cursorPaginate(100)->withQueryString(),
            'filters' => $this->filterValues($request),
            'roleOptions' => collect(UserAccessRole::cases())->mapWithKeys(
                static fn (UserAccessRole $role): array => [$role->value => $role->label()],
            )->all(),
            'branchOptions' => Branch::query()->orderBy('label')->get(['id', 'label']),
            'categoryOptions' => ActivityRequest::query()->whereNotNull('category')->distinct()->pluck('category')
                ->merge(ActivityEvent::query()->whereNotNull('category')->distinct()->pluck('category'))
                ->unique()->sort()->values(),
        ]);
    }

    public function show(Request $request, ActivityRequest $activityRequest): RedirectResponse|View
    {
        $guard = $this->guard($request);
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        $activityRequest->load('events');

        return view('developer.activities.show', [
            'settings' => ['church_name' => app_church_name()],
            'currentPage' => 'developer_activities',
            'activity' => $activityRequest,
            'backQuery' => trim((string) $request->query('back', '')),
        ]);
    }

    private function guard(Request $request): ?RedirectResponse
    {
        RuntimeBootstrap::boot($request);
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
            $query->where(static function (Builder $nested) use ($category): void {
                $nested->where('category', $category)
                    ->orWhereHas('events', static fn (Builder $events) => $events->where('category', $category));
            });
        }

        $action = $this->queryString($request, 'action');
        if ($action !== '') {
            $query->where(static function (Builder $nested) use ($action): void {
                $nested->where('action', $action)
                    ->orWhereHas('events', static fn (Builder $events) => $events->where('action', $action));
            });
        }

        $subjectType = $this->queryString($request, 'subject_type');
        $subjectId = $this->queryString($request, 'subject_id');
        if ($subjectType !== '' || $subjectId !== '') {
            $query->where(static function (Builder $nested) use ($subjectType, $subjectId): void {
                $nested->where(static function (Builder $requestSubject) use ($subjectType, $subjectId): void {
                    if ($subjectType !== '') {
                        $requestSubject->where('subject_type', $subjectType);
                    }
                    if ($subjectId !== '') {
                        $requestSubject->where('subject_id', $subjectId);
                    }
                })->orWhereHas('events', static function (Builder $events) use ($subjectType, $subjectId): void {
                    if ($subjectType !== '') {
                        $events->where('subject_type', $subjectType);
                    }
                    if ($subjectId !== '') {
                        $events->where('subject_id', $subjectId);
                    }
                });
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
            $query->where(static function (Builder $nested) use ($search): void {
                $needle = '%'.addcslashes($search, '%_\\').'%';
                $nested->where('username', 'like', $needle)
                    ->orWhere('route_name', 'like', $needle)
                    ->orWhere('action', 'like', $needle)
                    ->orWhere('path', 'like', $needle)
                    ->orWhere('subject_id', 'like', $needle)
                    ->orWhere('ip_address', 'like', $needle)
                    ->orWhereHas('events', static function (Builder $events) use ($needle): void {
                        $events->where('subject_label', 'like', $needle)
                            ->orWhere('description', 'like', $needle)
                            ->orWhere('action', 'like', $needle);
                    });
            });
        }
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
