<?php

namespace App\Services\Analytics;

use App\Models\ActivityEvent;
use App\Models\WebsitePageView;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WebsiteStatisticsService
{
    /** @return array<string, mixed> */
    public function dashboard(Request $request): array
    {
        $filters = $this->filters($request);
        $cacheKey = 'analytics.dashboard.v3.'.sha1(json_encode($filters) ?: '[]');

        if (app()->environment('testing')) {
            return $this->build($filters);
        }

        return Cache::remember(
            $cacheKey,
            max(1, (int) config('analytics.dashboard_cache_seconds', 60)),
            fn (): array => $this->build($filters),
        );
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function build(array $filters): array
    {
        $human = $this->query($filters)->where('is_bot', false)->where('is_prefetch', false);
        $summary = $this->summary(clone $human, $filters);
        $comparison = $this->comparison($filters, $summary);
        $timezone = $this->timezone();
        $timeRows = (clone $human)->orderBy('occurred_at')->cursor(['occurred_at', 'visitor_hash']);
        $trend = [];
        foreach ($this->dateKeys($filters) as $date) {
            $trend[$date] = 0;
        }
        $accessHours = [];
        $hourVisitors = [];
        foreach (range(0, 23) as $hour) {
            $key = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $accessHours[$key] = [
                'key' => $key,
                'label' => $key.':00 - '.$key.':59',
                'count' => 0,
                'visitors' => 0,
            ];
            $hourVisitors[$key] = [];
        }
        foreach ($timeRows as $row) {
            $date = $row->occurred_at?->setTimezone($timezone)->format('Y-m-d');
            if ($date !== null && array_key_exists($date, $trend)) {
                $trend[$date]++;
            }
            $hour = $row->occurred_at?->setTimezone($timezone)->format('H');
            if ($hour !== null && array_key_exists($hour, $accessHours)) {
                $accessHours[$hour]['count']++;
                $hourVisitors[$hour][(string) $row->visitor_hash] = true;
            }
        }
        foreach ($accessHours as $hour => $values) {
            $accessHours[$hour]['visitors'] = count($hourVisitors[$hour]);
        }
        $accessHours = collect($accessHours)
            ->filter(static fn (array $row): bool => $row['count'] > 0)
            ->sort(static fn (array $left, array $right): int => ($right['count'] <=> $left['count']) ?: ($left['key'] <=> $right['key']))
            ->values()
            ->all();

        $optionQuery = WebsitePageView::query()
            ->whereNull('user_id')
            ->whereIn('segment', ['publik', 'login'])
            ->whereBetween('occurred_at', [$filters['from_utc'], $filters['to_utc']])
            ->where('is_bot', false)
            ->where('is_prefetch', false);

        return [
            'filters' => $filters,
            'summary' => $summary,
            'loginAttempts' => $this->loginAttempts($filters),
            'comparison' => $comparison,
            'trend' => collect($trend)->map(static fn (int $count, string $date): array => [
                'date' => $date,
                'label' => CarbonImmutable::parse($date, $timezone)->format('d M'),
                'count' => $count,
            ])->values()->all(),
            'topPages' => $this->grouped(clone $human, 'route_name', 'path', 10),
            'languages' => $this->grouped(clone $human, 'language_code', 'language_name', 15),
            'accessHours' => $accessHours,
            'devices' => $this->grouped(clone $human, 'device_type', 'device_type', 10),
            'browsers' => $this->grouped(clone $human, 'browser_name', 'browser_name', 10),
            'operatingSystems' => $this->grouped(clone $human, 'os_name', 'os_name', 10),
            'referrers' => $this->grouped(clone $human, 'referer_host', 'referer_host', 10),
            'visitors' => $this->visitors(clone $human),
            'options' => [
                'languages' => (clone $optionQuery)->whereNotNull('language_code')->select(['language_code', 'language_name'])->distinct()->orderBy('language_name')->get(),
                'routes' => (clone $optionQuery)->whereNotNull('route_name')->distinct()->orderBy('route_name')->pluck('route_name'),
            ],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, int|float> */
    private function summary(Builder $query, array $filters): array
    {
        $pageViews = (clone $query)->count();
        $sessions = (clone $query)->distinct()->count('session_id');
        $visitors = (clone $query)->distinct()->count('visitor_hash');
        $activeThreshold = CarbonImmutable::now('UTC')->subMinutes(5);
        $active = (clone $query)->where('occurred_at', '>=', $activeThreshold)->distinct()->count('visitor_hash');
        $botQuery = $this->query($filters)->where('is_bot', true);

        return [
            'page_views' => $pageViews,
            'visitors' => $visitors,
            'sessions' => $sessions,
            'pages_per_session' => $sessions > 0 ? round($pageViews / $sessions, 2) : 0.0,
            'active_now' => $active,
            'bot_views' => $botQuery->count(),
            'average_response_ms' => round((float) ((clone $query)->avg('response_ms') ?? 0), 1),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, float|null> */
    private function comparison(array $filters, array $current): array
    {
        if ($filters['range'] === 'all') {
            return ['page_views' => null, 'visitors' => null, 'sessions' => null];
        }

        $seconds = max(1, $filters['from_utc']->diffInSeconds($filters['to_utc']) + 1);
        $previous = $filters;
        $previous['to_utc'] = $filters['from_utc']->subSecond();
        $previous['from_utc'] = $previous['to_utc']->subSeconds($seconds - 1);
        $query = $this->query($previous)->where('is_bot', false)->where('is_prefetch', false);
        $previousValues = [
            'page_views' => (clone $query)->count(),
            'visitors' => (clone $query)->distinct()->count('visitor_hash'),
            'sessions' => (clone $query)->distinct()->count('session_id'),
        ];

        return collect($previousValues)->mapWithKeys(static function (int $value, string $key) use ($current): array {
            if ($value === 0) {
                return [$key => (int) $current[$key] === 0 ? 0.0 : null];
            }

            return [$key => round((((int) $current[$key] - $value) / $value) * 100, 1)];
        })->all();
    }

    /** @return array<int, array{key:string,label:string,count:int,visitors:int}> */
    private function grouped(Builder $query, string $keyColumn, string $labelColumn, int $limit): array
    {
        return $query
            ->selectRaw("COALESCE({$keyColumn}, '') AS item_key, COALESCE({$labelColumn}, '') AS item_label, COUNT(*) AS aggregate_count, COUNT(DISTINCT visitor_hash) AS aggregate_visitors")
            ->groupBy($keyColumn, $labelColumn)
            ->orderByDesc('aggregate_count')
            ->limit($limit)
            ->get()
            ->map(static fn ($row): array => [
                'key' => trim((string) $row->item_key),
                'label' => trim((string) $row->item_label) ?: 'Tidak diketahui',
                'count' => (int) $row->aggregate_count,
                'visitors' => (int) $row->aggregate_visitors,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function visitors(Builder $query): array
    {
        $timezone = $this->timezone();

        return $query
            ->selectRaw('visitor_hash, MAX(user_id) AS user_id, MAX(username) AS username, MAX(language_name) AS language_name, MAX(device_type) AS device_type, COUNT(*) AS page_views, COUNT(DISTINCT session_id) AS sessions, MAX(occurred_at) AS last_seen_at')
            ->groupBy('visitor_hash')
            ->orderByDesc('page_views')
            ->limit(50)
            ->get()
            ->map(static fn ($row): array => [
                'visitor_hash' => (string) $row->visitor_hash,
                'label' => trim((string) $row->username) ?: 'Anonim #'.substr((string) $row->visitor_hash, 0, 8),
                'language' => trim((string) $row->language_name) ?: 'Tidak diketahui',
                'device' => trim((string) $row->device_type) ?: 'Tidak diketahui',
                'page_views' => (int) $row->page_views,
                'sessions' => (int) $row->sessions,
                'last_seen_at' => CarbonImmutable::parse((string) $row->last_seen_at, 'UTC')->setTimezone($timezone),
            ])->all();
    }

    /** @param array<string, mixed> $filters */
    private function query(array $filters): Builder
    {
        return WebsitePageView::query()
            ->whereNull('user_id')
            ->whereIn('segment', ['publik', 'login'])
            ->whereBetween('occurred_at', [$filters['from_utc'], $filters['to_utc']])
            ->when($filters['segment'] !== '', fn (Builder $query) => $query->where('segment', $filters['segment']))
            ->when($filters['language'] !== '', fn (Builder $query) => $query->where('language_code', $filters['language']))
            ->when($filters['device'] !== '', fn (Builder $query) => $query->where('device_type', $filters['device']))
            ->when($filters['route'] !== '', fn (Builder $query) => $query->where('route_name', $filters['route']))
            ->when($filters['visitor'] !== '', fn (Builder $query) => $query->where('visitor_hash', $filters['visitor']));
    }

    /** @param array<string, mixed> $filters @return array{total:int,succeeded:int,failed:int,locked:int} */
    private function loginAttempts(array $filters): array
    {
        $actions = [
            'auth.login.succeeded',
            'auth.login.failed',
            'auth.login.locked',
        ];

        if (! Schema::hasTable('activity_events')) {
            return ['total' => 0, 'succeeded' => 0, 'failed' => 0, 'locked' => 0];
        }

        $counts = ActivityEvent::query()
            ->whereBetween('occurred_at', [$filters['from_utc'], $filters['to_utc']])
            ->whereIn('action', $actions)
            ->selectRaw('action, COUNT(*) AS aggregate_count')
            ->groupBy('action')
            ->pluck('aggregate_count', 'action');

        $succeeded = (int) ($counts['auth.login.succeeded'] ?? 0);
        $failed = (int) ($counts['auth.login.failed'] ?? 0);
        $locked = (int) ($counts['auth.login.locked'] ?? 0);

        return [
            'total' => $succeeded + $failed + $locked,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'locked' => $locked,
        ];
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $range = strtolower(trim((string) $request->query('range', '30')));
        if (! in_array($range, ['today', '7', '30', '90', 'all', 'custom'], true)) {
            $range = '30';
        }
        $timezone = $this->timezone();
        $now = CarbonImmutable::now($timezone);
        $to = $now->endOfDay();
        $from = match ($range) {
            'today' => $now->startOfDay(),
            '7' => $now->subDays(6)->startOfDay(),
            '90' => $now->subDays(89)->startOfDay(),
            'all' => CarbonImmutable::create(2000, 1, 1, 0, 0, 0, $timezone),
            default => $now->subDays(29)->startOfDay(),
        };
        if ($range === 'custom') {
            try {
                $from = CarbonImmutable::parse((string) $request->query('from'), $timezone)->startOfDay();
                $to = CarbonImmutable::parse((string) $request->query('to'), $timezone)->endOfDay();
                if ($from->greaterThan($to)) {
                    [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
                }
            } catch (Throwable) {
                $range = '30';
                $from = $now->subDays(29)->startOfDay();
                $to = $now->endOfDay();
            }
        }

        return [
            'range' => $range,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'from_utc' => $from->utc(),
            'to_utc' => $to->utc(),
            'segment' => $this->oneOf($request, 'segment', ['publik', 'login']),
            'language' => $this->languageFilter($request),
            'device' => $this->oneOf($request, 'device', ['desktop', 'mobile', 'tablet', 'tv', 'console', 'other', 'unknown']),
            'route' => substr(trim((string) $request->query('route', '')), 0, 180),
            'visitor' => preg_match('/^[a-f0-9]{64}$/', (string) $request->query('visitor')) === 1 ? (string) $request->query('visitor') : '',
        ];
    }

    /** @param array<int, string> $allowed */
    private function oneOf(Request $request, string $key, array $allowed): string
    {
        $value = strtolower(trim((string) $request->query($key, '')));

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function languageFilter(Request $request): string
    {
        $value = substr(trim((string) $request->query('language', '')), 0, 20);

        return preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $value) === 1 ? $value : '';
    }

    /** @param array<string, mixed> $filters @return array<int, string> */
    private function dateKeys(array $filters): array
    {
        $timezone = $this->timezone();
        $from = $filters['from_utc']->setTimezone($timezone)->startOfDay();
        $to = $filters['to_utc']->setTimezone($timezone)->startOfDay();
        $days = max(1, min(366, $from->diffInDays($to) + 1));
        if ($filters['range'] === 'all' && $from->diffInDays($to) > 365) {
            $from = $to->subDays(365);
            $days = 366;
        }

        return collect(range(0, $days - 1))->map(static fn (int $day): string => $from->addDays($day)->format('Y-m-d'))->all();
    }

    private function timezone(): DateTimeZone
    {
        if (function_exists('app_timezone')) {
            return app_timezone();
        }

        return new DateTimeZone((string) config('app.timezone', 'Asia/Jakarta'));
    }
}
