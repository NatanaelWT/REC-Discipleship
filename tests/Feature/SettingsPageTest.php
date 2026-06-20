<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    public function test_legacy_settings_query_is_rejected(): void
    {
        $response = $this->get('/index.php?page=settings');

        $response->assertNotFound();
    }

    public function test_settings_page_renders_for_logged_in_user(): void
    {
        $this->seedUserAccount();
        $this->loginAsSettingsUser();

        $response = $this->get('/pengaturan');

        $response->assertStatus(200);
        $response->assertSee('Kelola Password');
        $response->assertSee('admin_settings_test');
    }

    public function test_settings_password_can_be_updated(): void
    {
        $this->seedUserAccount();
        $this->loginAsSettingsUser();

        $response = $this->post('/pengaturan', [
            'current_password' => 'old-secret',
            'new_password' => 'new-secret',
            'new_password_confirm' => 'new-secret',
        ]);

        $response->assertRedirect('/pengaturan?pw_changed=1');
        $storedPassword = (string) DB::table('users')->where('username', 'admin_settings_test')->value('password');
        $this->assertNotSame('new-secret', $storedPassword);
        $this->assertTrue(Hash::check('new-secret', $storedPassword));
    }

    private function seedUserAccount(): void
    {
        $this->createUsersTable();

        DB::table('users')->updateOrInsert(
            ['username' => 'admin_settings_test'],
            [
                'name' => 'admin_settings_test',
                'email' => 'admin_settings_test@rec.local',
                'password' => 'old-secret',
                'branch_code' => 'kutisari',
                'access_scope' => 'pemuridan_cabang',
                'is_active' => true,
                'last_login_at' => null,
                'created_at' => '2026-06-13 08:00:00',
                'updated_at' => '2026-06-13 08:00:00',
            ],
        );
    }

    private function createUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->rememberToken();
            $table->string('branch_code', 40)->nullable()->index();
            $table->string('access_scope', 80)->default('pemuridan_cabang');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    private function loginAsSettingsUser(): void
    {
        $user = User::query()->where('username', 'admin_settings_test')->firstOrFail();

        $this->actingAs($user);
    }
}
