<?php

namespace App\Services\Analytics;

use App\Models\WebsitePageView;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class WebsiteStatisticsService
{
    public function __construct(
        private readonly WebsiteAnalyticsSessionMetrics $sessionMetrics,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(Request $request): array
    {
        $filters = $this->filters($request);
        $cacheKey = 'analytics.dashboard.v6.'.sha1(json_encode($filters) ?: '[]');

        if (app()->environment('testing') && ! (bool) config('analytics.cache_in_testing', false)) {
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
        if ($this->rollupsEnabled($filters)) {
            return $this->buildWithRollups($filters);
        }

        $human = $this->query($filters)->where('is_bot', false)->where('is_prefetch', false);
        $summary = $this->summary(clone $human, $filters);
        $comparison = $this->comparison($filters, $summary);
        $timezone = $this->timezone();
        $trend = array_fill_keys($this->dateKeys($filters), 0);
        foreach ($this->trendCounts(clone $human) as $date => $count) {
            if (array_key_exists($date, $trend)) {
                $trend[$date] += $count;
            }
        }
        $accessHours = $this->accessHourRows(clone $human);

        $optionQuery = WebsitePageView::query()
            ->whereNull('user_id')
            ->whereIn('segment', ['publik', 'login'])
            ->whereBetween('occurred_at', [$filters['from_utc'], $filters['to_utc']])
            ->where('is_bot', false)
            ->where('is_prefetch', false);

        return [
            'filters' => $filters,
            'summary' => $summary,
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

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function buildWithRollups(array $filters): array
    {
        // Detailed visitor/session drill-down intentionally remains limited to retained raw rows.
        $retainedHuman = $this->query($filters)->where('is_bot', false)->where('is_prefetch', false);
        $currentHuman = $this->currentDayQuery($filters)->where('is_bot', false)->where('is_prefetch', false);
        $summary = $this->rollupSummary($filters, clone $retainedHuman, clone $currentHuman);
        $comparison = $this->comparison($filters, $summary);
        $timezone = $this->timezone();

        $trend = array_fill_keys($this->dateKeys($filters), 0);
        $rollupTrend = $this->summaryRollupQuery($filters)
            ->selectRaw('activity_date, SUM(human_page_views) AS aggregate_count')
            ->groupBy('activity_date')
            ->get();
        foreach ($rollupTrend as $row) {
            $date = (string) $row->activity_date;
            if (array_key_exists($date, $trend)) {
                $trend[$date] += (int) $row->aggregate_count;
            }
        }
        foreach ($this->trendCounts(clone $currentHuman) as $date => $count) {
            if (array_key_exists($date, $trend)) {
                $trend[$date] += $count;
            }
        }

        $accessHours = $this->accessHourRows(clone $retainedHuman);

        $optionQuery = WebsitePageView::query()
            ->whereNull('user_id')
            ->whereIn('segment', ['publik', 'login'])
            ->whereBetween('occurred_at', [$filters['from_utc'], $filters['to_utc']])
            ->where('is_bot', false)
            ->where('is_prefetch', false);
        $rollupOptions = $this->rollupQuery($filters);
        $languageOptions = collect((clone $optionQuery)
            ->whereNotNull('language_code')
            ->select(['language_code', 'language_name'])
            ->distinct()
            ->get()
            ->map(static fn ($row): array => [
                'language_code' => (string) $row->language_code,
                'language_name' => (string) ($row->language_name ?: $row->language_code),
            ])
            ->all())
            ->merge(
                (clone $rollupOptions)->where('language_code', '!=', '')->distinct()->pluck('language_code')
                    ->map(static fn (string $code): array => ['language_code' => $code, 'language_name' => $code]),
            )
            ->unique('language_code')
            ->sortBy('language_name')
            ->values();
        $routeOptions = (clone $optionQuery)->whereNotNull('route_name')->distinct()->pluck('route_name')
            ->merge((clone $rollupOptions)->where('route_name', '!=', '')->distinct()->pluck('route_name'))
            ->unique()->sort()->values();

        return [
            'filters' => $filters,
            'summary' => $summary,
            'comparison' => $comparison,
            'trend' => collect($trend)->map(static fn (int $count, string $date): array => [
                'date' => $date,
                'label' => CarbonImmutable::parse($date, $timezone)->format('d M'),
                'count' => $count,
            ])->values()->all(),
            'topPages' => $this->combinedGrouped($filters, clone $currentHuman, 'route_name', 'path', 10),
            'languages' => $this->combinedGrouped($filters, clone $currentHuman, 'language_code', 'language_code', 15),
            'accessHours' => $accessHours,
            'devices' => $this->combinedGrouped($filters, clone $currentHuman, 'device_type', 'device_type', 10),
            'browsers' => $this->grouped(clone $retainedHuman, 'browser_name', 'browser_name', 10),
            'operatingSystems' => $this->grouped(clone $retainedHuman, 'os_name', 'os_name', 10),
            'referrers' => $this->grouped(clone $retainedHuman, 'referer_host', 'referer_host', 10),
            'visitors' => $this->visitors(clone $retainedHuman),
            'options' => ['languages' => $languageOptions, 'routes' => $routeOptions],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, int|float> */
    private function rollupSummary(array $filters, Builder $retainedHuman, Builder $currentHuman): array
    {
        $rollup = $this->summaryRollupQuery($filters)
            ->selectRaw('COALESCE(SUM(human_page_views), 0) AS page_views')
            ->selectRaw('COALESCE(SUM(human_unique_visitors), 0) AS visitors')
            ->selectRaw('COALESCE(SUM(bot_views), 0) AS bot_views')
            ->selectRaw('COALESCE(SUM(human_total_response_ms), 0) AS response_total')
            ->first();
        $rawPageViews = (clone $currentHuman)->count();
        $pageViews = (int) ($rollup->page_views ?? 0) + $rawPageViews;
        $visitors = (int) ($rollup->visitors ?? 0) + (clone $currentHuman)->distinct()->count('visitor_hash');
        $sessions = $this->sessionMetrics->count(clone $retainedHuman);
        $active = (clone $retainedHuman)
            ->where('occurred_at', '>=', CarbonImmutable::now('UTC')->subMinutes(5))
            ->distinct()
            ->count('visitor_hash');
        $botViews = (int) ($rollup->bot_views ?? 0) + $this->currentDayQuery($filters)->where('is_bot', true)->count();
        $responseTotal = (float) ($rollup->response_total ?? 0)
            + (float) ((clone $currentHuman)->sum('response_ms') ?? 0);

        return [
            'page_views' => $pageViews,
            // Daily anonymous uniqueness is intentionally additive after raw rows expire.
            'visitors' => $visitors,
            'sessions' => $sessions,
            'pages_per_session' => $sessions > 0 ? round($pageViews / $sessions, 2) : 0.0,
            'active_now' => $active,
            'bot_views' => $botViews,
            'average_response_ms' => $pageViews > 0 ? round($responseTotal / $pageViews, 1) : 0.0,
        ];
    }

    /** @return array<int, array{key:string,label:string,count:int,visitors:int}> */
    private function combinedGrouped(array $filters, Builder $currentHuman, string $keyColumn, string $labelColumn, int $limit): array
    {
        $rollupRows = $this->rollupQuery($filters)
            ->selectRaw("COALESCE({$keyColumn}, '') AS item_key, COALESCE({$labelColumn}, '') AS item_label, SUM(human_page_views) AS aggregate_count, SUM(human_unique_visitors) AS aggregate_visitors")
            ->groupBy($keyColumn, $labelColumn)
            ->get()
            ->map(static fn ($row): array => [
                'key' => trim((string) $row->item_key),
                'label' => trim((string) $row->item_label) ?: 'Tidak diketahui',
                'count' => (int) $row->aggregate_count,
                'visitors' => (int) $row->aggregate_visitors,
            ]);
        $rawRows = collect($this->grouped($currentHuman, $keyColumn, $labelColumn, 250));

        return $rollupRows->merge($rawRows)
            ->groupBy(static fn (array $row): string => $row['key']."\x1f".$row['label'])
            ->map(static fn ($rows): array => [
                'key' => (string) $rows->first()['key'],
                'label' => (string) $rows->first()['label'],
                'count' => (int) $rows->sum('count'),
                'visitors' => (int) $rows->sum('visitors'),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $filters @return array<string, int|float> */
    private function summary(Builder $query, array $filters): array
    {
        $pageViews = (clone $query)->count();
        $sessions = $this->sessionMetrics->count(clone $query);
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
        $previous['from'] = $previous['from_utc']->setTimezone($this->timezone())->format('Y-m-d');
        $previous['to'] = $previous['to_utc']->setTimezone($this->timezone())->format('Y-m-d');
        $query = $this->query($previous)->where('is_bot', false)->where('is_prefetch', false);
        if ($this->rollupsEnabled($previous)) {
            $previousSummary = $this->rollupSummary(
                $previous,
                clone $query,
                $this->currentDayQuery($previous)->where('is_bot', false)->where('is_prefetch', false),
            );
            $previousValues = [
                'page_views' => (int) $previousSummary['page_views'],
                'visitors' => (int) $previousSummary['visitors'],
                'sessions' => (int) $previousSummary['sessions'],
            ];
        } else {
            $previousValues = [
                'page_views' => (clone $query)->count(),
                'visitors' => (clone $query)->distinct()->count('visitor_hash'),
                'sessions' => $this->sessionMetrics->count(clone $query),
            ];
        }

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

    /** @return array<string, int> */
    private function trendCounts(Builder $query): array
    {
        $expression = $this->localTimeExpression($query, 'date');

        return (clone $query)
            ->selectRaw($expression.' AS time_bucket, COUNT(*) AS aggregate_count')
            ->groupByRaw($expression)
            ->get()
            ->mapWithKeys(static fn ($row): array => [
                (string) $row->time_bucket => (int) $row->aggregate_count,
            ])
            ->all();
    }

    /** @return array<int, array{key:string,label:string,count:int,visitors:int}> */
    private function accessHourRows(Builder $query): array
    {
        $expression = $this->localTimeExpression($query, 'hour');

        return (clone $query)
            ->selectRaw($expression.' AS time_bucket, COUNT(*) AS aggregate_count, COUNT(DISTINCT visitor_hash) AS aggregate_visitors')
            ->groupByRaw($expression)
            ->orderByDesc('aggregate_count')
            ->orderBy('time_bucket')
            ->get()
            ->map(static function ($row): array {
                $hour = str_pad((string) $row->time_bucket, 2, '0', STR_PAD_LEFT);

                return [
                    'key' => $hour,
                    'label' => $hour.':00 - '.$hour.':59',
                    'count' => (int) $row->aggregate_count,
                    'visitors' => (int) $row->aggregate_visitors,
                ];
            })
            ->all();
    }

    private function localTimeExpression(Builder $query, string $bucket): string
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        $offset = $this->timezone()->getOffset(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $modifier = ($offset >= 0 ? '+' : '').$offset.' seconds';

        return match ($driver) {
            'sqlite' => $bucket === 'hour'
                ? "strftime('%H', datetime(occurred_at, '{$modifier}'))"
                : "strftime('%Y-%m-%d', datetime(occurred_at, '{$modifier}'))",
            'pgsql' => $bucket === 'hour'
                ? "TO_CHAR(occurred_at + INTERVAL '{$offset} seconds', 'HH24')"
                : "TO_CHAR(occurred_at + INTERVAL '{$offset} seconds', 'YYYY-MM-DD')",
            default => $bucket === 'hour'
                ? "DATE_FORMAT(TIMESTAMPADD(SECOND, {$offset}, occurred_at), '%H')"
                : "DATE_FORMAT(TIMESTAMPADD(SECOND, {$offset}, occurred_at), '%Y-%m-%d')",
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function visitors(Builder $query): array
    {
        $timezone = $this->timezone();
        $rows = (clone $query)
            ->selectRaw('visitor_hash, MAX(user_id) AS user_id, MAX(username) AS username, MAX(language_name) AS language_name, MAX(device_type) AS device_type, COUNT(*) AS page_views, MAX(occurred_at) AS last_seen_at')
            ->groupBy('visitor_hash')
            ->orderByDesc('page_views')
            ->limit(50)
            ->get();
        $visitorHashes = $rows->pluck('visitor_hash')
            ->filter(static fn (mixed $hash): bool => trim((string) $hash) !== '')
            ->map(static fn (mixed $hash): string => (string) $hash)
            ->values()
            ->all();
        $sessionsByVisitor = $visitorHashes !== []
            ? $this->sessionMetrics->countsByVisitor((clone $query)->whereIn('visitor_hash', $visitorHashes))
            : [];

        return $rows
            ->map(static fn ($row): array => [
                'visitor_hash' => (string) $row->visitor_hash,
                'label' => trim((string) $row->username) ?: 'Anonim #'.substr((string) $row->visitor_hash, 0, 8),
                'language' => trim((string) $row->language_name) ?: 'Tidak diketahui',
                'device' => trim((string) $row->device_type) ?: 'Tidak diketahui',
                'page_views' => (int) $row->page_views,
                'sessions' => (int) ($sessionsByVisitor[(string) $row->visitor_hash] ?? 0),
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

    /** @param array<string, mixed> $filters */
    private function currentDayQuery(array $filters): Builder
    {
        $query = $this->query($filters);
        $start = CarbonImmutable::now($this->timezone())->startOfDay()->utc();
        if ($filters['to_utc']->lessThan($start)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('occurred_at', '>=', $start);
    }

    /** @param array<string, mixed> $filters */
    private function rollupQuery(array $filters, string $scope = 'detail'): QueryBuilder
    {
        $query = DB::table('website_daily_rollups')->where('rollup_scope', $scope);
        $lastComplete = CarbonImmutable::now($this->timezone())->subDay()->format('Y-m-d');
        $from = (string) $filters['from'];
        $to = min((string) $filters['to'], $lastComplete);
        if ($to < $from) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereBetween('activity_date', [$from, $to])
            ->when($filters['segment'] !== '', fn (QueryBuilder $builder) => $builder->where('segment', $filters['segment']))
            ->when($filters['language'] !== '', fn (QueryBuilder $builder) => $builder->where('language_code', $filters['language']))
            ->when($filters['device'] !== '', fn (QueryBuilder $builder) => $builder->where('device_type', $filters['device']))
            ->when($filters['route'] !== '', fn (QueryBuilder $builder) => $builder->where('route_name', $filters['route']));
    }

    /** @param array<string, mixed> $filters */
    private function summaryRollupQuery(array $filters): QueryBuilder
    {
        $hasDetailedFilter = (string) $filters['language'] !== ''
            || (string) $filters['device'] !== ''
            || (string) $filters['route'] !== '';
        if ($hasDetailedFilter) {
            return $this->rollupQuery($filters, 'detail');
        }

        return $this->rollupQuery(
            $filters,
            (string) $filters['segment'] !== '' ? 'segment_summary' : 'summary',
        );
    }

    /** @param array<string, mixed> $filters */
    private function rollupsEnabled(array $filters): bool
    {
        return config('activity.storage', 'legacy') === 'split'
            && (string) ($filters['visitor'] ?? '') === '';
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
