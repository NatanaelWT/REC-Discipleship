<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'password')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'password'])
            ->whereNotNull('password')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $password = (string) $user->password;
                    if ($this->looksLikeLaravelHash($password)) {
                        continue;
                    }

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['password' => Hash::make($password)]);
                }
            });
    }

    public function down(): void
    {
        // Password hashes cannot be safely converted back to plaintext.
    }

    private function looksLikeLaravelHash(string $password): bool
    {
        return str_starts_with($password, '$2y$')
            || str_starts_with($password, '$argon2i$')
            || str_starts_with($password, '$argon2id$');
    }
};
