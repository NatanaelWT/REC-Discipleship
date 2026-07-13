<?php

namespace App\Services\Analytics;

use App\Models\ActivityRequest;
use App\Models\WebsitePageView;
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

        $attributes = $this->attributes($activity, $identity, $prefetch, $language);

        return DB::transaction(function () use ($activity, $attributes): WebsitePageView {
            $existing = WebsitePageView::query()->find($activity->getKey());
            if ($existing instanceof WebsitePageView) {
                return $existing;
            }

            ActivityRequest::query()->whereKey((string) $activity->getKey())->update($attributes);

            return WebsitePageView::query()->findOrFail((string) $activity->getKey());
        }, 3);
    }

    /**
     * @param  array{visitor_hash:string,identity_source:string}  $identity
     * @return array<string, mixed>
     */
    public function attributes(ActivityRequest $activity, array $identity, bool $prefetch = false, array $language = []): array
    {
        if (! $this->qualifies($activity)) {
            return [];
        }

        $client = $this->clients->classify($activity->user_agent);
        $language = array_merge(['language_code' => null, 'language_name' => null], $language);

        return array_merge($identity, $client, $language, [
            'is_page_view' => true,
            'segment' => $this->segment((string) $activity->path),
            'referer_host' => $this->refererHost($activity->referer),
            'is_prefetch' => $prefetch,
            'response_ms' => $activity->duration_ms,
            'occurred_at' => $activity->started_at,
        ]);
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
