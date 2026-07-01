<?php

namespace App\Services\Auth;

use App\Enums\UserAccessRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;

class DeveloperAccessSession
{
    private const ORIGINAL_USER_ID = 'developer_access.original_user_id';
    private const TARGET_USER_ID = 'developer_access.target_user_id';
    private const STARTED_AT = 'developer_access.started_at';

    /** @return array<int, string> */
    public static function keys(): array
    {
        return [self::ORIGINAL_USER_ID, self::TARGET_USER_ID, self::STARTED_AT];
    }

    public function start(Request $request, User $target): ?string
    {
        $original = $this->authenticatedUser();
        if (! $this->isActiveDeveloper($original)) {
            return 'access_denied';
        }

        if ((int) $original->getKey() === (int) $target->getKey()) {
            return 'access_self';
        }

        if (! (bool) ($target->is_active ?? true)) {
            return 'access_target_inactive';
        }

        if (UserAccessRole::fromStoredValue((string) $target->access_scope) === UserAccessRole::Developer) {
            return 'access_target_developer';
        }

        $request->session()->put([
            self::ORIGINAL_USER_ID => (int) $original->getKey(),
            self::TARGET_USER_ID => (int) $target->getKey(),
            self::STARTED_AT => now()->toIso8601String(),
        ]);
        $request->session()->forget(['developer_branch', 'developer_branch_id', 'central_rekap_branch_id']);

        return null;
    }

    public function stop(Request $request): void
    {
        $request->session()->forget(array_merge(
            self::keys(),
            ['developer_branch', 'developer_branch_id', 'central_rekap_branch_id'],
        ));
    }

    public function active(?User $authenticatedUser = null): bool
    {
        return $this->targetUser($authenticatedUser) instanceof User;
    }

    public function effectiveUser(?User $authenticatedUser = null): ?User
    {
        $authenticatedUser ??= $this->authenticatedUser();

        return $this->targetUser($authenticatedUser) ?? $authenticatedUser;
    }

    public function originalUser(?User $authenticatedUser = null): ?User
    {
        $authenticatedUser ??= $this->authenticatedUser();

        return $this->active($authenticatedUser) ? $authenticatedUser : null;
    }

    public function targetUser(?User $authenticatedUser = null): ?User
    {
        $authenticatedUser ??= $this->authenticatedUser();
        if (! $this->isActiveDeveloper($authenticatedUser)) {
            return null;
        }

        $session = $this->session();
        if (! $session instanceof Store) {
            return null;
        }

        $originalId = (int) $session->get(self::ORIGINAL_USER_ID, 0);
        if ($originalId !== (int) $authenticatedUser->getKey()) {
            return null;
        }

        $targetId = (int) $session->get(self::TARGET_USER_ID, 0);
        if ($targetId <= 0 || $targetId === $originalId) {
            return null;
        }

        $target = User::query()->find($targetId);
        if (! $target instanceof User || ! (bool) ($target->is_active ?? true)) {
            return null;
        }

        if (UserAccessRole::fromStoredValue((string) $target->access_scope) === UserAccessRole::Developer) {
            return null;
        }

        return $target;
    }

    public function originalUsername(): string
    {
        $original = $this->originalUser();

        return $original instanceof User ? trim((string) $original->username) : '';
    }

    public function targetUsername(): string
    {
        $target = $this->targetUser();

        return $target instanceof User ? trim((string) $target->username) : '';
    }

    public function startedAt(): string
    {
        $session = $this->session();

        return $session instanceof Store ? trim((string) $session->get(self::STARTED_AT, '')) : '';
    }

    /** @return array<string, mixed> */
    public function metadata(?User $target = null): array
    {
        $original = $this->authenticatedUser();
        $target ??= $this->targetUser($original);

        return [
            'original_user_id' => $original instanceof User ? (int) $original->getKey() : null,
            'original_username' => $original instanceof User ? trim((string) $original->username) : null,
            'target_user_id' => $target instanceof User ? (int) $target->getKey() : null,
            'target_username' => $target instanceof User ? trim((string) $target->username) : null,
        ];
    }

    private function authenticatedUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function isActiveDeveloper(?User $user): bool
    {
        return $user instanceof User
            && (bool) ($user->is_active ?? true)
            && UserAccessRole::fromStoredValue((string) $user->access_scope) === UserAccessRole::Developer;
    }

    private function session(): ?Store
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();
        if (! $request instanceof Request || ! $request->hasSession()) {
            return null;
        }

        return $request->session();
    }
}
