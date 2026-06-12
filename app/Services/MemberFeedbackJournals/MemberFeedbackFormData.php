<?php

namespace App\Services\MemberFeedbackJournals;

class MemberFeedbackFormData
{
    /**
     * @return array{groups: array<int, array<string, mixed>>, group_map: array<string, array<string, mixed>>}
     */
    public function forBranch(string $branchCode): array
    {
        $branchRuntime = load_branch_discipleship_runtime($branchCode);
        $peopleById = is_array($branchRuntime['people_by_id'] ?? null) ? $branchRuntime['people_by_id'] : [];
        $groups = is_array($branchRuntime['groups'] ?? null) ? $branchRuntime['groups'] : [];
        $formData = build_dg_public_form_data($groups, $peopleById);

        return [
            'groups' => is_array($formData['groups'] ?? null) ? $formData['groups'] : [],
            'group_map' => is_array($formData['group_map'] ?? null) ? $formData['group_map'] : [],
        ];
    }
}
