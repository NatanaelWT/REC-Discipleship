<?php

namespace App\Services\Developer;

use App\Models\Branch;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DeveloperBranchService
{
    /**
     * @return array<int, array{code:string,label:string}>
     */
    public function options(): array
    {
        $options = [];

        try {
            if (Schema::hasTable('branches')) {
                $options = Branch::query()
                    ->where('is_active', true)
                    ->where('code', '!=', 'pusat')
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get(['code', 'label'])
                    ->map(static fn (Branch $branch): array => [
                        'code' => normalize_user_branch((string) $branch->code),
                        'label' => trim((string) $branch->label) ?: user_branch_label((string) $branch->code),
                    ])
                    ->all();
            }
        } catch (Throwable) {
            $options = [];
        }

        $seen = [];
        $merged = [];
        foreach (array_merge($options, $this->fallbackOptions()) as $option) {
            $code = normalize_user_branch((string) ($option['code'] ?? ''));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $merged[] = [
                'code' => $code,
                'label' => trim((string) ($option['label'] ?? '')) ?: user_branch_label($code),
            ];
        }

        return $merged;
    }

    public function isAllowed(string $branch): bool
    {
        return $this->normalizeAllowed($branch) !== null;
    }

    public function idForCode(?string $branch): ?int
    {
        $branch = $branch !== null ? $this->normalizeAllowed($branch) : null;
        if ($branch === null || ! Schema::hasTable('branches')) {
            return null;
        }

        try {
            $id = Branch::query()->where('code', $branch)->where('is_active', true)->value('id');

            return $id === null ? null : (int) $id;
        } catch (Throwable) {
            return null;
        }
    }

    public function normalizeAllowed(string $branch): ?string
    {
        $branch = strtolower(trim($branch));
        foreach ($this->options() as $option) {
            if ($option['code'] === $branch) {
                return $option['code'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    private function fallbackOptions(): array
    {
        return [
            ['code' => 'kutisari', 'label' => 'Kutisari'],
            ['code' => 'gm', 'label' => 'GM'],
            ['code' => 'darmo', 'label' => 'Darmo'],
            ['code' => 'merr', 'label' => 'Merr'],
            ['code' => 'batam', 'label' => 'Batam'],
            ['code' => 'nginden', 'label' => 'Nginden'],
        ];
    }
}
