<?php

namespace Tests\Feature;

use App\Models\ActivityRequest;
use App\Models\User;
use App\Services\Analytics\BrowserLanguageClassifier;
use App\Services\Analytics\WebsiteAnalyticsWriter;
use App\Services\Analytics\WebsiteStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebsiteAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->registerRoutes();
        $this->createTables();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_html_page_views_are_recorded_with_stable_anonymous_identity_and_session(): void
    {
        $first = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/125.0 Safari/537.36',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8',
            ])
            ->get('/_analytics-test/page');
        $second = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/125.0 Safari/537.36',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8',
            ])
            ->get('/_analytics-test/page');

        $first->assertOk()->assertCookie(config('analytics.cookie.name'));
        $this->assertSame(2, DB::table('aktivitas')->where('is_page_view', true)->count());
        $this->assertSame(1, DB::table('aktivitas')->where('is_page_view', true)->distinct()->count('visitor_hash'));
        $dashboard = app(WebsiteStatisticsService::class)->dashboard(Request::create('/developer/statistics', 'GET', ['range' => 'all']));
        $this->assertSame(1, $dashboard['summary']['sessions']);
        $this->assertSame(2.0, $dashboard['summary']['pages_per_session']);
        $this->assertDatabaseHas('aktivitas', [
            'is_page_view' => true,
            'segment' => 'publik',
            'is_bot' => false,
            'language_code' => 'id-ID',
            'language_name' => 'Indonesia (id-ID)',
        ]);
    }

    public function test_only_anonymous_public_material_home_and_login_routes_qualify(): void
    {
        $writer = app(WebsiteAnalyticsWriter::class);
        foreach (['home', 'public.report', 'materials.index', 'auth.login'] as $routeName) {
            $activity = new ActivityRequest([
                'user_id' => null,
                'method' => 'GET',
                'route_name' => $routeName,
                'http_status' => 200,
                'response_content_type' => 'text/html; charset=UTF-8',
            ]);
            $this->assertTrue($writer->qualifies($activity), $routeName.' seharusnya masuk statistik.');
        }

        foreach ([
            ['route_name' => 'developer.dashboard', 'user_id' => null],
            ['route_name' => 'public.report', 'user_id' => 1],
            ['route_name' => 'auth.login.store', 'user_id' => null],
        ] as $attributes) {
            $activity = new ActivityRequest(array_merge($attributes, [
                'method' => 'GET',
                'http_status' => 200,
                'response_content_type' => 'text/html; charset=UTF-8',
            ]));
            $this->assertFalse($writer->qualifies($activity));
        }
    }

    public function test_accept_language_parser_honors_quality_and_rejects_invalid_values(): void
    {
        $classifier = app(BrowserLanguageClassifier::class);

        $this->assertSame([
            'language_code' => 'en-US',
            'language_name' => 'Inggris (en-US)',
        ], $classifier->classify('id-ID;q=0.8, en-US;q=0.9'));
        $this->assertSame('id-ID', $classifier->classify('id_ID, en-US;q=0.7')['language_code']);
        $this->assertSame('en-US', $classifier->classify('id-ID;q=0, en-US;q=0.8')['language_code']);
        $this->assertNull($classifier->classify('id-ID;q=bogus, *')['language_code']);
        $this->assertNull($classifier->classify('')['language_code']);
    }

    public function test_new_session_is_created_after_thirty_minutes(): void
    {
        CarbonImmutable::setTestNow('2026-06-21 10:00:00', 'UTC');
        $this->get('/_analytics-test/page')->assertOk();
        CarbonImmutable::setTestNow('2026-06-21 10:31:00', 'UTC');
        $this->get('/_analytics-test/page')->assertOk();

        $dashboard = app(WebsiteStatisticsService::class)->dashboard(Request::create('/developer/statistics', 'GET', [
            'range' => 'today',
        ]));

        $this->assertSame(2, $dashboard['summary']['sessions']);
        $this->assertSame(1.0, $dashboard['summary']['pages_per_session']);
    }

    public function test_exact_thirty_minute_gap_stays_in_same_session(): void
    {
        CarbonImmutable::setTestNow('2026-06-21 10:00:00', 'UTC');
        $this->get('/_analytics-test/page')->assertOk();
        CarbonImmutable::setTestNow('2026-06-21 10:30:00', 'UTC');
        $this->get('/_analytics-test/page')->assertOk();

        $dashboard = app(WebsiteStatisticsService::class)->dashboard(Request::create('/developer/statistics', 'GET', [
            'range' => 'today',
        ]));

        $this->assertSame(1, $dashboard['summary']['sessions']);
        $this->assertSame(2.0, $dashboard['summary']['pages_per_session']);
    }

    public function test_bot_and_prefetch_are_stored_but_technical_requests_are_not_page_views(): void
    {
        $this->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')
            ->get('/_analytics-test/page')->assertOk();
        $this->withHeaders([
            'Purpose' => 'prefetch',
            'User-Agent' => 'Mozilla/5.0 Chrome/125.0 Safari/537.36',
        ])->get('/_analytics-test/page')->assertOk();
        $this->get('/_analytics-test/json')->assertOk();
        $this->get('/_analytics-test/redirect')->assertRedirect('/_analytics-test/page');
        $this->post('/_analytics-test/post')->assertOk();

        $this->assertSame(2, DB::table('aktivitas')->where('is_page_view', true)->count());
        $this->assertSame(1, DB::table('aktivitas')->where('is_page_view', true)->where('is_bot', true)->count());
        $this->assertSame(1, DB::table('aktivitas')->where('is_page_view', true)->where('is_prefetch', true)->count());
    }

    public function test_authenticated_and_internal_page_views_are_not_recorded(): void
    {
        $user = $this->developer();
        $this->actingAs($user)->get('/_analytics-test/page')->assertOk();
        $this->flushSession();
        $this->get('/_analytics-test/internal')->assertOk();

        $this->assertSame(0, DB::table('aktivitas')->where('is_page_view', true)->count());
        $this->assertSame(2, DB::table('aktivitas')->count());
    }

    public function test_developer_statistics_page_and_filters_are_available_only_to_developer(): void
    {
        $this->withHeader('Accept-Language', 'id-ID')->get('/_analytics-test/page')->assertOk();
        $this->actingAs($this->developer())
            ->get('/developer/statistics?range=today&segment=publik&country=ID&city=Surabaya')
            ->assertOk()
            ->assertSee('data-developer-header', false)
            ->assertSee('discipleship-page-header__stats', false)
            ->assertDontSee('developer-hub-nav', false)
            ->assertSee('class="btn tiny ghost developer-link-button"', false)
            ->assertSee('Statistik Kunjungan Publik')
            ->assertDontSee('Percobaan login')
            ->assertSee('Page view harian')
            ->assertSee('Bahasa browser')
            ->assertSee('Jam akses')
            ->assertDontSee('Negara pengunjung')
            ->assertSee('Paling aktif');

        $branchUser = User::query()->create([
            'username' => 'branch-user',
            'password' => Hash::make('secret'),
            'branch_id' => 1,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => true,
        ]);
        $this->actingAs($branchUser)->get('/developer/statistics')
            ->assertRedirect('/pemuridan/dashboard?error=access_denied');
    }

    public function test_backfill_is_idempotent_and_uses_legacy_identity(): void
    {
        ActivityRequest::query()->create([
            'id' => '01KVMZZZZZZZZZZZZZZZZZZZZZ',
            'actor_type' => 'anonymous',
            'visitor_hash' => str_repeat('a', 64),
            'ip_address' => '127.0.0.1',
            'method' => 'GET',
            'route_name' => 'public.legacy.page',
            'path' => '/legacy',
            'category' => 'request',
            'action' => 'request',
            'http_status' => 200,
            'outcome' => 'succeeded',
            'response_content_type' => 'text/html; charset=UTF-8',
            'duration_ms' => 12.4,
            'started_at' => CarbonImmutable::now('UTC')->subDay(),
            'completed_at' => CarbonImmutable::now('UTC')->subDay(),
        ]);
        ActivityRequest::query()->create([
            'id' => '01KVMYYYYYYYYYYYYYYYYYYYYYY',
            'actor_type' => 'user',
            'user_id' => 1,
            'username' => 'developer',
            'role' => 'developer',
            'visitor_hash' => str_repeat('b', 64),
            'ip_address' => '127.0.0.1',
            'method' => 'GET',
            'route_name' => 'developer.dashboard',
            'path' => '/developer',
            'category' => 'request',
            'action' => 'request',
            'http_status' => 200,
            'outcome' => 'succeeded',
            'response_content_type' => 'text/html; charset=UTF-8',
            'duration_ms' => 8.2,
            'started_at' => CarbonImmutable::now('UTC')->subDay(),
            'completed_at' => CarbonImmutable::now('UTC')->subDay(),
        ]);

        $this->assertSame(0, Artisan::call('analytics:backfill'));
        $this->assertSame(0, Artisan::call('analytics:backfill'));
        $this->assertSame(1, DB::table('aktivitas')->where('is_page_view', true)->where('identity_source', 'legacy_session')->count());
        $this->assertNull(DB::table('aktivitas')->where('is_page_view', true)->value('language_code'));
    }

    public function test_dashboard_defensively_ignores_historical_internal_page_views_and_old_filters(): void
    {
        $occurredAt = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u');
        foreach ([
            ['request_id' => '05'.str_repeat('0', 24), 'user_id' => null, 'segment' => 'publik'],
            ['request_id' => '05'.str_repeat('1', 24), 'user_id' => 1, 'segment' => 'developer'],
        ] as $row) {
            DB::table('aktivitas')->insert($this->pageViewRow(array_merge($row, [
                'id' => $row['request_id'],
                'visitor_hash' => hash('sha256', $row['request_id']),
                'identity_source' => 'legacy_session',
                'username' => $row['user_id'] === null ? null : 'developer',
                'actor_type' => $row['user_id'] === null ? 'anonymous' : 'user',
                'route_name' => $row['segment'].'.page',
                'path' => '/'.$row['segment'],
                'device_type' => 'desktop',
                'is_bot' => false,
                'is_prefetch' => false,
                'http_status' => 200,
                'occurred_at' => $occurredAt,
            ])));
        }

        $dashboard = app(WebsiteStatisticsService::class)->dashboard(Request::create('/developer/statistics', 'GET', [
            'range' => 'today',
            'segment' => 'developer',
            'actor' => 'user',
        ]));

        $this->assertSame('', $dashboard['filters']['segment']);
        $this->assertArrayNotHasKey('actor', $dashboard['filters']);
        $this->assertSame(1, $dashboard['summary']['page_views']);
    }

    public function test_access_hour_distribution_uses_asia_jakarta_timezone(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-21 17:15:00', 'UTC'));
        $this->withHeader('Accept-Language', 'id-ID')->get('/_analytics-test/page')->assertOk();
        $this->get('/_analytics-test/page')->assertOk();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-22 01:00:00', 'UTC'));
        $this->get('/_analytics-test/page')->assertOk();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-22 03:00:00', 'UTC'));
        $this->get('/_analytics-test/page')->assertOk();
        $this->get('/_analytics-test/page')->assertOk();
        $this->get('/_analytics-test/page')->assertOk();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-22 05:00:00', 'UTC'));
        $this->get('/_analytics-test/page')->assertOk();

        $dashboard = app(WebsiteStatisticsService::class)->dashboard(Request::create('/developer/statistics', 'GET', [
            'range' => 'custom',
            'from' => '2026-06-22',
            'to' => '2026-06-22',
            'language' => 'id-ID',
        ]));

        $this->assertSame(['10', '00', '08', '12'], array_column($dashboard['accessHours'], 'key'));
        $this->assertSame([3, 2, 1, 1], array_column($dashboard['accessHours'], 'count'));
        $this->assertNotContains('01', array_column($dashboard['accessHours'], 'key'));
    }

    public function test_distribution_cards_show_five_rows_before_native_disclosure(): void
    {
        $rows = collect(range(1, 7))->map(static fn (int $index): array => [
            'key' => (string) $index,
            'label' => 'Item '.$index,
            'count' => 10 - $index,
            'visitors' => $index,
        ])->all();

        $html = view('developer.statistics._bars', [
            'title' => 'Pengujian',
            'rows' => $rows,
        ])->render();

        $this->assertStringContainsString('data-visible-rows="5"', $html);
        $this->assertStringContainsString('Lihat 2 lainnya', $html);
        $this->assertStringContainsString('<details class="analytics-more">', $html);
        $this->assertSame(7, substr_count($html, 'class="analytics-bar-row"'));
    }

    public function test_visitor_table_shows_ten_rows_before_remaining_visitors(): void
    {
        $occurredAt = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u');
        foreach (range(1, 12) as $index) {
            DB::table('aktivitas')->insert($this->pageViewRow([
                'id' => sprintf('03%024d', $index),
                'visitor_hash' => hash('sha256', 'compact-visitor-'.$index),
                'identity_source' => 'anonymous_cookie',
                'actor_type' => 'anonymous',
                'segment' => 'publik',
                'route_name' => 'compact.page',
                'path' => '/compact',
                'language_code' => 'id-ID',
                'language_name' => 'Indonesia (id-ID)',
                'device_type' => 'desktop',
                'is_bot' => false,
                'is_prefetch' => false,
                'http_status' => 200,
                'occurred_at' => $occurredAt,
            ]));
        }

        $response = $this->actingAs($this->developer())->get('/developer/statistics?range=today')->assertOk();

        $this->assertSame(10, substr_count($response->getContent(), 'analytics-primary-visitor-row'));
        $response->assertSee('Lihat 2 pengunjung lainnya');
    }

    public function test_dashboard_query_count_stays_bounded_with_one_hundred_thousand_page_views(): void
    {
        $baseTime = CarbonImmutable::now('UTC')->startOfDay();
        for ($batch = 0; $batch < 100; $batch++) {
            $rows = [];
            for ($offset = 0; $offset < 1000; $offset++) {
                $index = ($batch * 1000) + $offset;
                $rows[] = $this->pageViewRow([
                    'id' => sprintf('01%024d', $index),
                    'visitor_hash' => hash('sha256', 'visitor-'.($index % 5000)),
                    'identity_source' => 'anonymous_cookie',
                    'user_id' => null,
                    'username' => null,
                    'actor_type' => 'anonymous',
                    'segment' => $index % 2 === 0 ? 'publik' : 'login',
                    'route_name' => 'performance.page.'.($index % 20),
                    'path' => '/performance/'.($index % 20),
                    'referer_host' => $index % 3 === 0 ? 'example.test' : null,
                    'language_code' => $index % 4 === 0 ? 'id-ID' : 'en-US',
                    'language_name' => $index % 4 === 0 ? 'Indonesia (id-ID)' : 'Inggris (en-US)',
                    'device_type' => $index % 2 === 0 ? 'mobile' : 'desktop',
                    'browser_name' => 'Chrome',
                    'os_name' => 'Android',
                    'is_bot' => false,
                    'is_prefetch' => false,
                    'http_status' => 200,
                    'response_ms' => 25.5,
                    'occurred_at' => $baseTime->subDays($index % 30)->format('Y-m-d H:i:s.u'),
                ]);
            }
            DB::table('aktivitas')->insert($rows);
        }

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });
        $dashboard = app(WebsiteStatisticsService::class)->dashboard(Request::create('/developer/statistics?range=30'));

        $this->assertSame(100000, $dashboard['summary']['page_views']);
        $this->assertLessThanOrEqual(30, $queryCount);
    }

    private function registerRoutes(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/_analytics-test/page', static fn () => response('<html><body>ok</body></html>', 200)->header('Content-Type', 'text/html; charset=UTF-8'))->name('public.analytics-test.page');
            Route::get('/_analytics-test/internal', static fn () => response('<html><body>internal</body></html>', 200)->header('Content-Type', 'text/html; charset=UTF-8'))->name('developer.analytics-test.internal');
            Route::get('/_analytics-test/json', static fn () => response()->json(['ok' => true]))->name('analytics-test.json');
            Route::get('/_analytics-test/redirect', static fn () => redirect('/_analytics-test/page'))->name('analytics-test.redirect');
            Route::post('/_analytics-test/post', static fn () => response('ok'))->name('analytics-test.post');
        });
    }

    private function createTables(): void
    {
        Schema::create('cabang', static function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('access_scope');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
        $mergeMigration = require database_path('migrations/2026_07_07_000001_merge_activity_audit_tables.php');
        $mergeMigration->up();
        $dropSessionsMigration = require database_path('migrations/2026_07_07_000003_drop_website_sessions_table.php');
        $dropSessionsMigration->up();

        DB::table('cabang')->insert(['id' => 1, 'label' => 'Kutisari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<string, mixed> $overrides */
    private function pageViewRow(array $overrides): array
    {
        $id = (string) ($overrides['id'] ?? $overrides['request_id'] ?? Str::ulid());
        unset($overrides['request_id']);
        $occurredAt = (string) ($overrides['occurred_at'] ?? CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u'));
        $path = (string) ($overrides['path'] ?? '/');

        return array_merge([
            'id' => $id,
            'actor_type' => 'anonymous',
            'method' => 'GET',
            'route_name' => null,
            'path' => $path,
            'category' => 'request',
            'action' => 'request.page_view',
            'http_status' => 200,
            'outcome' => 'succeeded',
            'started_at' => $occurredAt,
            'completed_at' => $occurredAt,
            'is_page_view' => true,
            'visitor_hash' => hash('sha256', $id),
            'identity_source' => 'legacy_session',
            'segment' => 'publik',
            'device_type' => 'unknown',
            'is_bot' => false,
            'is_prefetch' => false,
            'response_ms' => null,
            'occurred_at' => $occurredAt,
        ], $overrides, ['id' => $id]);
    }

    private function developer(): User
    {
        return User::query()->firstOrCreate(['username' => 'developer'], [
            'password' => Hash::make('secret'),
            'branch_id' => null,
            'access_scope' => 'developer',
            'is_active' => true,
        ]);
    }
}
