<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, array{table:string,column:string}> */
    private array $personReferences = [
        ['table' => 'discipleship_group_people', 'column' => 'person_id'],
        ['table' => 'discipleship_group_memberships', 'column' => 'person_id'],
        ['table' => 'discipleship_group_leaderships', 'column' => 'person_id'],
        ['table' => 'discipleship_relationships', 'column' => 'mentor_person_id'],
        ['table' => 'discipleship_relationships', 'column' => 'disciple_person_id'],
        ['table' => 'discipleship_groups', 'column' => 'initiated_by_person_id'],
        ['table' => 'discipleship_group_multiplications', 'column' => 'initiated_by_person_id'],
        ['table' => 'discipleship_meeting_reports', 'column' => 'leader_person_id'],
        ['table' => 'discipleship_meeting_report_absences', 'column' => 'person_id'],
        ['table' => 'discipleship_meeting_report_meditation_sharers', 'column' => 'person_id'],
        ['table' => 'discipleship_feedbacks', 'column' => 'leader_person_id'],
        ['table' => 'discipleship_feedbacks', 'column' => 'respondent_person_id'],
        ['table' => 'discipleship_manual_journey_records', 'column' => 'person_id'],
    ];

    /** @var array<int, string> */
    private array $jsonPersonColumns = ['absences', 'meditation_sharers'];

    public function up(): void
    {
        if (! Schema::hasTable('people')
            || ! $this->hasColumns('people', ['id', 'branch_id', 'full_name', 'whatsapp'])) {
            return;
        }

        $changedBranchIds = [];

        DB::transaction(function () use (&$changedBranchIds): void {
            foreach ($this->duplicateGroups() as $rows) {
                $branchId = (int) ($rows[0]->branch_id ?? 0);
                $this->mergeDuplicateGroup($rows);
                if ($branchId > 0) {
                    $changedBranchIds[$branchId] = $branchId;
                }
            }
        });

        $this->invalidateDiscipleshipReadCache(array_values($changedBranchIds));
    }

    public function down(): void
    {
        // Merged duplicate canonical people cannot be reconstructed safely.
    }

    /** @return array<int, array<int, object>> */
    private function duplicateGroups(): array
    {
        $groups = [];
        foreach (DB::table('people')->orderBy('id')->get() as $person) {
            $identityKey = $this->identityKey($person);
            if ($identityKey === '') {
                continue;
            }

            $groups[(int) ($person->branch_id ?? 0).'|'.$identityKey][] = $person;
        }

        return array_values(array_filter($groups, static fn (array $rows): bool => count($rows) > 1));
    }

    /** @param array<int, object> $rows */
    private function mergeDuplicateGroup(array $rows): void
    {
        $target = $this->targetPerson($rows);
        $targetId = (int) $target->id;
        $duplicateIds = array_values(array_filter(
            array_map(static fn (object $row): int => (int) $row->id, $rows),
            static fn (int $personId): bool => $personId > 0 && $personId !== $targetId,
        ));
        if ($duplicateIds === []) {
            return;
        }

        $this->mergePersonValues($targetId, $rows);
        $this->remapPersonReferences($targetId, $duplicateIds);
        $this->remapReportJsonPersonIds($targetId, $duplicateIds);
        $this->deleteSelfRelationships($targetId);

        DB::table('people')->whereIn('id', $duplicateIds)->delete();
    }

    /** @param array<int, object> $rows */
    private function targetPerson(array $rows): object
    {
        usort($rows, function (object $left, object $right): int {
            $score = $this->personScore($right) <=> $this->personScore($left);

            return $score !== 0 ? $score : ((int) $left->id <=> (int) $right->id);
        });

        return $rows[0];
    }

    private function personScore(object $row): int
    {
        return ($this->discipleshipReferenceCount((int) $row->id) * 100000)
            + (count($this->sessionNumbers($row)) * 1000)
            + ($this->stringValue($row, 'batch_month') !== '' ? 100 : 0)
            + ($this->stringValue($row, 'completed_at') !== '' ? 50 : 0)
            + (count($this->photoRows($row)) * 10)
            + ($this->stringValue($row, 'email') !== '' ? 5 : 0)
            + ($this->stringValue($row, 'birth_date') !== '' ? 5 : 0);
    }

    private function discipleshipReferenceCount(int $personId): int
    {
        $total = 0;
        foreach ($this->personReferences as $reference) {
            if (! $this->hasColumns($reference['table'], [$reference['column']])) {
                continue;
            }

            $total += (int) DB::table($reference['table'])
                ->where($reference['column'], $personId)
                ->count();
        }

        return $total;
    }

    /** @param array<int, object> $rows */
    private function mergePersonValues(int $targetId, array $rows): void
    {
        $target = null;
        foreach ($rows as $row) {
            if ((int) $row->id === $targetId) {
                $target = $row;
                break;
            }
        }
        if ($target === null) {
            return;
        }

        $orderedRows = array_merge(
            [$target],
            array_values(array_filter($rows, static fn (object $row): bool => (int) $row->id !== $targetId)),
        );
        $updates = [
            'branch_id' => (int) ($target->branch_id ?? 0) ?: null,
            'full_name' => $this->firstFilled($orderedRows, 'full_name'),
            'gender' => $this->firstFilled($orderedRows, 'gender'),
            'birth_date' => $this->firstFilled($orderedRows, 'birth_date'),
            'birth_day_month' => $this->firstFilled($orderedRows, 'birth_day_month'),
            'birth_place' => $this->firstFilled($orderedRows, 'birth_place'),
            'address' => $this->firstFilled($orderedRows, 'address'),
            'email' => $this->firstFilled($orderedRows, 'email'),
            'whatsapp' => $this->preferredWhatsapp($orderedRows),
            'batch_month' => $this->firstFilled($orderedRows, 'batch_month'),
            'notes' => $this->mergedNotes($orderedRows),
            'completed_at' => $this->firstFilled($orderedRows, 'completed_at'),
            'journey_bridge_status' => $this->mergedBridgeStatus($orderedRows),
            'status' => $this->mergedStatus($orderedRows),
            'session_numbers' => json_encode($this->mergedSessionNumbers($orderedRows)),
            'photos' => json_encode($this->mergedPhotos($orderedRows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $this->timestampBoundary($orderedRows, 'created_at', 'min'),
            'updated_at' => $this->timestampBoundary($orderedRows, 'updated_at', 'max') ?? now(),
        ];

        DB::table('people')
            ->where('id', $targetId)
            ->update($this->existingColumnValues('people', $updates));
    }

    /** @param array<int, int> $duplicateIds */
    private function remapPersonReferences(int $targetId, array $duplicateIds): void
    {
        foreach ($this->personReferences as $reference) {
            if (! $this->hasColumns($reference['table'], [$reference['column']])) {
                continue;
            }

            DB::table($reference['table'])
                ->whereIn($reference['column'], $duplicateIds)
                ->update($this->valuesWithTimestamp($reference['table'], [
                    $reference['column'] => $targetId,
                ]));
        }
    }

    /** @param array<int, int> $duplicateIds */
    private function remapReportJsonPersonIds(int $targetId, array $duplicateIds): void
    {
        if (! Schema::hasTable('discipleship_meeting_reports') || ! Schema::hasColumn('discipleship_meeting_reports', 'id')) {
            return;
        }

        $columns = array_values(array_filter(
            $this->jsonPersonColumns,
            static fn (string $column): bool => Schema::hasColumn('discipleship_meeting_reports', $column),
        ));
        if ($columns === []) {
            return;
        }

        foreach (DB::table('discipleship_meeting_reports')->select(array_merge(['id'], $columns))->orderBy('id')->get() as $report) {
            $updates = [];
            foreach ($columns as $column) {
                $items = $this->jsonArray($report->{$column} ?? []);
                $changed = false;

                foreach ($items as &$item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $personId = (int) ($item['person_id'] ?? 0);
                    if (in_array($personId, $duplicateIds, true)) {
                        $item['person_id'] = $targetId;
                        $changed = true;
                    }
                }
                unset($item);

                if ($changed) {
                    $updates[$column] = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            if ($updates !== []) {
                DB::table('discipleship_meeting_reports')
                    ->where('id', (int) $report->id)
                    ->update($this->valuesWithTimestamp('discipleship_meeting_reports', $updates));
            }
        }
    }

    private function deleteSelfRelationships(int $targetId): void
    {
        if (! $this->hasColumns('discipleship_relationships', ['mentor_person_id', 'disciple_person_id'])) {
            return;
        }

        DB::table('discipleship_relationships')
            ->where('mentor_person_id', $targetId)
            ->where('disciple_person_id', $targetId)
            ->delete();
    }

    private function identityKey(object $row): string
    {
        $nameKey = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row->full_name ?? '')) ?? (string) ($row->full_name ?? '')));
        $whatsappKey = $this->normalizeWhatsappDigits((string) ($row->whatsapp ?? ''));
        if ($nameKey === '' || $whatsappKey === '') {
            return '';
        }

        return $nameKey.'|'.$whatsappKey;
    }

    private function normalizeWhatsappDigits(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits !== '' && str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }
        if ($digits !== '' && str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /** @param array<int, object> $rows */
    private function firstFilled(array $rows, string $column): ?string
    {
        foreach ($rows as $row) {
            $value = $this->stringValue($row, $column);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param array<int, object> $rows */
    private function preferredWhatsapp(array $rows): ?string
    {
        foreach ($rows as $row) {
            $value = $this->stringValue($row, 'whatsapp');
            if ($value !== '' && str_starts_with($value, '0')) {
                return $value;
            }
        }

        return $this->firstFilled($rows, 'whatsapp');
    }

    private function stringValue(object $row, string $column): string
    {
        return trim((string) ($row->{$column} ?? ''));
    }

    /** @param array<int, object> $rows */
    private function mergedNotes(array $rows): ?string
    {
        $notes = [];
        foreach ($rows as $row) {
            $note = $this->stringValue($row, 'notes');
            if ($note !== '' && ! in_array($note, $notes, true)) {
                $notes[] = $note;
            }
        }

        return $notes !== [] ? implode("\n\n", $notes) : null;
    }

    /** @param array<int, object> $rows */
    private function mergedBridgeStatus(array $rows): string
    {
        foreach ($rows as $row) {
            $status = $this->stringValue($row, 'journey_bridge_status');
            if ($status !== '' && $status !== 'belum') {
                return $status;
            }
        }

        return $this->firstFilled($rows, 'journey_bridge_status') ?: 'belum';
    }

    /** @param array<int, object> $rows */
    private function mergedStatus(array $rows): string
    {
        foreach ($rows as $row) {
            if ($this->stringValue($row, 'status') === 'active') {
                return 'active';
            }
        }

        return $this->firstFilled($rows, 'status') ?: 'active';
    }

    /** @param array<int, object> $rows @return array<int, int> */
    private function mergedSessionNumbers(array $rows): array
    {
        $sessions = [];
        foreach ($rows as $row) {
            foreach ($this->sessionNumbers($row) as $sessionNumber) {
                $sessions[$sessionNumber] = $sessionNumber;
            }
        }
        ksort($sessions);

        return array_values($sessions);
    }

    /** @return array<int, int> */
    private function sessionNumbers(object $row): array
    {
        $sessions = [];
        foreach ($this->jsonArray($row->session_numbers ?? []) as $sessionNumber) {
            if (! is_numeric($sessionNumber)) {
                continue;
            }

            $number = (int) $sessionNumber;
            if ($number >= 1 && $number <= 12) {
                $sessions[$number] = $number;
            }
        }
        ksort($sessions);

        return array_values($sessions);
    }

    /** @param array<int, object> $rows @return array<int, array<string, string>> */
    private function mergedPhotos(array $rows): array
    {
        $photos = [];
        foreach ($rows as $row) {
            foreach ($this->photoRows($row) as $photo) {
                $path = $this->photoPath((string) ($photo['path'] ?? ''));
                if ($path === '' || isset($photos[$path])) {
                    continue;
                }

                $photos[$path] = [
                    'path' => $path,
                    'name' => trim((string) ($photo['name'] ?? $photo['original_name'] ?? '')) ?: 'Foto',
                ];
            }
        }

        return array_values($photos);
    }

    /** @return array<int, array<string, mixed>> */
    private function photoRows(object $row): array
    {
        return array_values(array_filter(
            $this->jsonArray($row->photos ?? []),
            static fn (mixed $photo): bool => is_array($photo),
        ));
    }

    private function photoPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, '../') || str_starts_with($path, '/')) {
            return '';
        }

        return $path;
    }

    /** @return array<int, mixed> */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<int, object> $rows */
    private function timestampBoundary(array $rows, string $column, string $mode): ?string
    {
        $selectedValue = null;
        $selectedTimestamp = null;
        foreach ($rows as $row) {
            $value = $this->stringValue($row, $column);
            if ($value === '') {
                continue;
            }

            $timestamp = strtotime($value);
            if ($timestamp === false) {
                continue;
            }

            if ($selectedTimestamp === null
                || ($mode === 'min' && $timestamp < $selectedTimestamp)
                || ($mode === 'max' && $timestamp > $selectedTimestamp)) {
                $selectedTimestamp = $timestamp;
                $selectedValue = $value;
            }
        }

        return $selectedValue;
    }

    /** @param array<string, mixed> $values */
    private function existingColumnValues(string $table, array $values): array
    {
        return array_filter(
            $values,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @param array<string, mixed> $values */
    private function valuesWithTimestamp(string $table, array $values): array
    {
        if (Schema::hasColumn($table, 'updated_at')) {
            $values['updated_at'] = now();
        }

        return $values;
    }

    /** @param array<int, string> $columns */
    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, int> $branchIds */
    private function invalidateDiscipleshipReadCache(array $branchIds): void
    {
        $branchIds = array_values(array_unique(array_filter($branchIds, static fn (int $id): bool => $id > 0)));
        if ($branchIds === []) {
            return;
        }

        $store = Cache::store(app()->environment('testing') ? 'array' : 'file');
        $version = (string) hrtime(true);

        foreach ($branchIds as $branchId) {
            $store->forever('rec.discipleship-read.version.branch.'.$branchId, $version);
        }
    }
};
