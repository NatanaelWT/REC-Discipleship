<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CurrentUserContext
{
    public function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function isLoggedIn(): bool
    {
        $user = $this->user();

        return $user instanceof User && (bool) ($user->is_active ?? true);
    }

    public function username(): string
    {
        return $this->isLoggedIn() ? trim((string) $this->user()?->username) : '';
    }

    public function branch(): string
    {
        if ($this->isDeveloper()) {
            $developerBranch = trim((string) Session::get('developer_branch', ''));
            if ($developerBranch !== '') {
                return normalize_user_branch($developerBranch);
            }
        }

        $branch = $this->isLoggedIn() ? (string) ($this->user()?->branch_code ?? 'kutisari') : 'kutisari';

        return normalize_user_branch($branch);
    }

    public function accessScope(): string
    {
        if (! $this->isLoggedIn()) {
            return 'branch';
        }

        return normalize_auth_access_scope((string) ($this->user()?->access_scope ?? 'branch'));
    }

    public function isDeveloper(): bool
    {
        return $this->isLoggedIn() && $this->accessScope() === 'developer';
    }

    public function isCentralDiscipleshipReadonly(): bool
    {
        return $this->isLoggedIn() && $this->accessScope() === 'central_discipleship_readonly';
    }

    public function canAccessWorship(): bool
    {
        return $this->isLoggedIn() && username_can_access_worship($this->username());
    }

    public function canAccessPage(string $page): bool
    {
        $page = trim($page);
        if ($page === '') {
            return true;
        }

        if ($this->isDeveloper()) {
            return true;
        }

        if (is_worship_page($page)) {
            return $this->canAccessWorship();
        }

        $scope = $this->accessScope();
        if (is_worship_only_scope($scope)) {
            return isset(worship_only_page_map()[$page]);
        }

        if (is_discipleship_branch_scope($scope)) {
            return isset(restricted_branch_page_map()[$page]);
        }

        if ($this->isCentralDiscipleshipReadonly()) {
            return isset(central_readonly_page_map()[$page]);
        }

        return true;
    }

    public function canUseAction(string $action): bool
    {
        $action = trim($action);
        if ($action === '') {
            return true;
        }

        if ($this->isDeveloper()) {
            return true;
        }

        if (is_worship_action($action)) {
            return $this->canAccessWorship();
        }

        if ($this->isCentralDiscipleshipReadonly() && is_discipleship_action($action)) {
            return false;
        }

        $scope = $this->accessScope();
        if (is_worship_only_scope($scope)) {
            return isset(worship_only_action_map()[$action]);
        }

        if (is_discipleship_branch_scope($scope)) {
            return isset(restricted_branch_action_map()[$action]);
        }

        if ($this->isCentralDiscipleshipReadonly()) {
            return isset(central_readonly_action_map()[$action]);
        }

        return true;
    }

    public function canAccessSecureUploadPath(string $path): bool
    {
        if ($this->isDeveloper()) {
            return true;
        }

        $allowedPrefixes = $this->secureUploadPrefixes();
        if ($allowedPrefixes === []) {
            return true;
        }

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function homePage(): string
    {
        if ($this->isDeveloper()) {
            return 'developer_dashboard';
        }

        if ($this->canAccessWorship()) {
            return 'worship_penatalayan';
        }

        return 'discipleship_dashboard';
    }

    /**
     * @return array<int, string>
     */
    private function secureUploadPrefixes(): array
    {
        $scope = $this->accessScope();
        if (is_worship_only_scope($scope)) {
            return [];
        }

        if (is_discipleship_branch_scope($scope) || $this->isCentralDiscipleshipReadonly()) {
            return restricted_secure_upload_prefixes();
        }

        return [];
    }
}
