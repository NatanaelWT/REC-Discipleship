<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebsiteAnalyticsPublicScopeMigrationTest extends TestCase
{
    public function test_migration_prunes_internal_views_and_recalculates_remaining_sessions(): void
    {
        $this->createAnalyticsTables();

        $mixedSession = '01'.str_repeat('A', 24);
        $internalSession = '01'.str_repeat('B', 24);
        $publicSession = '01'.str_repeat('C', 24);
        $emptySession = '01'.str_repeat('D', 24);
        foreach ([$mixedSession, $internalSession, $publicSession, $emptySession] as $index => $sessionId) {
            DB::table('website_sessions')->insert([
                'id' => $sessionId,
                'visitor_hash' => hash('sha256', $sessionId),
                'user_id' => 99,
                'username' => 'stale-user',
                'identity_source' => 'legacy_session',
                'started_at' => '2026-06-20 00:00:00.000000',
                'last_seen_at' => '2026-06-20 23:59:59.000000',
                'landing_path' => '/stale-landing-'.$index,
                'exit_path' => '/stale-exit-'.$index,
                'page_views' => 99,
            ]);
        }

        $this->insertPageView('02'.str_repeat('A', 24), $mixedSession, 99, 'developer', '/internal-before', '2026-06-20 01:00:00.000000');
        $this->insertPageView('02'.str_repeat('B', 24), $mixedSession, null, 'publik', '/public-one', '2026-06-20 02:00:00.000000');
        $this->insertPageView('02'.str_repeat('C', 24), $mixedSession, null, 'login', '/login', '2026-06-20 03:00:00.000000');
        $this->insertPageView('02'.str_repeat('D', 24), $mixedSession, 99, 'publik', '/public-after-login', '2026-06-20 04:00:00.000000');
        $this->insertPageView('02'.str_repeat('E', 24), $internalSession, 99, 'publik', '/authenticated-public', '2026-06-20 05:00:00.000000');
        $this->insertPageView('02'.str_repeat('F', 24), $publicSession, null, 'publik', '/', '2026-06-20 06:00:00.000000');

        $migration = require database_path('migrations/2026_06_21_000004_prune_internal_website_analytics.php');
        $migration->up();

        $this->assertSame(3, DB::table('website_page_views')->count());
        $this->assertDatabaseMissing('website_page_views', ['path' => '/internal-before']);
        $this->assertDatabaseMissing('website_page_views', ['path' => '/public-after-login']);
        $this->assertDatabaseMissing('website_sessions', ['id' => $internalSession]);
        $this->assertDatabaseMissing('website_sessions', ['id' => $emptySession]);

        $mixed = DB::table('website_sessions')->where('id', $mixedSession)->first();
        $this->assertNotNull($mixed);
        $this->assertSame(2, (int) $mixed->page_views);
        $this->assertSame('/public-one', $mixed->landing_path);
        $this->assertSame('/login', $mixed->exit_path);
        $this->assertStringStartsWith('2026-06-20 02:00:00', (string) $mixed->started_at);
        $this->assertStringStartsWith('2026-06-20 03:00:00', (string) $mixed->last_seen_at);
        $this->assertNull($mixed->user_id);
        $this->assertNull($mixed->username);

        $migration->down();
        $this->assertSame(3, DB::table('website_page_views')->count());
    }

    public function test_migration_is_safe_when_analytics_tables_do_not_exist(): void
    {
        $migration = require database_path('migrations/2026_06_21_000004_prune_internal_website_analytics.php');

        $migration->up();

        $this->assertFalse(Schema::hasTable('website_page_views'));
    }

    private function createAnalyticsTables(): void
    {
        Schema::create('website_sessions', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('visitor_hash', 64);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('identity_source');
            $table->dateTime('started_at', 6);
            $table->dateTime('last_seen_at', 6);
            $table->text('landing_path');
            $table->text('exit_path');
            $table->unsignedInteger('page_views');
        });
        Schema::create('website_page_views', static function (Blueprint $table): void {
            $table->ulid('request_id')->primary();
            $table->ulid('session_id');
            $table->char('visitor_hash', 64);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('actor_type');
            $table->string('segment')->nullable();
            $table->text('path');
            $table->dateTime('occurred_at', 6);
        });
    }

    private function insertPageView(string $requestId, string $sessionId, ?int $userId, string $segment, string $path, string $occurredAt): void
    {
        DB::table('website_page_views')->insert([
            'request_id' => $requestId,
            'session_id' => $sessionId,
            'visitor_hash' => hash('sha256', $requestId),
            'user_id' => $userId,
            'username' => $userId === null ? null : 'developer',
            'actor_type' => $userId === null ? 'anonymous' : 'user',
            'segment' => $segment,
            'path' => $path,
            'occurred_at' => $occurredAt,
        ]);
    }
}
