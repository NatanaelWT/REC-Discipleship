<?php

namespace App\Models\Concerns;

trait ResolvesBranchSlug
{
    public function initializeResolvesBranchSlug(): void
    {
        $this->append('branch_code');
    }

    public function getBranchCodeAttribute(): string
    {
        return branch_slug_from_id($this->getAttribute('branch_id'));
    }
}
