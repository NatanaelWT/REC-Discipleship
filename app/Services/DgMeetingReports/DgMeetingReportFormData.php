<?php

namespace App\Services\DgMeetingReports;

use App\Services\DiscipleshipGroups\DiscipleshipGroupPublicFormData;

class DgMeetingReportFormData
{
    public function __construct(private readonly DiscipleshipGroupPublicFormData $publicFormData)
    {
    }

    /**
     * @return array{leaders: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>, group_map: array<string, array<string, mixed>>, material_options: array<int, string>}
     */
    public function forBranch(string $branchCode): array
    {
        $formData = $this->publicFormData->forBranch($branchCode);

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
