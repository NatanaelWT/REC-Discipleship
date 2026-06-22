<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\ActivityRequest;
use App\Models\User;
use App\Services\Activity\ActivityContext;
use App\Services\Activity\ActivityRecorder;
use App\Services\Activity\SensitiveDataSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ActivityAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createCoreTables();
        $migration = require database_path('migrations/2026_06_21_000001_create_activity_audit_tables.php');
        $migration->up();
        $this->registerAuditTestRoutes();
    }

    public function test_page_view_and_unknown_route_are_recorded_with_distinct_ids(): void
    {
        $first = $this->get('/_audit-test/view')->assertOk();
        $second = $this->get('/_audit-test/view?search=anggota')->assertOk();
        $this->get('/alamat-tidak-ada')->assertNotFound();

        $firstId = $first->headers->get('X-Activity-Request-Id');
        $secondId = $second->headers->get('X-Activity-Request-Id');
        $this->assertNotNull($firstId);
        $this->assertNotSame($firstId, $secondId);
        $this->assertDatabaseHas('activity_requests', [
            'id' => $firstId,
            'route_name' => 'audit-test.view',
            'category' => 'navigation',
            'outcome' => 'succeeded',
            'http_status' => 200,
        ]);
        $this->assertDatabaseHas('activity_requests', [
            'path' => '/alamat-tidak-ada',
            'outcome' => 'failed',
            'http_status' => 404,
        ]);
    }

    public function test_audit_utc_columns_are_cast_as_utc_before_jakarta_display_conversion(): void
    {
        $response = $this->get('/_audit-test/view')->assertOk();
        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));

        $this->assertSame('UTC', $activity->started_at->getTimezone()->getName());
        $this->assertSame('+07:00', $activity->started_at->setTimezone('Asia/Jakarta')->format('P'));
    }

    public function test_secret_request_values_are_redacted_and_file_bodies_are_not_stored(): void
    {
        $response = $this->post('/_audit-test/input', [
            'username' => 'tester',
            'password' => 'rahasia-utama',
            'api_token' => 'token-rahasia',
            'profile' => ['secret_note' => 'jangan-simpan', 'city' => 'Surabaya'],
        ])->assertOk();

        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));
        $fields = $activity->input_data['fields'];
        $this->assertSame('[REDACTED]', $fields['password']);
        $this->assertSame('[REDACTED]', $fields['api_token']);
        $this->assertSame('[REDACTED]', $fields['profile']['secret_note']);
        $this->assertSame('Surabaya', $fields['profile']['city']);
        $this->assertStringNotContainsString('rahasia-utama', json_encode($activity->input_data));
    }

    public function test_authenticated_actor_and_model_before_after_are_recorded(): void
    {
        $user = $this->developer();
        $this->actingAs($user);

        $response = $this->post('/_audit-test/user/'.$user->getKey(), ['is_active' => '0'])->assertOk();
        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));

        $this->assertSame('user', $activity->actor_type);
        $this->assertSame('developer', $activity->username);
        $this->assertSame('developer', $activity->role);
        $event = $activity->events()->where('action', 'users.updated')->firstOrFail();
        $this->assertTrue((bool) $event->before_values['is_active']);
        $this->assertFalse((bool) $event->after_values['is_active']);
        $this->assertArrayHasKey('is_active', $event->changed_values);
    }

    public function test_login_success_and_logout_keep_the_correct_actor(): void
    {
        $user = $this->developer();

        $login = $this->post('/login', ['username' => 'developer', 'password' => 'secret-dev'])->assertRedirect('/developer');
        $loginActivity = ActivityRequest::query()->findOrFail($login->headers->get('X-Activity-Request-Id'));
        $this->assertSame((int) $user->getKey(), (int) $loginActivity->user_id);
        $this->assertTrue($loginActivity->events()->where('action', 'auth.login.succeeded')->exists());

        $logout = $this->post('/logout')->assertRedirect('/');
        $logoutActivity = ActivityRequest::query()->findOrFail($logout->headers->get('X-Activity-Request-Id'));
        $this->assertSame('developer', $logoutActivity->username);
        $this->assertTrue($logoutActivity->events()->where('action', 'auth.logout')->exists());
    }

    public function test_audit_failure_rolls_back_the_business_mutation(): void
    {
        $recorder = new class(app(ActivityContext::class), app(SensitiveDataSanitizer::class)) extends ActivityRecorder
        {
            public function recordModel(string $operation, Model $model): ?ActivityEvent
            {
                throw new RuntimeException('audit unavailable');
            }
        };
        $this->app->instance(ActivityRecorder::class, $recorder);

        $this->post('/_audit-test/create-user', ['username' => 'must_rollback'])->assertStatus(500);

        $this->assertDatabaseMissing('users', ['username' => 'must_rollback']);
    }

    public function test_caught_audit_exception_still_rolls_back_the_outer_mutation(): void
    {
        $recorder = new class(app(ActivityContext::class), app(SensitiveDataSanitizer::class)) extends ActivityRecorder
        {
            public function recordModel(string $operation, Model $model): ?ActivityEvent
            {
                throw new RuntimeException('audit unavailable');
            }
        };
        $this->app->instance(ActivityRecorder::class, $recorder);

        $this->post('/_audit-test/caught-audit-failure')->assertStatus(500);

        $this->assertDatabaseMissing('users', ['username' => 'caught_failure']);
    }

    public function test_audit_failure_runs_filesystem_rollback_cleanup(): void
    {
        $path = storage_path('framework/testing/activity-audit-rollback.txt');
        File::delete($path);
        $recorder = new class(app(ActivityContext::class), app(SensitiveDataSanitizer::class)) extends ActivityRecorder
        {
            public function recordModel(string $operation, Model $model): ?ActivityEvent
            {
                throw new RuntimeException('audit unavailable');
            }
        };
        $this->app->instance(ActivityRecorder::class, $recorder);

        $this->post('/_audit-test/file-mutation')->assertStatus(500);

        $this->assertFalse(File::exists($path));
        $this->assertDatabaseMissing('users', ['username' => 'file_rollback']);
    }

    public function test_model_password_hash_is_redacted_in_before_and_after_values(): void
    {
        $user = $this->developer();
        $this->actingAs($user);

        $response = $this->post('/_audit-test/user/'.$user->getKey().'/password', [
            'password' => 'new-secret-password',
        ])->assertOk();
        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));
        $event = $activity->events()->where('action', 'users.updated')->firstOrFail();

        $this->assertSame('[REDACTED]', $event->before_values['password']);
        $this->assertSame('[REDACTED]', $event->after_values['password']);
        $this->assertStringNotContainsString('new-secret-password', json_encode($event->toArray()));
    }

    public function test_access_denied_is_recorded(): void
    {
        $response = $this->get('/_audit-test/denied')->assertForbidden();
        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));

        $this->assertSame('denied', $activity->outcome);
        $this->assertSame(403, $activity->http_status);
        $this->assertTrue($activity->events()->where('action', 'request.access_denied')->exists());
    }

    public function test_validation_redirect_is_recorded_as_failed(): void
    {
        $response = $this->from('/_audit-test/view')->post('/_audit-test/validate', [])->assertRedirect('/_audit-test/view');
        $activity = ActivityRequest::query()->findOrFail($response->headers->get('X-Activity-Request-Id'));

        $this->assertSame('failed', $activity->outcome);
        $this->assertTrue($activity->events()->where('action', 'request.validation_failed')->exists());
    }

    public function test_only_developer_can_open_activity_pages_and_filters_work(): void
    {
        $branchUser = User::query()->create([
            'username' => 'branch_user',
            'password' => Hash::make('secret'),
            'branch_id' => 1,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => true,
        ]);
        $this->actingAs($branchUser);
        $this->get('/developer/activities')->assertRedirect('/pemuridan/dashboard?error=access_denied');

        $developer = $this->developer();
        $this->actingAs($developer);
        $this->get('/developer/activities?actor=user&username=developer&include_developer=1')
            ->assertOk()
            ->assertSee('Riwayat Aktivitas')
            ->assertSee('developer');

        $activity = ActivityRequest::query()->where('username', 'developer')->firstOrFail();
        $this->get('/developer/activities/'.$activity->id)
            ->assertOk()
            ->assertSee('Request '.$activity->id);
    }

    public function test_activity_list_hides_developer_by_default_and_allows_toggle_or_explicit_role(): void
    {
        $developer = $this->developer();
        ActivityRequest::query()->create([
            'actor_type' => 'user',
            'user_id' => $developer->id,
            'username' => 'developer-only-user',
            'role' => 'developer',
            'method' => 'GET',
            'path' => '/developer-only-activity',
            'category' => 'request',
            'action' => 'developer-only-action',
            'outcome' => 'succeeded',
            'started_at' => now('UTC')->subMinute(),
            'completed_at' => now('UTC')->subMinute(),
        ]);
        ActivityRequest::query()->create([
            'actor_type' => 'anonymous',
            'method' => 'GET',
            'path' => '/public-visible-activity',
            'category' => 'request',
            'action' => 'public-visible-action',
            'outcome' => 'succeeded',
            'started_at' => now('UTC')->subMinutes(2),
            'completed_at' => now('UTC')->subMinutes(2),
        ]);

        $this->actingAs($developer)
            ->get('/developer/activities')
            ->assertOk()
            ->assertSee('Tampilkan aktivitas developer')
            ->assertSee('/public-visible-activity')
            ->assertDontSee('/developer-only-activity');

        $this->get('/developer/activities?include_developer=1')
            ->assertOk()
            ->assertSee('/developer-only-activity')
            ->assertSee('name="include_developer" value="1" checked', false);

        $this->get('/developer/activities?role=developer')
            ->assertOk()
            ->assertSee('/developer-only-activity');
    }

    public function test_activity_list_uses_one_hundred_rows_with_pagination_above_table(): void
    {
        $startedAt = now('UTC');
        foreach (range(1, 125) as $index) {
            ActivityRequest::query()->create([
                'actor_type' => 'anonymous',
                'method' => 'GET',
                'path' => '/compact-activity/'.$index,
                'category' => 'request',
                'action' => 'request.page_view',
                'http_status' => 200,
                'outcome' => 'succeeded',
                'started_at' => $startedAt->subSeconds($index),
                'completed_at' => $startedAt->subSeconds($index),
            ]);
        }

        $response = $this->actingAs($this->developer())
            ->get('/developer/activities?actor=anonymous&include_developer=1')
            ->assertOk()
            ->assertSee('data-developer-header', false)
            ->assertDontSee('developer-hub-nav', false)
            ->assertSee('Maksimal 100 per halaman')
            ->assertSee('100 aktivitas pada halaman ini')
            ->assertSee('Berikutnya')
            ->assertSee('data-developer-cursor-pagination', false)
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('class="btn tiny ghost developer-cursor-button"', false)
            ->assertSee('actor=anonymous', false)
            ->assertSee('include_developer=1', false);

        $content = $response->getContent();
        $this->assertSame(100, substr_count($content, 'data-activity-row'));
        $this->assertSame(1, substr_count($content, 'class="activity-pagination"'));
        $this->assertTrue(strpos($content, 'class="activity-pagination"') < strpos($content, 'class="table-wrap activity-table-wrap"'));
    }

    public function test_advanced_activity_filters_open_only_when_an_advanced_filter_is_active(): void
    {
        $this->actingAs($this->developer())
            ->get('/developer/activities?actor=user')
            ->assertOk()
            ->assertSee('data-advanced-open="false"', false);

        $this->get('/developer/activities?username=developer')
            ->assertOk()
            ->assertSee('data-advanced-open="true"', false)
            ->assertSee('1 aktif');
    }

    public function test_activity_technical_panels_are_folded_and_failed_error_opens_automatically(): void
    {
        $developer = $this->developer();
        $activity = ActivityRequest::query()->create([
            'actor_type' => 'user',
            'user_id' => $developer->id,
            'username' => $developer->username,
            'role' => 'developer',
            'method' => 'GET',
            'path' => '/failed-request',
            'category' => 'request',
            'action' => 'request.exception',
            'http_status' => 500,
            'outcome' => 'failed',
            'error_type' => RuntimeException::class,
            'error_message' => 'Pengujian error',
            'started_at' => now('UTC'),
            'completed_at' => now('UTC'),
        ]);
        ActivityEvent::query()->create([
            'request_id' => $activity->id,
            'category' => 'request',
            'action' => 'request.exception',
            'changed_values' => ['outcome' => ['before' => 'pending', 'after' => 'failed']],
            'metadata' => ['source' => 'test'],
            'occurred_at' => now('UTC'),
        ]);

        $response = $this->actingAs($developer)
            ->get('/developer/activities/'.$activity->id)
            ->assertOk()
            ->assertSee('data-developer-header', false)
            ->assertDontSee('developer-hub-nav', false)
            ->assertSee('class="btn ghost developer-link-button"', false)
            ->assertSee('data-activity-technical="query"', false)
            ->assertSee('data-auto-open-error', false)
            ->assertSee('Sebelum, sesudah, dan metadata');

        $this->assertStringNotContainsString('data-activity-technical="query" open', $response->getContent());
    }

    private function registerAuditTestRoutes(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/_audit-test/view', static fn () => response('ok'))->name('audit-test.view');
            Route::post('/_audit-test/input', static fn () => response('ok'))->name('audit-test.input');
            Route::post('/_audit-test/validate', static function (Request $request) {
                $request->validate(['required_name' => ['required', 'string']]);

                return response('ok');
            })->name('audit-test.validate');
            Route::get('/_audit-test/denied', static function (): never {
                abort(403, 'denied');
            })->name('audit-test.denied');
            Route::post('/_audit-test/user/{user}', static function (User $user, Request $request) {
                $user->forceFill(['is_active' => $request->boolean('is_active')])->save();

                return response('ok');
            })->name('audit-test.user.update');
            Route::post('/_audit-test/create-user', static function (Request $request) {
                User::query()->create([
                    'username' => (string) $request->input('username'),
                    'password' => Hash::make('secret'),
                    'branch_id' => null,
                    'access_scope' => 'pelayan',
                    'is_active' => true,
                ]);

                return response('ok');
            })->name('audit-test.user.create');
            Route::post('/_audit-test/caught-audit-failure', static function () {
                try {
                    User::query()->create([
                        'username' => 'caught_failure',
                        'password' => Hash::make('secret'),
                        'branch_id' => null,
                        'access_scope' => 'pelayan',
                        'is_active' => true,
                    ]);
                } catch (\Throwable) {
                    return redirect('/_audit-test/view?error=save_failed');
                }

                return response('ok');
            })->name('audit-test.caught-audit-failure');
            Route::post('/_audit-test/user/{user}/password', static function (User $user, Request $request) {
                $user->forceFill(['password' => Hash::make((string) $request->input('password'))])->save();

                return response('ok');
            })->name('audit-test.user.password');
            Route::post('/_audit-test/file-mutation', static function (ActivityRecorder $activity) {
                $path = storage_path('framework/testing/activity-audit-rollback.txt');
                File::ensureDirectoryExists(dirname($path));
                File::put($path, 'temporary');
                $activity->onRollback(static fn () => File::delete($path));
                User::query()->create([
                    'username' => 'file_rollback',
                    'password' => Hash::make('secret'),
                    'branch_id' => null,
                    'access_scope' => 'pelayan',
                    'is_active' => true,
                ]);

                return response('ok');
            })->name('audit-test.file-mutation');
        });
    }

    private function createCoreTables(): void
    {
        Schema::create('branches', static function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->nullable()->unique();
            $table->string('password');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('access_scope', 80)->default('pemuridan_cabang');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('login_attempts', static function (Blueprint $table): void {
            $table->id();
            $table->string('attempt_key', 120)->unique();
            $table->unsignedInteger('failed_attempt_count')->default(0);
            $table->timestamp('window_started_at')->nullable();
            $table->timestamp('locked_until_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('app_configs', static function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->string('updated_by', 120)->nullable();
            $table->timestamps();
        });
        \DB::table('branches')->insert([
            'id' => 1,
            'label' => 'Kutisari',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function developer(): User
    {
        return User::query()->firstOrCreate(
            ['username' => 'developer'],
            [
                'password' => Hash::make('secret-dev'),
                'branch_id' => null,
                'access_scope' => 'developer',
                'is_active' => true,
            ],
        );
    }
}
