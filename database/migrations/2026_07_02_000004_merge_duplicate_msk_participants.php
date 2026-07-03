<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('msk_participants')
            || ! Schema::hasColumn('msk_participants', 'branch_id')
            || ! Schema::hasColumn('msk_participants', 'full_name')
            || ! Schema::hasColumn('msk_participants', 'whatsapp')) {
            return;
        }

        DB::transaction(function (): void {
            foreach ($this->duplicateGroups() as $rows) {
                $this->mergeGroup($rows);
            }
        });
    }

    public function down(): void
    {
        // Merged duplicate participant rows cannot be reconstructed safely.
    }

    /** @return array<int, array<int, object>> */
    private function duplicateGroups(): array
    {
        $groups = [];
        foreach (DB::table('msk_participants')->orderBy('id')->get() as $participant) {
            $identityKey = $this->identityKey($participant);
            if ($identityKey === '') {
                continue;
            }

            $groups[(int) $participant->branch_id.'|'.$identityKey][] = $participant;
        }

        return array_values(array_filter($groups, static fn (array $rows): bool => count($rows) > 1));
    }

    /** @param array<int, object> $rows */
    private function mergeGroup(array $rows): void
    {
        $linkedPersonIds = [];
        if (Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            foreach ($rows as $row) {
                $linkedPersonId = (int) ($row->discipleship_person_id ?? 0);
                if ($linkedPersonId > 0) {
                    $linkedPersonIds[$linkedPersonId] = $linkedPersonId;
                }
            }
        }

        $target = $this->targetParticipant($rows);
        $losers = array_values(array_filter(
            $rows,
            static fn (object $row): bool => (int) $row->id !== (int) $target->id,
        ));
        if ($losers === []) {
            return;
        }

        $canonicalPersonId = $this->canonicalPersonId($target, $rows, $linkedPersonIds);
        $duplicatePersonIds = $canonicalPersonId !== null
            ? array_values(array_diff(array_values($linkedPersonIds), [$canonicalPersonId]))
            : [];
        $this->mergeDiscipleshipPeople($canonicalPersonId, $duplicatePersonIds);

        $updates = $this->mergedValues($target, $losers, $canonicalPersonId);
        $loserIds = array_map(static fn (object $row): int => (int) $row->id, $losers);

        if (Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            DB::table('msk_participants')
                ->whereIn('id', $loserIds)
                ->update(['discipleship_person_id' => null]);
            if ($duplicatePersonIds !== []) {
                DB::table('msk_participants')
                    ->whereIn('discipleship_person_id', $duplicatePersonIds)
                    ->update(['discipleship_person_id' => null]);
            }
        }

        DB::table('msk_participants')
            ->where('id', (int) $target->id)
            ->update($this->existingColumnValues('msk_participants', $updates));

        DB::table('msk_participants')
            ->whereIn('id', $loserIds)
            ->delete();

        $this->deleteDuplicatePeople($duplicatePersonIds);
    }

    /** @param array<int, object> $rows */
    private function targetParticipant(array $rows): object
    {
        usort($rows, function (object $left, object $right): int {
            $score = $this->participantScore($right) <=> $this->participantScore($left);

            return $score !== 0 ? $score : ((int) $left->id <=> (int) $right->id);
        });

        return $rows[0];
    }

    private function participantScore(object $row): int
    {
        return (count($this->sessionNumbers($row)) * 1000)
            + ($this->stringValue($row, 'batch_month') !== '' ? 100 : 0)
            + ($this->stringValue($row, 'completed_at') !== '' ? 50 : 0)
            + (count($this->photoRows($row)) * 10)
            + ($this->stringValue($row, 'email') !== '' ? 5 : 0)
            + ($this->stringValue($row, 'birth_date') !== '' ? 5 : 0)
            + ((int) ($row->discipleship_person_id ?? 0) > 0 ? 1 : 0);
    }

    /** @param array<int, object> $rows @param array<int, int> $linkedPersonIds */
    private function canonicalPersonId(object $target, array $rows, array $linkedPersonIds): ?int
    {
        $targetPersonId = (int) ($target->discipleship_person_id ?? 0);
        if ($targetPersonId > 0) {
            return $targetPersonId;
        }

        if ($linkedPersonIds === []) {
            return null;
        }

        usort($rows, function (object $left, object $right): int {
            $score = $this->participantScore($right) <=> $this->participantScore($left);

            return $score !== 0 ? $score : ((int) $left->id <=> (int) $right->id);
        });

        foreach ($rows as $row) {
            $personId = (int) ($row->discipleship_person_id ?? 0);
            if ($personId > 0) {
                return $personId;
            }
        }

        return array_values($linkedPersonIds)[0] ?? null;
    }

    /** @param array<int, int> $duplicatePersonIds */
    private function mergeDiscipleshipPeople(?int $canonicalPersonId, array $duplicatePersonIds): void
    {
        if ($canonicalPersonId === null || $duplicatePersonIds === []) {
            return;
        }

        $this->mergePersonRowValues($canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_group_people', 'person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_relationships', 'mentor_person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_relationships', 'disciple_person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_groups', 'initiated_by_person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_group_multiplications', 'initiated_by_person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_meeting_reports', 'leader_person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_feedbacks', 'leader_person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_feedbacks', 'respondent_person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_meeting_report_absences', 'person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->remapColumn('discipleship_meeting_report_meditation_sharers', 'person_id', $canonicalPersonId, $duplicatePersonIds);
        $this->mergeManualJourneyRecords($canonicalPersonId, $duplicatePersonIds);
        $this->remapReportJsonPersonIds($canonicalPersonId, $duplicatePersonIds);
        $this->deleteSelfRelationships($canonicalPersonId);
    }

    /** @param array<int, int> $duplicatePersonIds */
    private function mergePersonRowValues(int $canonicalPersonId, array $duplicatePersonIds): void
    {
        if (! Schema::hasTable('discipleship_people')) {
            return;
        }

        $personIds = array_values(array_unique(array_merge([$canonicalPersonId], $duplicatePersonIds)));
        $people = DB::table('discipleship_people')
            ->whereIn('id', $personIds)
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$canonicalPersonId])
            ->orderBy('id')
            ->get()
            ->all();
        if ($people === []) {
            return;
        }

        $updates = [];
        if (Schema::hasColumn('discipleship_people', 'notes')) {
            $updates['notes'] = $this->mergedNotes($people);
        }
        if (Schema::hasColumn('discipleship_people', 'status')) {
            $updates['status'] = $this->mergedPersonStatus($people);
        }
        if (Schema::hasColumn('discipleship_people', 'created_at')) {
            $updates['created_at'] = $this->timestampBoundary($people, 'created_at', 'min');
        }
        if (Schema::hasColumn('discipleship_people', 'updated_at')) {
            $updates['updated_at'] = $this->timestampBoundary($people, 'updated_at', 'max') ?? now();
        }

        if ($updates !== []) {
            DB::table('discipleship_people')
                ->where('id', $canonicalPersonId)
                ->update($this->existingColumnValues('discipleship_people', $updates));
        }
    }

    /** @param array<int, object> $people */
    private function mergedPersonStatus(array $people): string
    {
        foreach ($people as $person) {
            if ($this->stringValue($person, 'status') === 'active') {
                return 'active';
            }
        }

        return $this->firstFilled($people, 'status') ?: 'active';
    }

    /** @param array<int, int> $duplicatePersonIds */
    private function remapColumn(string $table, string $column, int $canonicalPersonId, array $duplicatePersonIds): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->whereIn($column, $duplicatePersonIds)
            ->update([$column => $canonicalPersonId]);
    }

    /** @param array<int, int> $duplicatePersonIds */
    private function mergeManualJourneyRecords(int $canonicalPersonId, array $duplicatePersonIds): void
    {
        if (! $this->hasColumns('discipleship_manual_journey_records', ['id', 'branch_id', 'person_id', 'stage'])) {
            return;
        }

        foreach (DB::table('discipleship_manual_journey_records')->whereIn('person_id', $duplicatePersonIds)->orderBy('id')->get() as $row) {
            $existing = DB::table('discipleship_manual_journey_records')
                ->where('branch_id', (int) $row->branch_id)
                ->where('person_id', $canonicalPersonId)
                ->where('stage', (string) $row->stage)
                ->first();

            if ($existing === null) {
                DB::table('discipleship_manual_journey_records')
                    ->where('id', (int) $row->id)
                    ->update(['person_id' => $canonicalPersonId]);

                continue;
            }

            DB::table('discipleship_manual_journey_records')
                ->where('id', (int) $existing->id)
                ->update($this->existingColumnValues('discipleship_manual_journey_records', [
                    'completed_on' => $this->earliestDate($this->stringValue($existing, 'completed_on'), $this->stringValue($row, 'completed_on')),
                    'notes' => $this->mergedNotes([$existing, $row]),
                    'created_at' => $this->timestampBoundary([$existing, $row], 'created_at', 'min'),
                    'updated_at' => $this->timestampBoundary([$existing, $row], 'updated_at', 'max') ?? now(),
                ]));
            DB::table('discipleship_manual_journey_records')->where('id', (int) $row->id)->delete();
        }
    }

    private function earliestDate(string $left, string $right): ?string
    {
        $dates = array_values(array_filter([$left, $right], static fn (string $value): bool => $value !== ''));
        if ($dates === []) {
            return null;
        }

        sort($dates, SORT_STRING);

        return $dates[0];
    }

    /** @param array<int, int> $duplicatePersonIds */
    private function remapReportJsonPersonIds(int $canonicalPersonId, array $duplicatePersonIds): void
    {
        if (! $this->hasColumns('discipleship_meeting_reports', ['id'])) {
            return;
        }

        $columns = array_values(array_filter(
            ['absences', 'meditation_sharers'],
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
                    if (in_array($personId, $duplicatePersonIds, true)) {
                        $item['person_id'] = $canonicalPersonId;
                        $changed = true;
                    }
                }
                unset($item);

                if ($changed) {
                    $updates[$column] = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            if ($updates !== []) {
                DB::table('discipleship_meeting_reports')->where('id', (int) $report->id)->update($updates);
            }
        }
    }

    private function deleteSelfRelationships(int $canonicalPersonId): void
    {
        if (! $this->hasColumns('discipleship_relationships', ['mentor_person_id', 'disciple_person_id'])) {
            return;
        }

        DB::table('discipleship_relationships')
            ->where('mentor_person_id', $canonicalPersonId)
            ->where('disciple_person_id', $canonicalPersonId)
            ->delete();
    }

    /** @param array<int, int> $duplicatePersonIds */
    private function deleteDuplicatePeople(array $duplicatePersonIds): void
    {
        if ($duplicatePersonIds === [] || ! Schema::hasTable('discipleship_people')) {
            return;
        }

        DB::table('discipleship_people')
            ->whereIn('id', $duplicatePersonIds)
            ->delete();
    }

    /** @param array<int, object> $losers @return array<string, mixed> */
    private function mergedValues(object $target, array $losers, ?int $linkedPersonId): array
    {
        $rows = array_merge([$target], $losers);
        $values = [
            'full_name' => $this->firstFilled($rows, 'full_name'),
            'gender' => $this->firstFilled($rows, 'gender'),
            'birth_date' => $this->firstFilled($rows, 'birth_date'),
            'birth_day_month' => $this->firstFilled($rows, 'birth_day_month'),
            'birth_place' => $this->firstFilled($rows, 'birth_place'),
            'address' => $this->firstFilled($rows, 'address'),
            'email' => $this->firstFilled($rows, 'email'),
            'whatsapp' => $this->firstFilled($rows, 'whatsapp'),
            'batch_month' => $this->firstFilled($rows, 'batch_month'),
            'notes' => $this->mergedNotes($rows),
            'completed_at' => $this->firstFilled($rows, 'completed_at'),
            'journey_bridge_status' => $this->mergedBridgeStatus($rows),
            'status' => $this->firstFilled($rows, 'status') ?: 'active',
            'session_numbers' => json_encode($this->mergedSessionNumbers($rows)),
            'photos' => json_encode($this->mergedPhotos($rows)),
            'created_at' => $this->timestampBoundary($rows, 'created_at', 'min'),
            'updated_at' => $this->timestampBoundary($rows, 'updated_at', 'max') ?? now(),
        ];

        if ($linkedPersonId !== null) {
            $values['discipleship_person_id'] = $linkedPersonId;
        }

        return $values;
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
        if ($digits !== '' && strpos($digits, '0') === 0) {
            return '62'.substr($digits, 1);
        }
        if ($digits !== '' && strpos($digits, '8') === 0) {
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
            if (is_numeric($sessionNumber)) {
                $number = (int) $sessionNumber;
                if ($number >= 1 && $number <= 12) {
                    $sessions[$number] = $number;
                }
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

    private function photoPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, '../') || str_starts_with($path, '/')) {
            return '';
        }

        return $path;
    }

    /** @return array<int, array<string, mixed>> */
    private function photoRows(object $row): array
    {
        return array_values(array_filter(
            $this->jsonArray($row->photos ?? []),
            static fn (mixed $photo): bool => is_array($photo),
        ));
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
};
