<?php

namespace App\Services\Developer;

use App\Enums\UserAccessRole;
use App\Models\Branch;
use App\Models\User;
use Throwable;

class DeveloperDashboardOverviewService
{
    /** @return array<string, mixed> */
    public function overview(): array
    {
        $access = $this->accessSnapshot();
        $raw = $access['raw'];

        return [
            'header_stats' => [
                ['label' => 'Total User', 'value' => $this->formatNumber($raw['users'])],
                ['label' => 'User Aktif', 'value' => $this->formatNumber($raw['active_users'])],
                ['label' => 'Developer Aktif', 'value' => $this->formatNumber($raw['active_developers'])],
                ['label' => 'Cabang Pemuridan', 'value' => $this->formatNumber($raw['branches'])],
            ],
            'access_snapshot' => $access,
        ];
    }

    /** @return array{metrics:array<int, array<string, string>>,raw:array{users:int,active_users:int,active_developers:int,branches:int}} */
    private function accessSnapshot(): array
    {
        $raw = [
            'users' => $this->safeUserCount(),
            'active_users' => $this->safeUserCount(true),
            'active_developers' => $this->safeActiveDeveloperCount(),
            'branches' => $this->safeActiveDiscipleshipBranchCount(),
        ];

        return [
            'metrics' => [
                ['label' => 'Total User', 'value' => $this->formatNumber($raw['users']), 'note' => 'Semua akun terdaftar', 'tone' => 'is-teal', 'icon' => 'users'],
                ['label' => 'User Aktif', 'value' => $this->formatNumber($raw['active_users']), 'note' => 'Akun yang dapat masuk', 'tone' => 'is-blue', 'icon' => 'users'],
                ['label' => 'Developer Aktif', 'value' => $this->formatNumber($raw['active_developers']), 'note' => 'Pengelola sistem aktif', 'tone' => 'is-amber', 'icon' => 'config'],
                ['label' => 'Cabang Pemuridan', 'value' => $this->formatNumber($raw['branches']), 'note' => 'Cabang pemuridan aktif', 'tone' => 'is-violet', 'icon' => 'dashboard'],
            ],
            'raw' => $raw,
        ];
    }

    private function safeUserCount(?bool $active = null): int
    {
        try {
            $query = User::query();
            if ($active !== null) {
                $query->where('is_active', $active);
            }

            return $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeActiveDeveloperCount(): int
    {
        try {
            return User::query()
                ->where('access_scope', UserAccessRole::Developer->value)
                ->where('is_active', true)
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeActiveDiscipleshipBranchCount(): int
    {
        try {
            return Branch::query()
                ->where('is_active', true)
                ->where('label', '!=', 'Pusat')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function formatNumber(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
