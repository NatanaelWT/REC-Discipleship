<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrackingRemovalTest extends TestCase
{
    public function test_legacy_analytics_identity_is_removed_during_transition(): void
    {
        Route::middleware('web')->get('/_tests/legacy-analytics-identity', static function () {
            return response()->json([
                'has_legacy_identity' => session()->has('analytics.visitor_id'),
            ]);
        });

        $response = $this
            ->withSession(['analytics.visitor_id' => 'legacy-session-id'])
            ->withCookie('rec_analytics_visitor', 'legacy-cookie-id')
            ->get('/_tests/legacy-analytics-identity');

        $response
            ->assertOk()
            ->assertJson(['has_legacy_identity' => false])
            ->assertCookieExpired('rec_analytics_visitor')
            ->assertSessionMissing('analytics.visitor_id')
            ->assertHeaderMissing('X-Activity-Request-Id');
    }

    public function test_normal_request_does_not_create_an_analytics_cookie(): void
    {
        $spoolBefore = $this->activitySpoolSnapshot();
        $response = $this->get('/');

        $analyticsCookies = array_filter(
            $response->headers->getCookies(),
            static fn ($cookie): bool => $cookie->getName() === 'rec_analytics_visitor',
        );

        $this->assertSame([], array_values($analyticsCookies));
        $this->assertSame($spoolBefore, $this->activitySpoolSnapshot());
        $response->assertHeaderMissing('X-Activity-Request-Id');
    }

    public function test_removed_developer_routes_are_not_found_for_guest_and_developer(): void
    {
        $this->withMiddleware(ValidateCsrfToken::class);
        $paths = [
            '/developer/activities',
            '/developer/activities/legacy-id',
            '/developer/statistics',
            '/developer/maintenance',
            '/developer/maintenance/legacy-id/batch',
        ];

        foreach ($paths as $path) {
            foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $this->call($method, $path)->assertNotFound();
            }
        }

        $this->actingAsRecUser('developer', null, 'developer');
        foreach ($paths as $path) {
            foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                $this->call($method, $path)->assertNotFound();
            }
        }
    }

    /** @return array<string, array{size: int, modified_at: int}> */
    private function activitySpoolSnapshot(): array
    {
        $directory = storage_path('app/private/activity-spool');
        $paths = array_merge(
            glob($directory.'/*.jsonl') ?: [],
            glob($directory.'/invalid/*.jsonl') ?: [],
        );
        sort($paths, SORT_STRING);

        $snapshot = [];
        foreach ($paths as $path) {
            $snapshot[str_replace('\\', '/', $path)] = [
                'size' => (int) filesize($path),
                'modified_at' => (int) filemtime($path),
            ];
        }

        return $snapshot;
    }
}
