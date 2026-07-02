<?php

namespace App\Services\WorshipServiceSchedules;

use App\Support\RuntimeBootstrap;

class WorshipServiceScheduleNormalizer
{
    /**
     * @param  array<int|string, mixed>  $rowLabelsInput
     * @param  array<int|string, mixed>  $assignmentsInput
     * @return array<string, mixed>
     */
    public function fromRequestInput(
        string $month,
        string $updateNote,
        array $rowLabelsInput,
        array $assignmentsInput,
    ): array {
        RuntimeBootstrap::load();

        $month = normalize_month_value($month);
        $submittedRows = [];

        foreach ($rowLabelsInput as $rowIndex => $roleLabelRaw) {
            $roleLabel = trim((string) $roleLabelRaw);
            if ($roleLabel === '') {
                continue;
            }

            $roleKey = strtolower($roleLabel);
            $rowAssignments = $assignmentsInput[$rowIndex] ?? [];
            if (! is_array($rowAssignments)) {
                $rowAssignments = [];
            }

            $normalizedRowAssignments = [];
            foreach ($rowAssignments as $weekIndex => $weekValue) {
                if (is_array($weekValue)) {
                    $weekParts = [];
                    foreach ($weekValue as $weekPart) {
                        $weekPartValue = trim((string) $weekPart);
                        if ($weekPartValue !== '') {
                            $weekParts[] = $weekPartValue;
                        }
                    }

                    $normalizedRowAssignments[(int) $weekIndex] = implode("\n", $weekParts);

                    continue;
                }

                $scalarValue = trim((string) $weekValue);
                if ($roleKey === 'jadwal latihan') {
                    $scalarValue = worship_penatalayan_training_date($scalarValue, $month);
                }

                $normalizedRowAssignments[(int) $weekIndex] = $scalarValue;
            }

            ksort($normalizedRowAssignments);
            $submittedRows[] = [
                'role' => $roleLabel,
                'assignments' => array_values($normalizedRowAssignments),
            ];
        }

        $weekCount = count(worship_penatalayan_week_dates($month));
        $rows = normalize_worship_penatalayan_rows($submittedRows, $weekCount);

        return [
            'month' => $month,
            'update_note' => trim($updateNote),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function splitAssignmentLines(string $value): array
    {
        $lines = preg_split("/\r\n?|\n/", $value) ?: [];
        $names = [];
        foreach ($lines as $line) {
            $name = trim((string) $line);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

}
