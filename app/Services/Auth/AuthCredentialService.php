<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;

class AuthCredentialService
{
    public function attempt(string $username, string $password): ?User
    {
        $user = $this->findByUsername($username);
        if (! $user instanceof User) {
            return null;
        }

        if (! (bool) ($user->is_active ?? true)) {
            return null;
        }

        return $this->passwordMatches((string) $user->password, $password) ? $user : null;
    }

    public function findByUsername(string $username): ?User
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        return User::query()->where('username', $username)->first();
    }

    public function passwordMatches(string $storedPassword, string $inputPassword): bool
    {
        if ($this->looksLikeLaravelHash($storedPassword) && Hash::check($inputPassword, $storedPassword)) {
            return true;
        }

        return false;
    }

    public function updateLastLogin(User $user, CarbonInterface $loginAt): void
    {
        $user->forceFill(['last_login_at' => $loginAt])->save();
    }

    private function looksLikeLaravelHash(string $password): bool
    {
        return str_starts_with($password, '$2y$')
            || str_starts_with($password, '$argon2i$')
            || str_starts_with($password, '$argon2id$');
    }

}
