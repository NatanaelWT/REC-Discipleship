<?php

namespace Tests\Feature;

use App\Models\ActivityRequest;
use App\Models\User;
use App\Services\Analytics\BrowserLanguageClassifier;
use App\Services\Analytics\WebsiteStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
        $this->assertSame(2, DB::table('website_page_views')->count());
        $this->assertSame(1, DB::table('website_page_views')->distinct()->count('visitor_hash'));
        $this->assertSame(1, DB::table('website_sessions')->count());
        $this->assertSame(2, (int) DB::table('website_sessions')->value('page_views'));
        $this->assertDatabaseHas('website_page_views', [
            'segment' => 'publik',
            'is_bot' => false,
            'language_code' => 'id-ID',
            'language_name' => 'Indonesia (id-ID)',
        ]);
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

        $this->assertSame(2, DB::table('website_sessions')->count());
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

        $this->assertSame(2, DB::table('website_page_views')->count());
        $this->assertSame(1, DB::table('website_page_views')->where('is_bot', true)->count());
        $this->assertSame(1, DB::table('website_page_views')->where('is_prefetch', true)->count());
    }

    public function test_authenticated_user_identity_is_stable_across_sessions(): void
    {
        $user = $this->developer();
        $this->actingAs($user)->get('/_analytics-test/page')->assertOk();
        $firstHash = (string) DB::table('website_page_views')->value('visitor_hash');
        $this->flushSession();
        $this->actingAs($user)->get('/_analytics-test/page')->assertOk();

        $this->assertSame(1, DB::table('website_page_views')->distinct()->count('visitor_hash'));
        $this->assertSame($firstHash, (string) DB::table('website_page_views')->orderByDesc('occurred_at')->value('visitor_hash'));
    }

    public function test_developer_statistics_page_and_filters_are_available_only_to_developer(): void
    {
        $this->withHeader('Accept-Language', 'id-ID')->get('/_analytics-test/page')->assertOk();
        $this->actingAs($this->developer())
            ->get('/developer/statistics?range=today&segment=publik&country=ID&city=Surabaya')
            ->assertOk()
            ->assertSee('Statistik Akses Website')
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
            'route_name' => 'legacy.page',
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

        $this->assertSame(0, Artisan::call('analytics:backfill'));
        $this->assertSame(0, Artisan::call('analytics:backfill'));
        $this->assertSame(1, DB::table('website_page_views')->where('identity_source', 'legacy_session')->count());
        $this->assertNull(DB::table('website_page_views')->value('language_code'));
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
            DB::table('website_page_views')->insert([
                'request_id' => sprintf('03%024d', $index),
                'session_id' => sprintf('04%024d', $index),
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
            ]);
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
                $rows[] = [
                    'request_id' => sprintf('01%024d', $index),
                    'session_id' => sprintf('02%024d', intdiv($index, 10)),
                    'visitor_hash' => hash('sha256', 'visitor-'.($index % 5000)),
                    'identity_source' => 'anonymous_cookie',
                    'user_id' => null,
                    'username' => null,
                    'actor_type' => 'anonymous',
                    'segment' => $index % 2 === 0 ? 'publik' : 'pemuridan',
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
                ];
            }
            DB::table('website_page_views')->insert($rows);
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
            Route::get('/_analytics-test/page', static fn () => response('<html><body>ok</body></html>', 200)->header('Content-Type', 'text/html; charset=UTF-8'))->name('analytics-test.page');
            Route::get('/_analytics-test/json', static fn () => response()->json(['ok' => true]))->name('analytics-test.json');
            Route::get('/_analytics-test/redirect', static fn () => redirect('/_analytics-test/page'))->name('analytics-test.redirect');
            Route::post('/_analytics-test/post', static fn () => response('ok'))->name('analytics-test.post');
        });
    }

    private function createTables(): void
    {
        Schema::create('branches', static function (Blueprint $table): void {
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
        Schema::create('activity_requests', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('actor_type')->default('anonymous');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('role')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('branch_label')->nullable();
            $table->char('visitor_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('method', 12);
            $table->string('route_name')->nullable();
            $table->text('path');
            $table->string('category')->default('request');
            $table->string('action')->default('request');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('query_data')->nullable();
            $table->json('input_data')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome')->default('pending');
            $table->text('redirect_to')->nullable();
            $table->string('response_content_type')->nullable();
            $table->unsignedBigInteger('response_size')->nullable();
            $table->decimal('duration_ms', 14, 3)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
        });
        Schema::create('activity_events', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('request_id');
            $table->string('category');
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->text('description')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('changed_values')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 6);
        });
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
            $table->string('identity_source');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('actor_type');
            $table->string('segment');
            $table->string('route_name')->nullable();
            $table->text('path');
            $table->string('referer_host')->nullable();
            $table->string('language_code', 20)->nullable();
            $table->string('language_name', 100)->nullable();
            $table->string('device_type');
            $table->string('browser_name')->nullable();
            $table->string('os_name')->nullable();
            $table->boolean('is_bot');
            $table->boolean('is_prefetch');
            $table->unsignedSmallInteger('http_status');
            $table->decimal('response_ms', 14, 3)->nullable();
            $table->dateTime('occurred_at', 6);
        });
        DB::table('branches')->insert(['id' => 1, 'label' => 'Kutisari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
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
