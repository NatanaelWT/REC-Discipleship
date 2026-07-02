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

        if (count($linkedPersonIds) > 1) {
            throw new RuntimeException('Duplicate MSK participants share identity but point to different discipleship people.');
        }

        $target = $this->targetParticipant($rows);
        $losers = array_values(array_filter(
            $rows,
            static fn (object $row): bool => (int) $row->id !== (int) $target->id,
        ));
        if ($losers === []) {
            return;
        }

        $updates = $this->mergedValues($target, $losers, array_values($linkedPersonIds)[0] ?? null);
        $loserIds = array_map(static fn (object $row): int => (int) $row->id, $losers);

        if (Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            DB::table('msk_participants')
                ->whereIn('id', $loserIds)
                ->update(['discipleship_person_id' => null]);
        }

        DB::table('msk_participants')
            ->where('id', (int) $target->id)
            ->update($this->existingColumnValues('msk_participants', $updates));

        DB::table('msk_participants')
            ->whereIn('id', $loserIds)
            ->delete();
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
};
