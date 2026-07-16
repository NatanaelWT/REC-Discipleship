<?php

namespace App\Services\Auth;

use App\Enums\UserAccessRole;
use App\Models\User;
use App\Services\Branches\BranchCatalog;
use Illuminate\Support\Facades\Auth;

class CurrentUserContext
{
    public function __construct(
        private readonly BranchCatalog $branches,
        private readonly DeveloperAccessSession $developerAccess,
    ) {}

    public function user(): ?User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        return $this->developerAccess->effectiveUser($user);
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
        $branch = $this->branches->slugForId($this->branchId());

        return $branch !== '' ? $branch : null;
    }

    public function branchId(): ?int
    {
        if ($this->isDeveloper()) {
            return $this->developerSelectedBranchId();
        }

        if (! $this->isDiscipleshipBranch()) {
            return null;
        }

        $branchId = $this->user()?->branch_id;

        if (! is_numeric($branchId) || (int) $branchId < 1 || ! $this->branches->isActiveId((int) $branchId)) {
            return null;
        }

        return (int) $branchId;
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

    public function isDiscipleshipPreviewReadonly(): bool
    {
        if ($this->isDeveloper()) {
            // The aggregate all-branch view has no unambiguous write target.
            // Once a developer selects a branch, production and experiment
            // branches are both fully editable.
            return $this->developerSelectedBranchId() === null;
        }

        return $this->isDiscipleshipCentral();
    }

    public function isDeveloperExperimentBranch(): bool
    {
        $branchId = $this->developerSelectedBranchId();

        return $branchId !== null && $this->branches->isInactiveId($branchId);
    }

    public function isDeveloperTestingBranch(): bool
    {
        return $this->isDeveloperExperimentBranch();
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
            if (is_worship_action($action) || $action === 'save_difficult_question_answer') {
                return true;
            }

            if ($this->developerSelectedBranchId() !== null) {
                return true;
            }

            return isset(central_readonly_action_map()[$action]);
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

    private function developerSelectedBranchId(): ?int
    {
        if (! $this->isDeveloper()) {
            return null;
        }

        $candidate = null;
        $request = request();
        if ($request->query->has('branch_id')) {
            $input = trim((string) $request->query('branch_id', ''));
            if ($input === '' || strtolower($input) === 'all') {
                if ($request->hasSession()) {
                    $request->session()->put('central_rekap_branch_id', 'all');
                }

                return null;
            }

            $candidate = filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        } elseif (! $request->isMethodSafe()) {
            // Developer writes must carry an explicit branch in the action URL.
            // This prevents a form opened in one browser tab from using a branch
            // selection that was changed later in another tab.
            return null;
        } else {
            $stored = session('central_rekap_branch_id', 'all');
            $candidate = is_numeric($stored)
                ? filter_var($stored, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : null;
        }

        if ($candidate === false || $candidate === null) {
            return null;
        }

        if (! $this->branches->isActiveId($candidate, true)) {
            return null;
        }

        if ($request->query->has('branch_id') && $request->hasSession()) {
            $request->session()->put('central_rekap_branch_id', (int) $candidate);
        }

        return (int) $candidate;
    }
}
