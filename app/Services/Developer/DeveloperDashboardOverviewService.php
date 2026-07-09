<?php

namespace App\Services\Developer;

use App\Enums\UserAccessRole;
use App\Models\ActivityRequest;
use App\Models\Branch;
use App\Models\User;
use App\Models\WebsitePageView;
use App\Services\Analytics\WebsiteAnalyticsSessionMetrics;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DeveloperDashboardOverviewService
{
    public function __construct(
        private readonly WebsiteAnalyticsSessionMetrics $sessionMetrics,
    ) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $health = $this->healthMetrics();
        $publicAnalytics = $this->publicAnalytics();
        $attentionItems = $this->attentionItems();

        return [
            'header_stats' => [
                ['label' => 'Status Sistem', 'value' => count($attentionItems) > 0 ? 'Perlu Dicek' : 'Stabil'],
                ['label' => 'Request 24 Jam', 'value' => $this->formatNumber((int) ($health['raw']['requests'] ?? 0))],
                ['label' => 'Error 5xx', 'value' => $this->formatNumber((int) ($health['raw']['errors'] ?? 0))],
                ['label' => 'Pengunjung 7 Hari', 'value' => $this->formatNumber((int) ($publicAnalytics['summary']['visitors'] ?? 0))],
            ],
            'health_metrics' => $health,
            'public_analytics' => $publicAnalytics,
            'recent_activities' => $this->recentActivities(),
            'attention_items' => $attentionItems,
            'access_snapshot' => $this->accessSnapshot(),
        ];
    }

    /** @return array{status_label:string,status_tone:string,metrics:array<int, array<string, mixed>>,raw:array<string, int|float>} */
    private function healthMetrics(): array
    {
        $raw = [
            'requests' => 0,
            'errors' => 0,
            'average_response_ms' => 0.0,
            'events' => 0,
        ];

        if (Schema::hasTable('aktivitas')) {
            try {
                $from = CarbonImmutable::now('UTC')->subDay();
                $base = ActivityRequest::query()->where('started_at', '>=', $from);
                $raw['requests'] = (clone $base)->count();
                $raw['errors'] = (clone $base)
                    ->where(static function (Builder $query): void {
                        $query->where('http_status', '>=', 500)
                            ->orWhere('outcome', 'error');
                    })
                    ->count();
                $raw['average_response_ms'] = round((float) ((clone $base)->avg('duration_ms') ?? 0), 1);
            } catch (Throwable) {
                $raw['requests'] = 0;
                $raw['errors'] = 0;
                $raw['average_response_ms'] = 0.0;
            }
        }

        if (Schema::hasTable('aktivitas')) {
            try {
                $raw['events'] = (int) ActivityRequest::query()
                    ->where('started_at', '>=', CarbonImmutable::now('UTC')->subDay())
                    ->sum('events_count');
            } catch (Throwable) {
                $raw['events'] = 0;
            }
        }

        return [
            'status_label' => (int) $raw['errors'] > 0 ? 'Perlu dicek' : 'Stabil',
            'status_tone' => (int) $raw['errors'] > 0 ? 'is-warning' : 'is-online',
            'metrics' => [
                ['label' => 'Total Request', 'value' => $this->formatNumber((int) $raw['requests']), 'note' => 'Semua request 24 jam terakhir', 'tone' => 'is-blue', 'icon' => 'activities'],
                ['label' => 'Error 5xx', 'value' => $this->formatNumber((int) $raw['errors']), 'note' => 'Request gagal atau outcome error', 'tone' => 'is-amber', 'icon' => 'config'],
                ['label' => 'Rata-rata Respons', 'value' => number_format((float) $raw['average_response_ms'], 1, ',', '.').' ms', 'note' => 'Durasi server, bukan waktu baca', 'tone' => 'is-teal', 'icon' => 'statistics'],
                ['label' => 'Event Audit', 'value' => $this->formatNumber((int) $raw['events']), 'note' => 'Perubahan data tercatat', 'tone' => 'is-violet', 'icon' => 'dashboard'],
            ],
            'raw' => $raw,
        ];
    }

    /** @return array{summary:array<string, int|float>,metrics:array<int, array<string, mixed>>,top_pages:array<int, array<string, mixed>>} */
    private function publicAnalytics(): array
    {
        $summary = [
            'page_views' => 0,
            'visitors' => 0,
            'sessions' => 0,
        ];
        $topPages = [];

        if (Schema::hasTable('aktivitas')) {
            try {
                $from = CarbonImmutable::now('UTC')->subDays(7);
                $human = $this->humanPageViews()->where('occurred_at', '>=', $from);
                $summary = [
                    'page_views' => (clone $human)->count(),
                    'visitors' => (clone $human)->distinct()->count('visitor_hash'),
                    'sessions' => $this->sessionMetrics->count(clone $human),
                ];
                $topPages = (clone $human)
                    ->selectRaw("COALESCE(route_name, '') AS item_key, COALESCE(path, '') AS item_path, COUNT(*) AS aggregate_count")
                    ->groupBy('route_name', 'path')
                    ->orderByDesc('aggregate_count')
                    ->limit(5)
                    ->get()
                    ->map(fn ($row): array => [
                        'label' => trim((string) $row->item_key) ?: Str::limit(trim((string) $row->item_path), 80),
                        'path' => trim((string) $row->item_path),
                        'count' => (int) $row->aggregate_count,
                    ])
                    ->all();
            } catch (Throwable) {
                $summary = ['page_views' => 0, 'visitors' => 0, 'sessions' => 0];
                $topPages = [];
            }
        }

        return [
            'summary' => $summary,
            'metrics' => [
                ['label' => 'Page View', 'value' => $this->formatNumber((int) $summary['page_views']), 'note' => 'Kunjungan manusia 7 hari'],
                ['label' => 'Pengunjung', 'value' => $this->formatNumber((int) $summary['visitors']), 'note' => 'Visitor unik'],
                ['label' => 'Sesi', 'value' => $this->formatNumber((int) $summary['sessions']), 'note' => 'Sesi publik/login'],
            ],
            'top_pages' => $topPages,
        ];
    }

    private function humanPageViews(): Builder
    {
        return WebsitePageView::query()
            ->whereNull('user_id')
            ->whereIn('segment', ['publik', 'login'])
            ->where('is_bot', false)
            ->where('is_prefetch', false);
    }

    /** @return array<int, array<string, mixed>> */
    private function recentActivities(): array
    {
        if (! Schema::hasTable('aktivitas')) {
            return [];
        }

        try {
            $query = ActivityRequest::query()
                ->where(static function (Builder $roles): void {
                    $roles->whereNull('role')
                        ->orWhere('role', '!=', UserAccessRole::Developer->value);
                });
            $this->withoutTechnicalPageViews($query);

            return $query
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn (ActivityRequest $activity): array => $this->activityRow($activity))
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function attentionItems(): array
    {
        if (! Schema::hasTable('aktivitas')) {
            return [];
        }

        try {
            $query = ActivityRequest::query()
                ->where(static function (Builder $builder): void {
                    $builder->where('http_status', '>=', 500)
                        ->orWhere('outcome', 'error');
                });
            $this->withoutTechnicalPageViews($query);

            return $query
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (ActivityRequest $activity): array => $this->activityRow($activity))
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function activityRow(ActivityRequest $activity): array
    {
        $username = trim((string) ($activity->username ?? ''));
        $actorType = trim((string) ($activity->actor_type ?? 'anonymous'));
        $path = trim((string) ($activity->path ?? ''));
        $status = $activity->http_status !== null ? (string) $activity->http_status : trim((string) ($activity->outcome ?? ''));

        return [
            'id' => (string) $activity->getKey(),
            'actor' => $username !== '' ? $username : ($actorType === 'user' ? 'User' : 'Anonim'),
            'method' => strtoupper(trim((string) ($activity->method ?? 'GET'))),
            'path' => Str::limit($path !== '' ? $path : '-', 90),
            'status' => $status !== '' ? $status : '-',
            'outcome' => trim((string) ($activity->outcome ?? '')),
            'events_count' => (int) ($activity->events_count ?? 0),
            'started_at' => $this->dateLabel($activity->started_at),
            'detail_url' => route('developer.activities.show', ['activityRequest' => $activity->getKey()]),
        ];
    }

    private function withoutTechnicalPageViews(Builder $query): void
    {
        $query->where(static function (Builder $builder): void {
            $builder->where('is_page_view', false)
                ->orWhereNull('is_page_view')
                ->orWhere(static function (Builder $pageView): void {
                    $pageView->where('is_page_view', true)
                        ->where('is_bot', false)
                        ->where('is_prefetch', false);
                });
        });
    }

    /** @return array{metrics:array<int, array<string, mixed>>,raw:array<string, int>} */
    private function accessSnapshot(): array
    {
        $raw = [
            'users' => $this->safeCount(User::class),
            'active_users' => $this->safeUserCount(true),
            'active_developers' => $this->safeActiveDeveloperCount(),
            'branches' => $this->safeActiveDiscipleshipBranchCount(),
        ];

        return [
            'metrics' => [
                ['label' => 'Total User', 'value' => $this->formatNumber($raw['users']), 'note' => 'Semua akun terdaftar', 'tone' => 'is-teal', 'icon' => 'users'],
                ['label' => 'User Aktif', 'value' => $this->formatNumber($raw['active_users']), 'note' => 'Akun yang dapat masuk', 'tone' => 'is-blue', 'icon' => 'users'],
                ['label' => 'Developer Aktif', 'value' => $this->formatNumber($raw['active_developers']), 'note' => 'Pengelola sistem', 'tone' => 'is-amber', 'icon' => 'config'],
                ['label' => 'Cabang Pemuridan', 'value' => $this->formatNumber($raw['branches']), 'note' => 'Cabang aktif terpantau', 'tone' => 'is-violet', 'icon' => 'statistics'],
            ],
            'raw' => $raw,
        ];
    }

    /** @param class-string $model */
    private function safeCount(string $model): int
    {
        try {
            return $model::query()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeUserCount(bool $active): int
    {
        try {
            return User::query()->where('is_active', $active)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeActiveDeveloperCount(): int
    {
        try {
            return User::query()
                ->where('access_scope', UserAccessRole::Developer->value)
                ->where('is_active', true)
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeActiveDiscipleshipBranchCount(): int
    {
        try {
            $query = Branch::query()
                ->where('is_active', true)
                ->where('label', '!=', 'Pusat');
            if (Schema::hasColumn('cabang', 'is_developer_only')) {
                $query->where('is_developer_only', false);
            }

            return $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function dateLabel(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone($this->timezone())->format('d M H:i');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '-';
        }

        try {
            return CarbonImmutable::parse($raw, 'UTC')->setTimezone($this->timezone())->format('d M H:i');
        } catch (Throwable) {
            return $raw;
        }
    }

    private function formatNumber(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function timezone(): DateTimeZone
    {
        if (function_exists('app_timezone')) {
            return app_timezone();
        }

        return new DateTimeZone((string) config('app.timezone', 'Asia/Jakarta'));
    }
}
