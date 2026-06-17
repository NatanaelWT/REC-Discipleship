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
        if (hash_equals($storedPassword, $inputPassword)) {
            return true;
        }

        if ($this->looksLikeLaravelHash($storedPassword) && Hash::check($inputPassword, $storedPassword)) {
            return true;
        }

        return false;
    }

    public function updateLastLogin(User $user, CarbonInterface $loginAt): void
    {
        $user->forceFill(['last_login_at' => $loginAt])->save();
    }

    /**
     * @return array{username:string,cabang:string,access_scope:string}
     */
    public function sessionPayload(User $user): array
    {
        return [
            'username' => (string) $user->username,
            'cabang' => $this->normalizeBranch((string) ($user->branch_code ?? 'kutisari')),
            'access_scope' => $this->normalizeScope((string) ($user->access_scope ?? 'branch')),
        ];
    }

    private function looksLikeLaravelHash(string $password): bool
    {
        return str_starts_with($password, '$2y$')
            || str_starts_with($password, '$argon2i$')
            || str_starts_with($password, '$argon2id$');
    }

    private function normalizeBranch(string $branch): string
    {
        return function_exists('normalize_user_branch')
            ? normalize_user_branch($branch)
            : (in_array(strtolower(trim($branch)), ['kutisari', 'gm', 'darmo', 'merr', 'batam', 'nginden', 'pusat'], true) ? strtolower(trim($branch)) : 'kutisari');
    }

    private function normalizeScope(string $scope): string
    {
        return function_exists('normalize_auth_access_scope')
            ? normalize_auth_access_scope($scope)
            : (in_array(strtolower(trim($scope)), ['branch', 'worship_only', 'central_discipleship_readonly'], true) ? strtolower(trim($scope)) : 'branch');
    }
}
