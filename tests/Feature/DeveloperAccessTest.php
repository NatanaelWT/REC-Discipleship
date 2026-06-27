<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Developer\DeveloperDiagnosticsService;
use App\Support\RuntimeBootstrap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeveloperAccessTest extends TestCase
{
    public function test_developer_login_redirects_to_developer_dashboard(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();

        $response = $this->post('/login', [
            'username' => 'developer',
            'password' => 'secret-dev',
        ]);

        $response->assertRedirect('/developer');
        $this->assertAuthenticatedAs(User::query()->where('username', 'developer')->firstOrFail());
        $this->assertSame('developer', current_auth_access_scope());
        $this->assertSame('', current_user_branch());
    }

    public function test_non_developer_cannot_open_developer_routes(): void
    {
        $this->createCoreTables();
        $this->seedUser('branch_user', 'pemuridan_cabang');
        $this->loginAs('branch_user');

        $this->get('/developer')->assertRedirect('/pemuridan/dashboard?error=access_denied');
        $this->get('/developer/users')->assertRedirect('/pemuridan/dashboard?error=access_denied');
        $this->get('/developer/config')->assertRedirect('/pemuridan/dashboard?error=access_denied');
        $this->get('/developer/statistics')->assertRedirect('/pemuridan/dashboard?error=access_denied');
    }

    public function test_developer_bypasses_existing_access_gates(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');
        RuntimeBootstrap::load();

        $this->assertTrue(branch_can_access_page(current_user_branch(), 'discipleship_dashboard'));
        $this->assertTrue(branch_can_access_page(current_user_branch(), 'worship_penatalayan'));
        $this->assertTrue(branch_can_use_action(current_user_branch(), 'save_worship_penatalayan'));
        $this->assertFalse(branch_can_use_action(current_user_branch(), 'save_person'));
        $this->assertTrue(is_effective_central_discipleship_readonly());
        $this->assertTrue(can_manage_public_materials());
        $this->assertTrue(can_manage_difficult_questions());
        $this->assertTrue(branch_can_access_secure_upload_path(current_user_branch(), 'restricted/example.pdf'));
    }

    public function test_developer_has_no_active_branch_or_branch_switch_route(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        session()->put('developer_branch_id', 2);
        session()->put('developer_branch', 'gm');

        $this->get('/developer')
            ->assertOk()
            ->assertSee('Pemuridan lintas cabang hanya lihat')
            ->assertDontSee('Cabang Aktif')
            ->assertDontSee('Pakai')
            ->assertDontSee('Mode Pusat')
            ->assertDontSee('Semua Cabang');

        $this->post('/developer/branch', ['branch_id' => 2])->assertNotFound();

        $this->assertNull(session('developer_branch_id'));
        $this->assertNull(session('developer_branch'));
        $this->assertSame('', current_user_branch());
    }

    public function test_developer_can_manage_users_and_reset_passwords(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/users', [
            'username' => 'managed_user',
            'password' => 'new-secret',
            'branch_id' => 2,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?status=created');

        $storedPassword = (string) DB::table('users')->where('username', 'managed_user')->value('password');
        $this->assertNotSame('new-secret', $storedPassword);
        $this->assertTrue(Hash::check('new-secret', $storedPassword));

        $managedUserId = (int) DB::table('users')->where('username', 'managed_user')->value('id');
        $this->post('/developer/users/'.$managedUserId, [
            'access_scope' => 'pelayan',
            'is_active' => '0',
        ])->assertRedirect('/developer/users?status=updated&user='.$managedUserId);

        $this->assertDatabaseHas('users', [
            'username' => 'managed_user',
            'branch_id' => null,
            'access_scope' => 'pelayan',
            'is_active' => false,
        ]);

        $this->post('/developer/users/'.$managedUserId.'/password', [
            'password' => 'reset-secret',
        ])->assertRedirect('/developer/users?status=password_reset&user='.$managedUserId);

        $resetPassword = (string) DB::table('users')->where('username', 'managed_user')->value('password');
        $this->assertTrue(Hash::check('reset-secret', $resetPassword));
    }

    public function test_developer_cannot_deactivate_self_or_remove_last_active_developer(): void
    {
        $this->createCoreTables();
        $developerId = $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/users/'.$developerId, [
            'access_scope' => 'developer',
            'is_active' => '0',
        ])->assertRedirect('/developer/users?error=self_deactivate&user='.$developerId);

        $this->post('/developer/users/'.$developerId, [
            'branch_id' => 1,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?error=last_active_developer&user='.$developerId);

        $this->assertDatabaseHas('users', [
            'username' => 'developer',
            'access_scope' => 'developer',
            'is_active' => true,
        ]);
    }

    public function test_developer_config_update_is_persisted_and_used(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/config', [
            'church_name' => 'REC Internal',
            'app_timezone' => 'Asia/Jakarta',
            'developer_debug_banner' => '1',
        ])->assertRedirect('/developer/config?status=saved');

        $this->assertDatabaseHas('app_configs', [
            'key' => 'church_name',
            'value' => 'REC Internal',
        ]);
        $this->assertDatabaseHas('app_configs', [
            'key' => 'developer_debug_banner',
            'value' => '1',
        ]);

        $this->get('/developer/config')
            ->assertOk()
            ->assertSee('REC Internal')
            ->assertSee('data-developer-header', false)
            ->assertSee('discipleship-page-header__stats', false)
            ->assertDontSee('developer-hub-nav', false)
            ->assertSee('class="btn developer-primary-action"', false);
        $this->get('/developer')
            ->assertOk()
            ->assertSee('Developer debug aktif');
    }

    public function test_user_management_uses_roles_and_has_no_central_branch_option(): void
    {
        $this->createCoreTables();
        $developerId = $this->seedDeveloper();
        $branchUserId = $this->seedUser('branch_user', 'pemuridan_cabang');
        $this->loginAs('developer');

        $this->get('/developer/users')
            ->assertOk()
            ->assertSee('data-developer-header', false)
            ->assertSee('discipleship-page-header__stats', false)
            ->assertDontSee('developer-hub-nav', false)
            ->assertSee('Role')
            ->assertSee('value="pemuridan_cabang"', false)
            ->assertSee('value="pemuridan_pusat"', false)
            ->assertSee('value="pelayan"', false)
            ->assertSee('Tanpa cabang')
            ->assertSee('branch_user')
            ->assertSee('<details class="developer-user-item" data-developer-user', false)
            ->assertSee('<summary class="developer-user-toggle">', false)
            ->assertSee('<strong>branch_user</strong>', false)
            ->assertDontSee(route('developer.users.update', $developerId), false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('value="pusat"', false);

        $this->get('/developer/users?user='.$branchUserId)
            ->assertOk()
            ->assertSee('<details class="developer-user-item" data-developer-user open>', false);
    }

    public function test_developer_diagnostics_counts_only_active_discipleship_branches(): void
    {
        $this->createCoreTables();

        DB::table('branches')->insert([
            [
                'label' => 'Pusat',
                'is_active' => true,
                'created_at' => '2026-06-19 08:00:00',
                'updated_at' => '2026-06-19 08:00:00',
            ],
            [
                'label' => 'Inactive',
                'is_active' => false,
                'created_at' => '2026-06-19 08:00:00',
                'updated_at' => '2026-06-19 08:00:00',
            ],
        ]);

        $summary = app(DeveloperDiagnosticsService::class)->summary();

        $this->assertSame(3, $summary['counts']['branches']);
    }

    public function test_developer_dashboard_shows_operational_overview_and_omits_diagnostics(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $branchUserId = $this->seedUser('branch_user', 'pemuridan_cabang');
        $this->createDashboardTelemetryTables();
        $this->seedDashboardTelemetry($branchUserId);
        $this->loginAs('developer');

        $summary = app(DeveloperDiagnosticsService::class)->summary();

        $this->assertArrayNotHasKey('storage', $summary);
        $this->assertArrayNotHasKey('materials', $summary);

        $this->get('/developer')
            ->assertOk()
            ->assertSee('data-developer-header', false)
            ->assertSee('discipleship-page-header__stats', false)
            ->assertDontSee('developer-hub-nav', false)
            ->assertSee('Kondisi Aplikasi')
            ->assertSee('Kunjungan Publik')
            ->assertSee('Aktivitas Terbaru')
            ->assertSee('Perlu Dicek')
            ->assertSee('Total Request')
            ->assertSee('Error 5xx')
            ->assertSee('/pemuridan/anggota', false)
            ->assertSee('/pemuridan/gagal', false)
            ->assertSee('/publik/materi', false)
            ->assertDontSee('Aktif 5 Menit')
            ->assertDontSee('Akses Cepat')
            ->assertDontSee('Navigasi')
            ->assertDontSee('Diagnostics')
            ->assertDontSee('Runtime')
            ->assertDontSee('<small>/developer/config</small>', false)
            ->assertDontSee('/publik/bot', false)
            ->assertDontSee('Material Audit')
            ->assertDontSee('Public storage')
            ->assertDontSee('Path target');
    }

    public function test_steward_has_no_branch_and_cannot_access_discipleship_or_developer(): void
    {
        $this->createCoreTables();
        $this->seedUser('keziaae', 'pelayan', ['branch_id' => null]);
        $this->loginAs('keziaae');
        RuntimeBootstrap::load();

        $this->assertSame('', current_user_branch());
        $this->assertTrue(current_user_can_access_worship());
        $this->assertFalse(branch_can_access_page('', 'discipleship_dashboard'));
        $this->assertTrue(branch_can_access_page('', 'worship_penatalayan'));
        $this->get('/pemuridan/dashboard')->assertRedirect('/ibadah/penatalayan?error=access_denied');
        $this->get('/developer')->assertRedirect('/ibadah/penatalayan?error=access_denied');
    }

    public function test_discipleship_branch_role_requires_a_branch(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer');

        $this->post('/developer/users', [
            'username' => 'missing_branch',
            'password' => 'new-secret',
            'access_scope' => 'pemuridan_cabang',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?error=branch_invalid');

        $this->assertDatabaseMissing('users', ['username' => 'missing_branch']);
    }

    private function createCoreTables(): void
    {
        Schema::dropIfExists('website_page_views');
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('activity_requests');
        Schema::dropIfExists('app_configs');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->nullable()->unique();
            $table->string('password');
            $table->rememberToken();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('access_scope', 80)->default('pemuridan_cabang');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('attempt_key', 120)->unique();
            $table->unsignedInteger('failed_attempt_count')->default(0);
            $table->timestamp('window_started_at')->nullable();
            $table->timestamp('locked_until_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('app_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->string('updated_by', 120)->nullable();
            $table->timestamps();
        });

        foreach (['Kutisari', 'GM', 'Darmo'] as $label) {
            DB::table('branches')->insert([
                'label' => $label,
                'is_active' => true,
                'created_at' => '2026-06-19 08:00:00',
                'updated_at' => '2026-06-19 08:00:00',
            ]);
        }
    }

    private function createDashboardTelemetryTables(): void
    {
        Schema::dropIfExists('website_page_views');
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('activity_requests');

        Schema::create('activity_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('actor_type', 20)->default('anonymous');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username', 120)->nullable();
            $table->string('role', 80)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('branch_label', 160)->nullable();
            $table->char('visitor_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('method', 12);
            $table->string('route_name', 180)->nullable();
            $table->text('path');
            $table->string('category', 60)->default('request');
            $table->string('action', 180)->default('request');
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 191)->nullable();
            $table->json('query_data')->nullable();
            $table->json('input_data')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome', 30)->default('success');
            $table->text('redirect_to')->nullable();
            $table->string('response_content_type', 180)->nullable();
            $table->unsignedBigInteger('response_size')->nullable();
            $table->decimal('duration_ms', 14, 3)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->string('error_type', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at', 6)->index();
            $table->dateTime('completed_at', 6)->nullable();
        });

        Schema::create('activity_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('request_id');
            $table->string('category', 60);
            $table->string('action', 180);
            $table->string('subject_label', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('changed_values')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->index(['request_id', 'id']);
        });

        Schema::create('website_page_views', function (Blueprint $table): void {
            $table->ulid('request_id')->primary();
            $table->ulid('session_id')->index();
            $table->char('visitor_hash', 64)->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('segment', 30)->index();
            $table->string('route_name', 180)->nullable();
            $table->text('path');
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_prefetch')->default(false);
            $table->decimal('response_ms', 14, 3)->nullable();
            $table->dateTime('occurred_at', 6)->index();
        });
    }

    private function seedDashboardTelemetry(int $branchUserId): void
    {
        $recentId = (string) Str::ulid();
        $developerId = (string) Str::ulid();
        $errorId = (string) Str::ulid();
        $now = now('UTC');

        DB::table('activity_requests')->insert([
            [
                'id' => $recentId,
                'actor_type' => 'user',
                'user_id' => $branchUserId,
                'username' => 'branch_user',
                'role' => 'pemuridan_cabang',
                'method' => 'POST',
                'route_name' => 'discipleship.people-list',
                'path' => '/pemuridan/anggota',
                'category' => 'data',
                'action' => 'people.update',
                'http_status' => 200,
                'outcome' => 'success',
                'duration_ms' => 84.5,
                'started_at' => $now->copy()->subMinutes(20)->format('Y-m-d H:i:s.u'),
                'completed_at' => $now->copy()->subMinutes(20)->addMilliseconds(85)->format('Y-m-d H:i:s.u'),
            ],
            [
                'id' => $developerId,
                'actor_type' => 'user',
                'user_id' => 1,
                'username' => 'developer',
                'role' => 'developer',
                'method' => 'GET',
                'route_name' => 'developer.config',
                'path' => '/developer/config',
                'category' => 'developer',
                'action' => 'config.open',
                'http_status' => 200,
                'outcome' => 'success',
                'duration_ms' => 33.1,
                'started_at' => $now->copy()->subMinutes(10)->format('Y-m-d H:i:s.u'),
                'completed_at' => $now->copy()->subMinutes(10)->addMilliseconds(34)->format('Y-m-d H:i:s.u'),
            ],
            [
                'id' => $errorId,
                'actor_type' => 'user',
                'user_id' => $branchUserId,
                'username' => 'branch_user',
                'role' => 'pemuridan_cabang',
                'method' => 'GET',
                'route_name' => 'discipleship.error',
                'path' => '/pemuridan/gagal',
                'category' => 'request',
                'action' => 'request.failed',
                'http_status' => 500,
                'outcome' => 'error',
                'duration_ms' => 140.2,
                'started_at' => $now->copy()->subMinutes(5)->format('Y-m-d H:i:s.u'),
                'completed_at' => $now->copy()->subMinutes(5)->addMilliseconds(141)->format('Y-m-d H:i:s.u'),
            ],
        ]);

        DB::table('activity_events')->insert([
            ['request_id' => $recentId, 'category' => 'data', 'action' => 'people.updated', 'subject_label' => 'Peserta Test', 'description' => 'Updated person', 'occurred_at' => $now->copy()->subMinutes(20)->format('Y-m-d H:i:s.u')],
            ['request_id' => $recentId, 'category' => 'data', 'action' => 'audit.logged', 'subject_label' => 'Peserta Test', 'description' => 'Audit logged', 'occurred_at' => $now->copy()->subMinutes(20)->format('Y-m-d H:i:s.u')],
            ['request_id' => $errorId, 'category' => 'request', 'action' => 'request.failed', 'subject_label' => 'Error Test', 'description' => 'Failure logged', 'occurred_at' => $now->copy()->subMinutes(5)->format('Y-m-d H:i:s.u')],
        ]);

        DB::table('website_page_views')->insert([
            [
                'request_id' => (string) Str::ulid(),
                'session_id' => (string) Str::ulid(),
                'visitor_hash' => str_repeat('a', 64),
                'user_id' => null,
                'segment' => 'publik',
                'route_name' => 'public.materials',
                'path' => '/publik/materi',
                'is_bot' => false,
                'is_prefetch' => false,
                'response_ms' => 52.4,
                'occurred_at' => $now->copy()->subDay()->format('Y-m-d H:i:s.u'),
            ],
            [
                'request_id' => (string) Str::ulid(),
                'session_id' => (string) Str::ulid(),
                'visitor_hash' => str_repeat('b', 64),
                'user_id' => null,
                'segment' => 'publik',
                'route_name' => 'public.bot',
                'path' => '/publik/bot',
                'is_bot' => true,
                'is_prefetch' => false,
                'response_ms' => 20.0,
                'occurred_at' => $now->copy()->subDay()->format('Y-m-d H:i:s.u'),
            ],
            [
                'request_id' => (string) Str::ulid(),
                'session_id' => (string) Str::ulid(),
                'visitor_hash' => str_repeat('c', 64),
                'user_id' => null,
                'segment' => 'publik',
                'route_name' => 'public.prefetch',
                'path' => '/publik/prefetch',
                'is_bot' => false,
                'is_prefetch' => true,
                'response_ms' => 19.0,
                'occurred_at' => $now->copy()->subDay()->format('Y-m-d H:i:s.u'),
            ],
        ]);
    }

    private function seedDeveloper(): int
    {
        return $this->seedUser('developer', 'developer', [
            'password' => Hash::make('secret-dev'),
            'branch_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedUser(string $username, string $scope, array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => $username,
            'password' => 'secret-test',
            'branch_id' => $scope === 'pemuridan_cabang' ? 1 : null,
            'access_scope' => $scope,
            'is_active' => true,
            'created_at' => '2026-06-19 08:00:00',
            'updated_at' => '2026-06-19 08:00:00',
        ], $overrides));
    }

    private function loginAs(string $username): void
    {
        $user = User::query()->where('username', $username)->firstOrFail();

        $this->actingAs($user);
    }
}
