<?php

namespace App\Services\Developer;

use App\Enums\UserAccessRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DeveloperUserService
{
    public function __construct(private readonly DeveloperBranchService $branches) {}

    /**
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        return [
            UserAccessRole::DiscipleshipBranch->value => UserAccessRole::DiscipleshipBranch->label(),
            UserAccessRole::DiscipleshipCentral->value => UserAccessRole::DiscipleshipCentral->label(),
            UserAccessRole::Steward->value => UserAccessRole::Steward->label(),
            UserAccessRole::Developer->value => UserAccessRole::Developer->label(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): ?string
    {
        $username = $this->normalizeUsername((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $role = UserAccessRole::tryFrom(strtolower(trim((string) ($input['access_scope'] ?? ''))));
        $isActive = $this->boolFromInput($input['is_active'] ?? '1');

        if ($username === '' || trim($password) === '') {
            return 'missing_required';
        }
        if (! preg_match('/^[A-Za-z0-9_.-]{3,120}$/', $username)) {
            return 'username_invalid';
        }
        if (strlen($password) < 6) {
            return 'password_short';
        }
        if (! $role instanceof UserAccessRole) {
            return 'role_invalid';
        }

        $branchId = $role->requiresBranch()
            ? $this->branches->normalizeAllowedId($input['branch_id'] ?? null)
            : null;
        if ($role->requiresBranch() && $branchId === null) {
            return 'branch_invalid';
        }
        if (User::query()->where('username', $username)->exists()) {
            return 'username_taken';
        }
        User::query()->create(array_merge([
            'username' => $username,
            'password' => Hash::make($password),
            'access_scope' => $role->value,
            'is_active' => $isActive,
        ], $this->branchAttributes($branchId)));

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input, string $actorUsername): ?string
    {
        $role = UserAccessRole::tryFrom(strtolower(trim((string) ($input['access_scope'] ?? ''))));
        $isActive = $this->boolFromInput($input['is_active'] ?? '0');
        $actorUsername = trim($actorUsername);
        $isSelf = $actorUsername !== '' && hash_equals($actorUsername, (string) $user->username);

        if (! $role instanceof UserAccessRole) {
            return 'role_invalid';
        }

        $branchId = $role->requiresBranch()
            ? $this->branches->normalizeAllowedId($input['branch_id'] ?? null)
            : null;
        if ($role->requiresBranch() && $branchId === null) {
            return 'branch_invalid';
        }
        if ($isSelf && ! $isActive) {
            return 'self_deactivate';
        }
        if ($this->wouldRemoveLastActiveDeveloper($user, $role, $isActive)) {
            return 'last_active_developer';
        }

        $user->forceFill(array_merge([
            'access_scope' => $role->value,
            'is_active' => $isActive,
        ], $this->branchAttributes($branchId)))->save();

        if ($isSelf) {
            session()->forget(['developer_branch', 'developer_branch_id']);
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
     * @return array{status:string,username:string}
     */
    public function ensureDeveloperUserFromEnvironment(): array
    {
        $password = trim((string) env('DEVELOPER_PASSWORD', ''));
        $username = $this->normalizeUsername((string) env('DEVELOPER_USERNAME', 'developer'));

        if ($password === '') {
            return ['status' => 'missing_password', 'username' => $username];
        }
        if ($username === '') {
            $username = 'developer';
        }

        $attributes = [
            'password' => Hash::make($password),
            'access_scope' => UserAccessRole::Developer->value,
            'is_active' => true,
        ];

        User::query()->updateOrCreate(
            ['username' => $username],
            array_merge($attributes, $this->branchAttributes(null)),
        );

        return ['status' => 'ensured', 'username' => $username];
    }

    private function wouldRemoveLastActiveDeveloper(User $user, UserAccessRole $newRole, bool $newIsActive): bool
    {
        $wasActiveDeveloper = UserAccessRole::fromStoredValue((string) $user->access_scope) === UserAccessRole::Developer
            && (bool) ($user->is_active ?? true);
        $willBeActiveDeveloper = $newRole === UserAccessRole::Developer && $newIsActive;

        if (! $wasActiveDeveloper || $willBeActiveDeveloper) {
            return false;
        }

        return ! User::query()
            ->where('access_scope', 'developer')
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function branchAttributes(?int $branchId): array
    {
        return ['branch_id' => $branchId];
    }

    private function normalizeUsername(string $username): string
    {
        return function_exists('mb_substr')
            ? mb_substr(trim($username), 0, 120)
            : substr(trim($username), 0, 120);
    }

    private function boolFromInput(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
