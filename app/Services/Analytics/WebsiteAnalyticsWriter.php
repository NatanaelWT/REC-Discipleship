<?php

namespace App\Services\Analytics;

use App\Models\ActivityRequest;
use App\Models\WebsitePageView;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WebsiteAnalyticsWriter
{
    public function __construct(
        private readonly WebsiteClientClassifier $clients,
    ) {}

    /** @param array{visitor_hash:string,identity_source:string} $identity */
    public function record(ActivityRequest $activity, array $identity, bool $prefetch = false, array $language = []): ?WebsitePageView
    {
        if (! $this->qualifies($activity)) {
            return null;
        }

        $occurredAt = $activity->started_at instanceof CarbonImmutable
            ? $activity->started_at
            : CarbonImmutable::parse((string) $activity->started_at, 'UTC');
        $client = $this->clients->classify($activity->user_agent);
        $language = array_merge(['language_code' => null, 'language_name' => null], $language);

        return DB::transaction(function () use ($activity, $identity, $prefetch, $occurredAt, $client, $language): WebsitePageView {
            $existing = WebsitePageView::query()->find($activity->getKey());
            if ($existing instanceof WebsitePageView) {
                return $existing;
            }

            ActivityRequest::query()->whereKey((string) $activity->getKey())->update(array_merge($identity, $client, $language, [
                'is_page_view' => true,
                'user_id' => $activity->user_id,
                'username' => $activity->username,
                'actor_type' => $activity->actor_type,
                'segment' => $this->segment((string) $activity->path),
                'route_name' => $activity->route_name,
                'path' => $activity->path,
                'referer_host' => $this->refererHost($activity->referer),
                'is_prefetch' => $prefetch,
                'http_status' => (int) $activity->http_status,
                'response_ms' => $activity->duration_ms,
                'occurred_at' => $occurredAt,
            ]));

            return WebsitePageView::query()->findOrFail((string) $activity->getKey());
        }, 3);
    }

    public function qualifies(ActivityRequest $activity): bool
    {
        $contentType = strtolower(trim((string) $activity->response_content_type));
        $routeName = trim((string) $activity->route_name);

        return $activity->user_id === null
            && $this->isPublicAnalyticsRoute($routeName)
            && strtoupper((string) $activity->method) === 'GET'
            && (int) $activity->http_status >= 200
            && (int) $activity->http_status < 300
            && str_starts_with($contentType, 'text/html');
    }

    private function isPublicAnalyticsRoute(string $routeName): bool
    {
        return $routeName === 'home'
            || $routeName === 'auth.login'
            || str_starts_with($routeName, 'public.')
            || str_starts_with($routeName, 'materials.');
    }

    private function segment(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return match (true) {
            str_starts_with($path, '/developer') => 'developer',
            str_starts_with($path, '/pemuridan') => 'pemuridan',
            str_starts_with($path, '/ibadah') => 'ibadah',
            $path === '/login' => 'login',
            default => 'publik',
        };
    }

    private function refererHost(?string $referer): ?string
    {
        $host = strtolower(trim((string) parse_url(trim((string) $referer), PHP_URL_HOST)));

        return $host !== '' ? $host : null;
    }
}
