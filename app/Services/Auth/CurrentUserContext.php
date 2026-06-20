<?php

namespace App\Services\Auth;

use App\Enums\UserAccessRole;
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

    public function role(): UserAccessRole
    {
        if (! $this->isLoggedIn()) {
            return UserAccessRole::DiscipleshipBranch;
        }

        return UserAccessRole::fromStoredValue((string) ($this->user()?->access_scope ?? ''));
    }

    public function featureRole(): string
    {
        return match ($this->role()) {
            UserAccessRole::Developer => 'developer',
            UserAccessRole::Steward => 'pelayan',
            UserAccessRole::DiscipleshipBranch, UserAccessRole::DiscipleshipCentral => 'pemuridan',
        };
    }

    public function branch(): ?string
    {
        if ($this->isDeveloper()) {
            $developerBranch = normalize_user_branch((string) Session::get('developer_branch', ''));
            if ($developerBranch === '') {
                $developerBranch = 'kutisari';
            }

            return $developerBranch;
        }

        if (! $this->isDiscipleshipBranch()) {
            return null;
        }

        $branch = normalize_user_branch((string) ($this->user()?->branch_code ?? ''));

        return $branch !== '' ? $branch : null;
    }

    public function accessScope(): string
    {
        if (! $this->isLoggedIn()) {
            return UserAccessRole::DiscipleshipBranch->value;
        }

        return $this->role()->value;
    }

    public function isDeveloper(): bool
    {
        return $this->isLoggedIn() && $this->role() === UserAccessRole::Developer;
    }

    public function isDiscipleshipBranch(): bool
    {
        return $this->isLoggedIn() && $this->role() === UserAccessRole::DiscipleshipBranch;
    }

    public function isDiscipleshipCentral(): bool
    {
        return $this->isLoggedIn() && $this->role() === UserAccessRole::DiscipleshipCentral;
    }

    public function isSteward(): bool
    {
        return $this->isLoggedIn() && $this->role() === UserAccessRole::Steward;
    }

    public function isCentralDiscipleshipReadonly(): bool
    {
        return $this->isDiscipleshipCentral();
    }

    public function canAccessStewardship(): bool
    {
        return $this->isLoggedIn() && $this->role()->canAccessStewardship();
    }

    public function canAccessWorship(): bool
    {
        return $this->canAccessStewardship();
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
            return $this->canAccessStewardship();
        }

        if ($this->isSteward()) {
            return isset(worship_only_page_map()[$page]);
        }

        if ($this->isDiscipleshipBranch()) {
            return isset(restricted_branch_page_map()[$page]);
        }

        if ($this->isDiscipleshipCentral()) {
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
            return $this->canAccessStewardship();
        }

        if ($this->isSteward()) {
            return isset(worship_only_action_map()[$action]);
        }

        if ($this->isDiscipleshipBranch()) {
            return isset(restricted_branch_action_map()[$action]);
        }

        if ($this->isDiscipleshipCentral()) {
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

        if ($this->canAccessStewardship()) {
            return 'worship_penatalayan';
        }

        return 'discipleship_dashboard';
    }

    /**
     * @return array<int, string>
     */
    private function secureUploadPrefixes(): array
    {
        if ($this->isSteward()) {
            return [];
        }

        if ($this->isDiscipleshipBranch() || $this->isDiscipleshipCentral()) {
            return restricted_secure_upload_prefixes();
        }

        return [];
    }
}
