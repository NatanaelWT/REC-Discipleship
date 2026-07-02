<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $profileColumns = [
        'full_name',
        'phone',
        'gender',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('discipleship_people') || ! Schema::hasTable('msk_participants')) {
            return;
        }

        $this->ensureParticipantLinkColumn();
        $this->assertNoDuplicateParticipantLinks();
        $this->backfillParticipantProfiles();
        $this->ensureParticipantLinkIsUnique();
        $this->dropPersonProfileColumns();
    }

    public function down(): void
    {
        if (! Schema::hasTable('discipleship_people')) {
            return;
        }

        $missingColumns = array_values(array_filter(
            $this->profileColumns,
            static fn (string $column): bool => ! Schema::hasColumn('discipleship_people', $column),
        ));

        if ($missingColumns !== []) {
            Schema::table('discipleship_people', function (Blueprint $table) use ($missingColumns): void {
                foreach ($missingColumns as $column) {
                    $table->string($column, $column === 'phone' ? 80 : 255)->nullable();
                }
            });
        }

        if (! Schema::hasTable('msk_participants') || ! Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            return;
        }

        foreach (DB::table('msk_participants')->whereNotNull('discipleship_person_id')->orderBy('id')->get() as $participant) {
            $updates = [
                'full_name' => trim((string) ($participant->full_name ?? '')) ?: null,
                'phone' => trim((string) ($participant->whatsapp ?? '')) ?: null,
                'gender' => trim((string) ($participant->gender ?? '')) ?: null,
            ];

            DB::table('discipleship_people')
                ->where('id', (int) $participant->discipleship_person_id)
                ->update($this->existingColumnValues('discipleship_people', $updates));
        }
    }

    private function ensureParticipantLinkColumn(): void
    {
        if (Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            return;
        }

        Schema::table('msk_participants', static function (Blueprint $table): void {
            $table->unsignedBigInteger('discipleship_person_id')->nullable()->after('branch_id');
        });
    }

    private function assertNoDuplicateParticipantLinks(): void
    {
        $duplicate = DB::table('msk_participants')
            ->whereNotNull('discipleship_person_id')
            ->select('discipleship_person_id', DB::raw('COUNT(*) AS total'))
            ->groupBy('discipleship_person_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException('Discipleship person '.$duplicate->discipleship_person_id.' is linked to multiple MSK participants.');
        }
    }

    private function backfillParticipantProfiles(): void
    {
        $select = ['id', 'branch_id', 'status', 'notes', 'created_at', 'updated_at'];
        foreach ($this->profileColumns as $column) {
            if (Schema::hasColumn('discipleship_people', $column)) {
                $select[] = $column;
            }
        }

        DB::transaction(function () use ($select): void {
            foreach (DB::table('discipleship_people')->select($select)->orderBy('id')->get() as $person) {
                $participant = DB::table('msk_participants')
                    ->where('discipleship_person_id', (int) $person->id)
                    ->orderBy('id')
                    ->first();

                if ($participant === null) {
                    $participant = $this->matchingUnlinkedParticipant($person);
                }

                if ($participant !== null) {
                    $this->updateParticipantProfile($participant, $person, true);

                    continue;
                }

                $this->insertPlaceholderParticipant($person);
            }
        });
    }

    private function matchingUnlinkedParticipant(object $person): ?object
    {
        $identityKey = $this->participantIdentityKey(
            (string) ($person->full_name ?? ''),
            (string) ($person->phone ?? ''),
        );
        if ($identityKey === '') {
            return null;
        }

        $matches = DB::table('msk_participants')
            ->where('branch_id', (int) $person->branch_id)
            ->whereNull('discipleship_person_id')
            ->get()
            ->filter(fn (object $participant): bool => $this->participantIdentityKey(
                (string) ($participant->full_name ?? ''),
                (string) ($participant->whatsapp ?? ''),
            ) === $identityKey)
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function participantIdentityKey(string $fullName, string $whatsapp): string
    {
        $nameKey = strtolower(trim(preg_replace('/\s+/', ' ', $fullName) ?? $fullName));
        $whatsappKey = $this->normalizeWhatsappDigits($whatsapp);
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

    private function updateParticipantProfile(object $participant, object $person, bool $linkToPerson): void
    {
        $updates = [];

        if ($linkToPerson && $participant->discipleship_person_id === null) {
            $updates['discipleship_person_id'] = (int) $person->id;
        }

        foreach ([
            'full_name' => 'full_name',
            'gender' => 'gender',
            'whatsapp' => 'phone',
        ] as $participantColumn => $personColumn) {
            $participantValue = trim((string) ($participant->{$participantColumn} ?? ''));
            $personValue = trim((string) ($person->{$personColumn} ?? ''));
            if ($participantValue === '' && $personValue !== '') {
                $updates[$participantColumn] = $personValue;
            }
        }

        if ($updates === []) {
            return;
        }

        DB::table('msk_participants')
            ->where('id', (int) $participant->id)
            ->update($this->existingColumnValues('msk_participants', $updates));
    }

    private function insertPlaceholderParticipant(object $person): void
    {
        $timestamp = $person->created_at ?? now();
        $updatedAt = $person->updated_at ?? $timestamp;
        $values = [
            'branch_id' => (int) $person->branch_id,
            'discipleship_person_id' => (int) $person->id,
            'full_name' => trim((string) ($person->full_name ?? '')) ?: null,
            'gender' => trim((string) ($person->gender ?? '')) ?: null,
            'whatsapp' => trim((string) ($person->phone ?? '')) ?: null,
            'batch_month' => null,
            'notes' => null,
            'completed_at' => null,
            'journey_bridge_status' => 'belum',
            'status' => 'active',
            'session_numbers' => json_encode([]),
            'photos' => json_encode([]),
            'created_at' => $timestamp,
            'updated_at' => $updatedAt,
        ];

        DB::table('msk_participants')->insert($this->existingColumnValues('msk_participants', $values));
    }

    private function ensureParticipantLinkIsUnique(): void
    {
        if (! Schema::hasColumn('msk_participants', 'discipleship_person_id')
            || Schema::hasIndex('msk_participants', ['discipleship_person_id'], 'unique')) {
            return;
        }

        Schema::table('msk_participants', static function (Blueprint $table): void {
            $table->unique('discipleship_person_id', 'msk_participants_person_unique');
        });
    }

    private function dropPersonProfileColumns(): void
    {
        $columns = array_values(array_filter(
            $this->profileColumns,
            static fn (string $column): bool => Schema::hasColumn('discipleship_people', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('discipleship_people', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
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
