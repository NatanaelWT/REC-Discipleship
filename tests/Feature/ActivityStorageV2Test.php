<?php

namespace Tests\Feature;

use App\Models\ActivityRequest;
use App\Services\Activity\ActivityRecorder;
use App\Services\Analytics\WebsiteStatisticsService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActivityStorageV2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'activity.storage' => 'split',
            'activity.enabled' => true,
            'analytics.enabled' => true,
        ]);
        $migration = require database_path('migrations/2026_07_13_100000_create_retained_activity_storage.php');
        $migration->up();

        Route::middleware('web')->group(function (): void {
            Route::get('/_activity-v2/safe', static fn () => response('<p>ok</p>', 200, ['Content-Type' => 'text/html']))
                ->name('public.activity-v2.safe');
            Route::get('/_activity-v2/safe-event', static function (ActivityRecorder $recorder) {
                $recorder->record(
                    'file',
                    'file.previewed',
                    subjectType: 'materials',
                    subjectId: 17,
                );

                return response('ok');
            })->name('public.activity-v2.safe-event');
            Route::post('/_activity-v2/mutation', static function (ActivityRecorder $recorder) {
                $recorder->record(
                    'data',
                    'activity-v2.changed',
                    subjectType: 'test_records',
                    subjectId: 7,
                    before: ['name' => 'lama'],
                    after: ['name' => 'baru'],
                );

                return response('ok');
            })->name('activity-v2.mutation');
        });
    }

    public function test_safe_get_with_a_controller_event_still_writes_exactly_one_statement(): void
    {
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'request_activities') || str_contains($sql, 'audit_events')) {
                $queries[] = $sql;
            }
        });

        $response = $this->get('/_activity-v2/safe-event')->assertOk();

        $this->assertCount(1, $queries, implode("\n", $queries));
        $this->assertStringStartsWith('insert into', $queries[0]);

        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));
        $this->assertSame('file', $activity->category);
        $this->assertSame('file.previewed', $activity->action);
        $this->assertSame('materials', $activity->subject_type);
        $this->assertSame('17', $activity->subject_id);
        $this->assertSame(0, (int) $activity->events_count);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_safe_get_writes_one_final_request_row_without_select_or_update(): void
    {
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'request_activities')) {
                $queries[] = strtolower($query->sql);
            }
        });

        $response = $this->get('/_activity-v2/safe')->assertOk();

        $this->assertCount(1, $queries, implode("\n", $queries));
        $this->assertStringStartsWith('insert into', $queries[0]);
        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));
        $this->assertSame('succeeded', $activity->outcome);
        $this->assertTrue($activity->is_page_view);
        $this->assertSame('public.activity-v2.safe', $activity->route_name);
    }

    public function test_mutation_flushes_audit_events_once_and_keeps_request_summary(): void
    {
        $response = $this->post('/_activity-v2/mutation')->assertOk();
        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));

        $this->assertSame(1, (int) $activity->events_count);
        $event = $activity->events()->where('action', 'activity-v2.changed')->firstOrFail();
        $this->assertSame('lama', $event->before_values['name']);
        $this->assertSame('baru', $event->after_values['name']);
        $this->assertDatabaseHas('audit_events', [
            'request_id' => $activity->id,
            'subject_type' => 'test_records',
            'subject_id' => '7',
        ]);
    }

    public function test_statistics_use_anonymous_rollups_after_raw_rows_expire(): void
    {
        $base = [
            'activity_date' => '2025-12-01',
            'segment' => '',
            'route_name' => '',
            'path' => '',
            'language_code' => '',
            'device_type' => '',
            'page_views' => 12,
            'human_page_views' => 12,
            'unique_visitors' => 5,
            'human_unique_visitors' => 5,
            'bot_views' => 0,
            'prefetch_views' => 0,
            'total_response_ms' => 600,
            'average_response_ms' => 50,
            'human_total_response_ms' => 600,
            'human_average_response_ms' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('website_daily_rollups')->insert([
            array_merge($base, [
                'dimension_hash' => hash('sha256', 'summary'),
                'rollup_scope' => 'summary',
            ]),
            array_merge($base, [
                'dimension_hash' => hash('sha256', 'detail'),
                'rollup_scope' => 'detail',
                'segment' => 'publik',
                'route_name' => 'public.archive',
                'path' => '/publik/archive',
            ]),
        ]);

        $dashboard = app(WebsiteStatisticsService::class)->dashboard(
            Request::create('/developer/statistics', 'GET', ['range' => 'all']),
        );

        $this->assertSame(12, $dashboard['summary']['page_views']);
        $this->assertSame(5, $dashboard['summary']['visitors']);
        $this->assertSame('public.archive', $dashboard['topPages'][0]['key']);
        $this->assertSame(12, $dashboard['topPages'][0]['count']);
    }
}
