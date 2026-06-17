<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        parent::tearDown();
    }

    public function test_login_page_renders_from_laravel_view(): void
    {
        $this->createAuthTables();

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Portal Internal REC');
        $response->assertSee('/login', false);
    }

    public function test_user_can_login_with_existing_credentials(): void
    {
        $this->createAuthTables();
        $this->seedUser();

        $response = $this->post('/login', [
            'username' => 'auth_user_test',
            'password' => 'secret-test',
        ]);

        $response->assertRedirect('/pemuridan/dashboard');
        $this->assertSame('auth_user_test', $_SESSION['user'] ?? null);
        $this->assertSame('kutisari', $_SESSION['cabang'] ?? null);
        $this->assertSame('branch', $_SESSION['access_scope'] ?? null);
        $this->assertDatabaseHas('users', [
            'username' => 'auth_user_test',
            'branch_code' => 'kutisari',
        ]);
    }

    public function test_invalid_login_is_rate_limited_after_five_failures(): void
    {
        $this->createAuthTables();
        $this->seedUser();

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'username' => 'auth_user_test',
                'password' => 'wrong-password',
            ])->assertRedirect('/login?error=1');
        }

        $this->post('/login', [
            'username' => 'auth_user_test',
            'password' => 'wrong-password',
        ])->assertRedirectContains('/login?error=locked&wait=');

        $this->assertDatabaseHas('login_attempts', [
            'failed_attempt_count' => 0,
        ]);
    }

    private function createAuthTables(): void
    {
        Schema::dropIfExists('login_attempts');
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
    }

    private function seedUser(): void
    {
        DB::table('users')->insert([
            'username' => 'auth_user_test',
            'name' => 'auth_user_test',
            'email' => 'auth_user_test@rec.local',
            'password' => 'secret-test',
            'branch_code' => 'kutisari',
            'access_scope' => 'branch',
            'created_at' => '2026-06-14 08:00:00',
            'updated_at' => '2026-06-14 08:00:00',
        ]);
    }
}
