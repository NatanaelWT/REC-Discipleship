<?php

namespace App\Services\DiscipleshipTargets;

class DiscipleshipTargetNormalizer
{
    /**
     * @return array<string, int>
     */
    public function defaults(): array
    {
        return [
            'camp_gap_participant_target' => 50,
            'msk_completion_target' => 50,
            'dg1_completion_target' => 50,
            'dg2_completion_target' => 50,
            'dg3_completion_target' => 50,
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, int>
     */
    public function normalize(array $values): array
    {
        $normalized = [];
        foreach ($this->defaults() as $key => $defaultValue) {
            $normalized[$key] = $this->boundedInteger($values[$key] ?? $defaultValue, $defaultValue);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $formValues
     * @return array<string, int>
     */
    public function normalizeFormValues(array $formValues): array
    {
        return $this->normalize([
            'camp_gap_participant_target' => $formValues['dg_total_people'] ?? 50,
            'msk_completion_target' => $formValues['msk_completed'] ?? 50,
            'dg1_completion_target' => $formValues['dg1_people'] ?? 50,
            'dg2_completion_target' => $formValues['dg2_people'] ?? 50,
            'dg3_completion_target' => $formValues['dg3_people'] ?? 50,
        ]);
    }

    /**
     * @param array<string, int> $values
     * @return array<string, int>
     */
    public function toFormValues(array $values): array
    {
        $values = $this->normalize($values);

        return [
            'dg_total_people' => $values['camp_gap_participant_target'],
            'msk_completed' => $values['msk_completion_target'],
            'dg1_people' => $values['dg1_completion_target'],
            'dg2_people' => $values['dg2_completion_target'],
            'dg3_people' => $values['dg3_completion_target'],
        ];
    }

    private function boundedInteger(mixed $value, int $default): int
    {
        if (is_string($value)) {
            $value = preg_replace('/[^0-9]/', '', $value) ?? '';
        }

        if (! is_numeric($value)) {
            $value = $default;
        }

        return min(1000000, max(0, (int) $value));
    }
}
