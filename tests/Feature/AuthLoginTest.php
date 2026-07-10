<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Branches\BranchCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
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
        $this->assertAuthenticatedAs(User::query()->where('username', 'auth_user_test')->firstOrFail());
        $this->assertSame('auth_user_test', current_username());
        $this->assertSame('kutisari', current_user_branch());
        $this->assertSame('pemuridan_cabang', current_auth_access_scope());
        $this->assertDatabaseHas('users', [
            'username' => 'auth_user_test',
            'branch_id' => 1,
        ]);
    }

    public function test_logout_invalidates_laravel_auth_session(): void
    {
        $this->createAuthTables();
        $this->seedUser();

        $this->actingAs(User::query()->where('username', 'auth_user_test')->firstOrFail());

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_inactive_authenticated_user_is_logged_out_from_login_page(): void
    {
        $this->createAuthTables();
        $this->seedUser(['is_active' => false]);

        $this->actingAs(User::query()->where('username', 'auth_user_test')->firstOrFail());

        $response = $this->get('/login');

        $response->assertRedirect('/login?account_removed=1');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->createAuthTables();
        $this->seedUser(['is_active' => false]);

        $response = $this->post('/login', [
            'username' => 'auth_user_test',
            'password' => 'secret-test',
        ]);

        $response->assertRedirect('/login?error=1');
        $this->assertGuest();
    }

    public function test_plaintext_stored_password_is_not_accepted(): void
    {
        $this->createAuthTables();
        $this->seedUser(['password' => 'secret-test']);

        $response = $this->post('/login', [
            'username' => 'auth_user_test',
            'password' => 'secret-test',
        ]);

        $response->assertRedirect('/login?error=1');
        $this->assertGuest();
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

        $this->assertDatabaseHas('percobaan_login', [
            'failed_attempt_count' => 0,
        ]);
    }

    private function createAuthTables(): void
    {
        Schema::dropIfExists('percobaan_login');
        Schema::dropIfExists('users');
        $this->createBranchesTable();

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

        Schema::create('percobaan_login', function (Blueprint $table): void {
            $table->id();
            $table->string('attempt_key', 120)->unique();
            $table->unsignedInteger('failed_attempt_count')->default(0);
            $table->timestamp('window_started_at')->nullable();
            $table->timestamp('locked_until_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();
        });
    }

    private function createBranchesTable(): void
    {
        Schema::dropIfExists('cabang');

        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('camp_gap_participant_target')->default(50);
            $table->unsignedInteger('msk_completion_target')->default(50);
            $table->unsignedInteger('dg1_completion_target')->default(50);
            $table->unsignedInteger('dg2_completion_target')->default(50);
            $table->unsignedInteger('dg3_completion_target')->default(50);
            $table->timestamps();
        });

        DB::table('cabang')->insert([
            ['id' => 1, 'label' => 'Kutisari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(BranchCatalog::class)->clearCache();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedUser(array $overrides = []): void
    {
        DB::table('users')->insert(array_merge([
            'username' => 'auth_user_test',
            'password' => Hash::make('secret-test'),
            'branch_id' => 1,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => true,
            'created_at' => '2026-06-14 08:00:00',
            'updated_at' => '2026-06-14 08:00:00',
        ], $overrides));
    }
}
