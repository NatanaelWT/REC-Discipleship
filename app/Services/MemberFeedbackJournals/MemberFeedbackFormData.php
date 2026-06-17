<?php

namespace App\Services\MemberFeedbackJournals;

use App\Services\DiscipleshipGroups\DiscipleshipGroupPublicFormData;

class MemberFeedbackFormData
{
    public function __construct(private readonly DiscipleshipGroupPublicFormData $publicFormData)
    {
    }

    /**
     * @return array{groups: array<int, array<string, mixed>>, group_map: array<string, array<string, mixed>>}
     */
    public function forBranch(string $branchCode): array
    {
        $formData = $this->publicFormData->forBranch($branchCode);

        return [
            'groups' => is_array($formData['groups'] ?? null) ? $formData['groups'] : [],
            'group_map' => is_array($formData['group_map'] ?? null) ? $formData['group_map'] : [],
        ];
    }
}
