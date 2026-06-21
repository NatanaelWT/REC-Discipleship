<?php

namespace App\Services\Analytics;

use App\Models\ActivityRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsVisitorIdentity
{
    /** @return array{visitor_hash:string, identity_source:string} */
    public function resolve(ActivityRequest $activity, Request $request, Response $response): array
    {
        if ((int) $activity->user_id > 0) {
            return [
                'visitor_hash' => $this->hash('user:'.(string) $activity->user_id),
                'identity_source' => 'user',
            ];
        }

        $cookieName = (string) config('analytics.cookie.name', 'rec_analytics_visitor');
        $rawId = trim((string) $request->cookie($cookieName, ''));
        if (preg_match('/^[a-f0-9]{64}$/', $rawId) !== 1) {
            $rawId = $request->hasSession()
                ? trim((string) $request->session()->get('analytics.visitor_id', ''))
                : '';
        }
        if (preg_match('/^[a-f0-9]{64}$/', $rawId) !== 1) {
            $rawId = bin2hex(random_bytes(32));
        }

        if ($request->hasSession()) {
            $request->session()->put('analytics.visitor_id', $rawId);
        }
        $response->headers->setCookie(Cookie::make(
            $cookieName,
            $rawId,
            (int) config('analytics.cookie.minutes', 525600),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));

        return [
            'visitor_hash' => $this->hash('anonymous:'.$rawId),
            'identity_source' => 'anonymous_cookie',
        ];
    }

    /** @return array{visitor_hash:string, identity_source:string} */
    public function legacy(ActivityRequest $activity): array
    {
        if ((int) $activity->user_id > 0) {
            return [
                'visitor_hash' => $this->hash('user:'.(string) $activity->user_id),
                'identity_source' => 'user',
            ];
        }

        $legacy = trim((string) $activity->visitor_hash);

        return [
            'visitor_hash' => $legacy !== '' ? $legacy : $this->hash('request:'.$activity->getKey()),
            'identity_source' => 'legacy_session',
        ];
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key', 'website-analytics'));
    }
}
