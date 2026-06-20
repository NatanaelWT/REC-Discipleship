<?php

namespace App\Services\Developer;

use App\Services\Branches\BranchCatalog;

class DeveloperBranchService
{
    public function __construct(private readonly BranchCatalog $branches) {}

    /**
     * @return array<int, array{id:int|null,code:string,label:string}>
     */
    public function options(): array
    {
        return array_map(static fn (array $option): array => [
            'id' => $option['id'],
            'code' => $option['slug'],
            'label' => $option['label'],
        ], $this->branches->options());
    }

    public function normalizeAllowedId(mixed $branchId): ?int
    {
        $branchId = filter_var($branchId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($branchId === false || ! $this->branches->isActiveId($branchId)) {
            return null;
        }

        return $branchId;
    }
}
