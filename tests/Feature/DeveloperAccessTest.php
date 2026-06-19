<?php

namespace Tests\Feature;

use App\Support\RuntimeBootstrap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeveloperAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->restoreLegacySession();

        parent::tearDown();
    }

    public function test_developer_login_redirects_to_developer_dashboard(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();

        $response = $this->post('/login', [
            'username' => 'developer',
            'password' => 'secret-dev',
        ]);

        $response->assertRedirect('/developer');
        $this->assertSame('developer', $_SESSION['user'] ?? null);
        $this->assertSame('developer', $_SESSION['access_scope'] ?? null);
        $this->assertSame('kutisari', current_user_branch());
    }

    public function test_non_developer_cannot_open_developer_routes(): void
    {
        $this->createCoreTables();
        $this->seedUser('branch_user', 'branch_user@rec.local', 'branch');
        $this->loginAs('branch_user', 'branch');

        $this->get('/developer')->assertForbidden();
        $this->get('/developer/users')->assertForbidden();
        $this->get('/developer/config')->assertForbidden();
    }

    public function test_developer_bypasses_existing_access_gates(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer', 'developer');
        RuntimeBootstrap::load();

        $this->assertTrue(branch_can_access_page(current_user_branch(), 'discipleship_dashboard'));
        $this->assertTrue(branch_can_access_page(current_user_branch(), 'worship_penatalayan'));
        $this->assertTrue(branch_can_use_action(current_user_branch(), 'save_worship_penatalayan'));
        $this->assertTrue(can_manage_public_materials());
        $this->assertTrue(can_manage_difficult_questions());
        $this->assertTrue(branch_can_access_secure_upload_path(current_user_branch(), 'restricted/example.pdf'));
    }

    public function test_developer_can_switch_active_branch(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer', 'developer');

        $this->post('/developer/branch', ['branch_code' => 'gm'])
            ->assertRedirect('/developer?branch_changed=1');

        $this->assertSame('gm', $_SESSION['developer_branch'] ?? null);
        $this->assertSame('gm', current_user_branch());
    }

    public function test_developer_can_manage_users_and_reset_passwords(): void
    {
        $this->createCoreTables();
        $this->seedDeveloper();
        $this->loginAs('developer', 'developer');

        $this->post('/developer/users', [
            'username' => 'managed_user',
            'name' => 'Managed User',
            'email' => 'managed_user@rec.local',
            'password' => 'new-secret',
            'branch_code' => 'gm',
            'access_scope' => 'branch',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?status=created');

        $storedPassword = (string) DB::table('users')->where('username', 'managed_user')->value('password');
        $this->assertNotSame('new-secret', $storedPassword);
        $this->assertTrue(Hash::check('new-secret', $storedPassword));

        $managedUserId = (int) DB::table('users')->where('username', 'managed_user')->value('id');
        $this->post('/developer/users/' . $managedUserId, [
            'name' => 'Managed User Updated',
            'email' => 'managed_user_updated@rec.local',
            'branch_code' => 'darmo',
            'access_scope' => 'worship_only',
            'is_active' => '0',
        ])->assertRedirect('/developer/users?status=updated');

        $this->assertDatabaseHas('users', [
            'username' => 'managed_user',
            'name' => 'Managed User Updated',
            'email' => 'managed_user_updated@rec.local',
            'branch_code' => 'darmo',
            'access_scope' => 'worship_only',
            'is_active' => false,
        ]);

        $this->post('/developer/users/' . $managedUserId . '/password', [
            'password' => 'reset-secret',
        ])->assertRedirect('/developer/users?status=password_reset');

        $resetPassword = (string) DB::table('users')->where('username', 'managed_user')->value('password');
        $this->assertTrue(Hash::check('reset-secret', $resetPassword));
    }

    public function test_developer_cannot_deactivate_self_or_remove_last_active_developer(): void
    {
        $this->createCoreTables();
        $developerId = $this->seedDeveloper();
        $this->loginAs('developer', 'developer');

        $this->post('/developer/users/' . $developerId, [
            'name' => 'Developer',
            'email' => 'developer@rec.local',
            'branch_code' => 'kutisari',
            'access_scope' => 'developer',
            'is_active' => '0',
        ])->assertRedirect('/developer/users?error=self_deactivate');

        $this->post('/developer/users/' . $developerId, [
            'name' => 'Developer',
            'email' => 'developer@rec.local',
            'branch_code' => 'kutisari',
            'access_scope' => 'branch',
            'is_active' => '1',
        ])->assertRedirect('/developer/users?error=last_active_developer');

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
        $this->loginAs('developer', 'developer');

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
            ->assertSee('REC Internal');
        $this->get('/developer')
            ->assertOk()
            ->assertSee('Developer debug aktif');
    }

    private function createCoreTables(): void
    {
        Schema::dropIfExists('app_configs');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('branch_code', 40)->default('kutisari')->index();
            $table->string('access_scope', 80)->default('branch');
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
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
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

        foreach ([
            ['kutisari', 'Kutisari', 0],
            ['gm', 'GM', 1],
            ['darmo', 'Darmo', 2],
        ] as [$code, $label, $sortOrder]) {
            DB::table('branches')->insert([
                'code' => $code,
                'label' => $label,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => '2026-06-19 08:00:00',
                'updated_at' => '2026-06-19 08:00:00',
            ]);
        }
    }

    private function seedDeveloper(): int
    {
        return $this->seedUser('developer', 'developer@rec.local', 'developer', [
            'password' => Hash::make('secret-dev'),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedUser(string $username, string $email, string $scope, array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => $username,
            'name' => ucfirst(str_replace('_', ' ', $username)),
            'email' => $email,
            'password' => 'secret-test',
            'branch_code' => 'kutisari',
            'access_scope' => $scope,
            'is_active' => true,
            'created_at' => '2026-06-19 08:00:00',
            'updated_at' => '2026-06-19 08:00:00',
        ], $overrides));
    }

    private function loginAs(string $username, string $scope): void
    {
        $this->startLegacySession();

        $_SESSION['user'] = $username;
        $_SESSION['cabang'] = 'kutisari';
        $_SESSION['access_scope'] = $scope;
    }

    private function startLegacySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_id('developer-test-' . str_replace('.', '', uniqid('', true)));
            session_start();
        }
    }

    private function restoreLegacySession(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
