<?php

namespace App\Services\Developer;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DeveloperUserService
{
    public function __construct(private readonly DeveloperBranchService $branches) {}

    /**
     * @return array<string, string>
     */
    public function scopeOptions(): array
    {
        return [
            'branch' => 'Cabang Pemuridan',
            'central_discipleship_readonly' => 'Pusat Pemuridan',
            'worship_only' => 'Ibadah Umum',
            'developer' => 'Developer',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): ?string
    {
        $username = $this->normalizeUsername((string) ($input['username'] ?? ''));
        $name = $this->normalizeText((string) ($input['name'] ?? ''), 120);
        $email = $this->normalizeEmail((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $branch = $this->branches->normalizeAllowed((string) ($input['branch_code'] ?? 'kutisari'));
        $scope = normalize_auth_access_scope((string) ($input['access_scope'] ?? 'branch'));
        $isActive = $this->boolFromInput($input['is_active'] ?? '1');

        if ($username === '' || $name === '' || $email === '' || trim($password) === '') {
            return 'missing_required';
        }
        if (! preg_match('/^[A-Za-z0-9_.-]{3,120}$/', $username)) {
            return 'username_invalid';
        }
        if (strlen($password) < 6) {
            return 'password_short';
        }
        if (! isset($this->scopeOptions()[$scope])) {
            return 'scope_invalid';
        }
        if ($branch === null) {
            return 'branch_invalid';
        }
        if (User::query()->where('username', $username)->exists()) {
            return 'username_taken';
        }
        if (User::query()->where('email', $email)->exists()) {
            return 'email_taken';
        }

        User::query()->create([
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'branch_code' => $branch,
            'access_scope' => $scope,
            'is_active' => $isActive,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input, string $actorUsername): ?string
    {
        $name = $this->normalizeText((string) ($input['name'] ?? ''), 120);
        $email = $this->normalizeEmail((string) ($input['email'] ?? ''));
        $branch = $this->branches->normalizeAllowed((string) ($input['branch_code'] ?? 'kutisari'));
        $scope = normalize_auth_access_scope((string) ($input['access_scope'] ?? 'branch'));
        $isActive = $this->boolFromInput($input['is_active'] ?? '0');
        $actorUsername = trim($actorUsername);
        $isSelf = $actorUsername !== '' && hash_equals($actorUsername, (string) $user->username);

        if ($name === '' || $email === '') {
            return 'missing_required';
        }
        if (! isset($this->scopeOptions()[$scope])) {
            return 'scope_invalid';
        }
        if ($branch === null) {
            return 'branch_invalid';
        }
        if ($isSelf && ! $isActive) {
            return 'self_deactivate';
        }
        if (User::query()->where('email', $email)->whereKeyNot($user->getKey())->exists()) {
            return 'email_taken';
        }
        if ($this->wouldRemoveLastActiveDeveloper($user, $scope, $isActive)) {
            return 'last_active_developer';
        }

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'branch_code' => $branch,
            'access_scope' => $scope,
            'is_active' => $isActive,
        ])->save();

        if ($isSelf) {
            if ($scope !== 'developer') {
                session()->forget('developer_branch');
            } else {
                session()->put('developer_branch', $branch);
            }
        }

        return null;
    }

    public function resetPassword(User $user, string $password, string $actorUsername): ?string
    {
        $actorUsername = trim($actorUsername);
        if ($actorUsername !== '' && hash_equals($actorUsername, (string) $user->username)) {
            return 'self_password_reset';
        }
        if (strlen($password) < 6) {
            return 'password_short';
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        return null;
    }

    /**
     * @return array{status:string,username:string,email:string}
     */
    public function ensureDeveloperUserFromEnvironment(): array
    {
        $password = trim((string) env('DEVELOPER_PASSWORD', ''));
        $username = $this->normalizeUsername((string) env('DEVELOPER_USERNAME', 'developer'));
        $email = $this->normalizeEmail((string) env('DEVELOPER_EMAIL', $username.'@rec.local'));

        if ($password === '') {
            return ['status' => 'missing_password', 'username' => $username, 'email' => $email];
        }
        if ($username === '') {
            $username = 'developer';
        }
        if ($email === '') {
            $email = $username.'@rec.local';
        }

        User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => 'Developer',
                'email' => $email,
                'password' => Hash::make($password),
                'branch_code' => 'kutisari',
                'access_scope' => 'developer',
                'is_active' => true,
            ],
        );

        return ['status' => 'ensured', 'username' => $username, 'email' => $email];
    }

    private function wouldRemoveLastActiveDeveloper(User $user, string $newScope, bool $newIsActive): bool
    {
        $wasActiveDeveloper = normalize_auth_access_scope((string) $user->access_scope) === 'developer'
            && (bool) ($user->is_active ?? true);
        $willBeActiveDeveloper = $newScope === 'developer' && $newIsActive;

        if (! $wasActiveDeveloper || $willBeActiveDeveloper) {
            return false;
        }

        return ! User::query()
            ->where('access_scope', 'developer')
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    private function normalizeUsername(string $username): string
    {
        return function_exists('mb_substr')
            ? mb_substr(trim($username), 0, 120)
            : substr(trim($username), 0, 120);
    }

    private function normalizeText(string $value, int $maxLength): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
    }

    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($email, 0, 255) : substr($email, 0, 255);
    }

    private function boolFromInput(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
