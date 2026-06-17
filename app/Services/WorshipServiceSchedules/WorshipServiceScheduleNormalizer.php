<?php

namespace App\Services\WorshipServiceSchedules;

use App\Support\RuntimeBootstrap;
use stdClass;

class WorshipServiceScheduleNormalizer
{
    /**
     * @param array<int|string, mixed> $rowLabelsInput
     * @param array<int|string, mixed> $assignmentsInput
     * @return array<string, mixed>
     */
    public function fromRequestInput(
        string $month,
        string $title,
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
        $title = trim($title);

        return [
            'month' => $month,
            'title' => $title !== '' ? $title : default_worship_penatalayan_title($month),
            'update_note' => trim($updateNote),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromDatabaseRow(stdClass $row): array
    {
        RuntimeBootstrap::load();

        $month = normalize_month_value((string) ($row->month ?? date('Y-m')));
        $rows = json_decode((string) ($row->rows_payload ?? '[]'), true);
        if (! is_array($rows)) {
            $rows = [];
        }

        return [
            'month' => $month,
            'title' => trim((string) ($row->title ?? '')) !== ''
                ? trim((string) $row->title)
                : default_worship_penatalayan_title($month),
            'update_note' => trim((string) ($row->update_note ?? '')),
            'rows' => normalize_worship_penatalayan_rows($rows, count(worship_penatalayan_week_dates($month))),
            'branch_code' => $this->nullableString($row->branch ?? null),
            'created_at' => $this->firstNonEmpty([
                $row->created_at ?? null,
            ]),
            'updated_at' => $this->firstNonEmpty([
                $row->record_updated_at ?? null,
                $row->updated_at ?? null,
            ]),
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

    /**
     * @param array<int, mixed> $values
     */
    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                return $stringValue;
            }
        }

        return '';
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : null;
    }
}
