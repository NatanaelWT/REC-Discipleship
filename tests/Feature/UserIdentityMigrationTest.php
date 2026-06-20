<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserIdentityMigrationTest extends TestCase
{
    public function test_user_profile_columns_are_removed_without_changing_account_identity(): void
    {
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('access_scope');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('users')->insert([
            'username' => 'branch_user',
            'name' => 'Branch User',
            'email' => 'branch-user@rec.local',
            'email_verified_at' => now(),
            'password' => 'hashed-password',
            'branch_id' => 2,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_06_20_000003_remove_user_profile_columns.php');
        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'name'));
        $this->assertFalse(Schema::hasColumn('users', 'email'));
        $this->assertFalse(Schema::hasColumn('users', 'email_verified_at'));
        $this->assertDatabaseHas('users', [
            'username' => 'branch_user',
            'password' => 'hashed-password',
            'branch_id' => 2,
            'access_scope' => 'pemuridan_cabang',
            'is_active' => true,
        ]);
    }
}
