<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserPasswordHashMigrationTest extends TestCase
{
    public function test_plaintext_user_passwords_are_hashed_without_rehashing_existing_hashes(): void
    {
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 120)->unique();
            $table->string('password');
            $table->timestamps();
        });

        $existingHash = Hash::make('already-secret');
        DB::table('users')->insert([
            [
                'username' => 'plaintext_user',
                'password' => 'plain-secret',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'hashed_user',
                'password' => $existingHash,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path('migrations/2026_07_09_000001_hash_plaintext_user_passwords.php');
        $migration->up();
        $migration->up();

        $plainPassword = (string) DB::table('users')->where('username', 'plaintext_user')->value('password');
        $hashedPassword = (string) DB::table('users')->where('username', 'hashed_user')->value('password');

        $this->assertNotSame('plain-secret', $plainPassword);
        $this->assertTrue(Hash::check('plain-secret', $plainPassword));
        $this->assertSame($existingHash, $hashedPassword);
    }
}
