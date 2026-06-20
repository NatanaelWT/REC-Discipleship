<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserRoleMigrationTest extends TestCase
{
    public function test_legacy_roles_and_branch_scope_are_migrated(): void
    {
        $this->createLegacyTables();
        $this->seedLegacyRows();

        $migration = require database_path('migrations/2026_06_20_000001_restructure_user_roles_and_branch_scope.php');
        $migration->up();

        $this->assertDatabaseHas('users', [
            'username' => 'branch_user',
            'access_scope' => 'pemuridan_cabang',
            'branch_code' => 'gm',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'recpusat',
            'access_scope' => 'pemuridan_pusat',
            'branch_code' => null,
            'branch_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'keziaae',
            'access_scope' => 'pelayan',
            'branch_code' => null,
            'branch_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'developer',
            'access_scope' => 'developer',
            'branch_code' => null,
            'branch_id' => null,
        ]);
        $this->assertDatabaseMissing('branches', ['code' => 'pusat']);
        $this->assertDatabaseHas('worship_schedules', [
            'month' => '2026-06',
            'branch_code' => null,
            'branch_id' => null,
        ]);

        DB::table('users')->insert([
            'username' => 'central_without_branch',
            'name' => 'Central Without Branch',
            'email' => 'central-without-branch@rec.local',
            'password' => 'secret',
            'branch_code' => null,
            'branch_id' => null,
            'access_scope' => 'pemuridan_pusat',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('users', [
            'username' => 'central_without_branch',
            'branch_code' => null,
        ]);
    }

    private function createLegacyTables(): void
    {
        Schema::dropIfExists('worship_schedules');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('branch_code', 40)->default('kutisari')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('access_scope', 80)->default('branch');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('worship_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7);
            $table->string('title');
            $table->string('update_note')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('branch_code', 40)->nullable();
            $table->json('rows')->nullable();
            $table->timestamps();
            $table->unique(['branch_code', 'month'], 'worship_schedules_branch_month_unique');
        });
    }

    private function seedLegacyRows(): void
    {
        DB::table('branches')->insert([
            [
                'id' => 1,
                'code' => 'kutisari',
                'label' => 'Kutisari',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'gm',
                'label' => 'GM',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 7,
                'code' => 'pusat',
                'label' => 'Pusat',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('users')->insert([
            $this->legacyUser('branch_user', 'branch', 'gm', 2),
            $this->legacyUser('recpusat', 'central_discipleship_readonly', 'pusat', 7),
            $this->legacyUser('keziaae', 'worship_only', 'kutisari', 1),
            $this->legacyUser('developer', 'developer', 'kutisari', 1),
        ]);

        DB::table('worship_schedules')->insert([
            'month' => '2026-06',
            'title' => 'Jadwal Pelayan Juni',
            'update_note' => null,
            'branch_id' => 1,
            'branch_code' => 'kutisari',
            'rows' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyUser(string $username, string $scope, string $branchCode, int $branchId): array
    {
        return [
            'username' => $username,
            'name' => $username,
            'email' => $username.'@rec.local',
            'password' => 'secret',
            'branch_code' => $branchCode,
            'branch_id' => $branchId,
            'access_scope' => $scope,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
