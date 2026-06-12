<?php

namespace App\Services\DgMeetingReports;

class DgMeetingReportFormData
{
    /**
     * @return array{leaders: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>, group_map: array<string, array<string, mixed>>, material_options: array<int, string>}
     */
    public function forBranch(string $branchCode): array
    {
        $branchRuntime = load_branch_discipleship_runtime($branchCode);
        $peopleById = is_array($branchRuntime['people_by_id'] ?? null) ? $branchRuntime['people_by_id'] : [];
        $groups = is_array($branchRuntime['groups'] ?? null) ? $branchRuntime['groups'] : [];
        $formData = build_dg_public_form_data($groups, $peopleById);

        return [
            'leaders' => is_array($formData['leaders'] ?? null) ? $formData['leaders'] : [],
            'groups' => is_array($formData['groups'] ?? null) ? $formData['groups'] : [],
            'group_map' => is_array($formData['group_map'] ?? null) ? $formData['group_map'] : [],
            'material_options' => $this->materialOptions(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function materialOptions(): array
    {
        $options = [];
        for ($i = 1; $i <= 12; $i++) {
            $options[] = 'Sesi ' . (string) $i;
        }
        $options[] = 'Lainnya';

        return $options;
    }
}
