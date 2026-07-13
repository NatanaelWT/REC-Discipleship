<?php

namespace Tests\Feature;

use App\Support\HelperManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RuntimeBootstrapHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_get_and_post_are_bootstrapped_once_without_runtime_schema_introspection(): void
    {
        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = strtolower((string) $query->sql);
        });

        $getResponse = $this->get('/');
        $getResponse
            ->assertOk()
            ->assertHeader('X-Runtime-Bootstrap-Count', '1')
            ->assertHeader('X-Runtime-Helper-Count', (string) count(HelperManifest::forPath('')));

        $postResponse = $this->post('/login', []);
        $postResponse
            ->assertRedirect()
            ->assertHeader('X-Runtime-Bootstrap-Count', '1')
            ->assertHeader('X-Runtime-Helper-Count', (string) count(HelperManifest::forPath('login')));

        $this->assertLessThan(count(HelperManifest::all()), (int) $getResponse->headers->get('X-Runtime-Helper-Count'));
        $this->assertLessThan(count(HelperManifest::all()), (int) $postResponse->headers->get('X-Runtime-Helper-Count'));
        $this->assertSame([], array_values(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, 'information_schema')
                || str_contains($sql, 'sqlite_master')
                || str_contains($sql, 'pragma_table'),
        )), 'Normal HTTP requests must not introspect the database schema at runtime.');
        $this->assertSame([], array_values(array_filter(
            $queries,
            static fn (string $sql): bool => preg_match(
                '/\b(?:aktivitas|request_activities|activity_requests|permintaan_aktivitas|audit_events|activity_events|peristiwa_aktivitas|website_page_views|kunjungan_halaman|website_daily_rollups|website_sessions|sesi|maintenance_runs)\b/',
                $sql,
            ) === 1,
        )), 'Normal HTTP requests must not access removed tracking storage.');
        $this->assertFalse($getResponse->headers->has('X-Activity-Request-Id'));
        $this->assertFalse($postResponse->headers->has('X-Activity-Request-Id'));
    }
}
