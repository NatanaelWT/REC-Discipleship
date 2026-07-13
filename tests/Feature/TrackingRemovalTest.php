<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class TrackingRemovalTest extends TestCase
{
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
