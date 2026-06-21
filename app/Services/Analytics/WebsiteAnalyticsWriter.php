<?php

namespace App\Services\Analytics;

use App\Models\ActivityRequest;
use App\Models\WebsitePageView;
use App\Models\WebsiteSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            $session = $this->session($activity, $identity, $occurredAt);

            return WebsitePageView::query()->create(array_merge($identity, $client, $language, [
                'request_id' => (string) $activity->getKey(),
                'session_id' => (string) $session->getKey(),
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
        }, 3);
    }

    public function qualifies(ActivityRequest $activity): bool
    {
        $contentType = strtolower(trim((string) $activity->response_content_type));

        return strtoupper((string) $activity->method) === 'GET'
            && (int) $activity->http_status >= 200
            && (int) $activity->http_status < 300
            && str_starts_with($contentType, 'text/html');
    }

    /** @param array{visitor_hash:string,identity_source:string} $identity */
    private function session(ActivityRequest $activity, array $identity, CarbonImmutable $occurredAt): WebsiteSession
    {
        $threshold = $occurredAt->subMinutes(max(1, (int) config('analytics.session_inactivity_minutes', 30)));
        $session = WebsiteSession::query()
            ->where('visitor_hash', $identity['visitor_hash'])
            ->where('last_seen_at', '>=', $threshold)
            ->orderByDesc('last_seen_at')
            ->lockForUpdate()
            ->first();

        if ($session instanceof WebsiteSession) {
            $session->forceFill([
                'last_seen_at' => $occurredAt,
                'exit_path' => (string) $activity->path,
                'page_views' => (int) $session->page_views + 1,
                'user_id' => $activity->user_id ?: $session->user_id,
                'username' => $activity->username ?: $session->username,
            ])->save();

            return $session;
        }

        return WebsiteSession::query()->create([
            'id' => (string) Str::ulid(),
            'visitor_hash' => $identity['visitor_hash'],
            'user_id' => $activity->user_id,
            'username' => $activity->username,
            'identity_source' => $identity['identity_source'],
            'started_at' => $occurredAt,
            'last_seen_at' => $occurredAt,
            'landing_path' => (string) $activity->path,
            'exit_path' => (string) $activity->path,
            'page_views' => 1,
        ]);
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
