<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, array{table:string,column:string,on_delete:string,name:string}>
     */
    private array $personReferences = [
        ['table' => 'discipleship_groups', 'column' => 'initiated_by_person_id', 'on_delete' => 'null', 'name' => 'dg_init_person_fk'],
        ['table' => 'discipleship_relationships', 'column' => 'mentor_person_id', 'on_delete' => 'null', 'name' => 'dr_mentor_fk'],
        ['table' => 'discipleship_relationships', 'column' => 'disciple_person_id', 'on_delete' => 'null', 'name' => 'dr_disciple_fk'],
        ['table' => 'discipleship_group_memberships', 'column' => 'person_id', 'on_delete' => 'null', 'name' => 'dgm_person_fk'],
        ['table' => 'discipleship_group_leaderships', 'column' => 'person_id', 'on_delete' => 'null', 'name' => 'dgl_person_fk'],
        ['table' => 'discipleship_group_multiplications', 'column' => 'initiated_by_person_id', 'on_delete' => 'null', 'name' => 'dg_mult_init_fk'],
        ['table' => 'discipleship_group_people', 'column' => 'person_id', 'on_delete' => 'null', 'name' => 'dgp_person_fk'],
        ['table' => 'discipleship_meeting_reports', 'column' => 'leader_person_id', 'on_delete' => 'null', 'name' => 'dmr_leader_fk'],
        ['table' => 'discipleship_meeting_report_absences', 'column' => 'person_id', 'on_delete' => 'null', 'name' => 'dmra_person_fk'],
        ['table' => 'discipleship_meeting_report_meditation_sharers', 'column' => 'person_id', 'on_delete' => 'null', 'name' => 'dmrs_person_fk'],
        ['table' => 'discipleship_feedbacks', 'column' => 'leader_person_id', 'on_delete' => 'null', 'name' => 'df_leader_fk'],
        ['table' => 'discipleship_feedbacks', 'column' => 'respondent_person_id', 'on_delete' => 'null', 'name' => 'df_respondent_fk'],
        ['table' => 'discipleship_manual_journey_records', 'column' => 'person_id', 'on_delete' => 'cascade', 'name' => 'dmj_person_fk'],
    ];

    /** @var array<int, string> */
    private array $participantColumns = [
        'branch_id',
        'full_name',
        'gender',
        'birth_date',
        'birth_day_month',
        'birth_place',
        'address',
        'email',
        'whatsapp',
        'batch_month',
        'notes',
        'completed_at',
        'journey_bridge_status',
        'status',
        'session_numbers',
        'photos',
        'created_at',
        'updated_at',
    ];

    public function up(): void
    {
        if (Schema::hasTable('people') && ! Schema::hasTable('msk_participants')) {
            return;
        }

        if (! Schema::hasTable('msk_participants')) {
            return;
        }

        $this->ensureParticipantColumns();
        $this->backfillParticipantJson();

        if (Schema::hasTable('discipleship_people')) {
            $this->ensureParticipantLinkColumn();
            $this->assertNoDuplicateParticipantLinks();

            DB::transaction(function (): void {
                $this->ensureEveryDiscipleshipPersonHasParticipant();
                $personIdMap = $this->personIdMap();
                $this->mergeDiscipleshipPeopleIntoParticipants($personIdMap);
                $this->remapPersonReferences($personIdMap);
                $this->remapMeetingReportJson($personIdMap);
            });
        }

        Schema::disableForeignKeyConstraints();

        try {
            $this->dropLegacyForeignKeys();
            Schema::dropIfExists('msk_participant_photos');
            Schema::dropIfExists('msk_participant_sessions');

            if (! Schema::hasTable('people')) {
                Schema::rename('msk_participants', 'people');
            }

            $this->dropColumnIfExists('people', 'discipleship_person_id');
            Schema::dropIfExists('discipleship_people');
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->createPeopleForeignKeys();
    }

    public function down(): void
    {
        // The merged profile rows and remapped references cannot be split safely.
    }

    private function ensureParticipantColumns(): void
    {
        Schema::table('msk_participants', function (Blueprint $table): void {
            if (! Schema::hasColumn('msk_participants', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('msk_participants', 'full_name')) {
                $table->string('full_name')->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'gender')) {
                $table->string('gender', 40)->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'birth_day_month')) {
                $table->string('birth_day_month', 20)->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'birth_place')) {
                $table->string('birth_place', 120)->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'address')) {
                $table->text('address')->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'whatsapp')) {
                $table->string('whatsapp', 80)->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'batch_month')) {
                $table->string('batch_month', 20)->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'completed_at')) {
                $table->string('completed_at', 80)->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'journey_bridge_status')) {
                $table->string('journey_bridge_status', 80)->default('belum');
            }
            if (! Schema::hasColumn('msk_participants', 'status')) {
                $table->string('status', 80)->default('active');
            }
            if (! Schema::hasColumn('msk_participants', 'session_numbers')) {
                $table->json('session_numbers')->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'photos')) {
                $table->json('photos')->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (! Schema::hasColumn('msk_participants', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
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
        if (! Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            return;
        }

        $duplicate = DB::table('msk_participants')
            ->whereNotNull('discipleship_person_id')
            ->select('discipleship_person_id', DB::raw('COUNT(*) as total'))
            ->groupBy('discipleship_person_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException('Discipleship person '.$duplicate->discipleship_person_id.' is linked to multiple MSK participants.');
        }
    }

    private function backfillParticipantJson(): void
    {
        if (! Schema::hasColumn('msk_participants', 'session_numbers') || ! Schema::hasColumn('msk_participants', 'photos')) {
            return;
        }

        $sessionsByParticipant = [];
        if (Schema::hasTable('msk_participant_sessions')) {
            foreach (DB::table('msk_participant_sessions')->select(['msk_participant_id', 'session_number'])->orderBy('session_number')->get() as $row) {
                $number = (int) $row->session_number;
                if ($number >= 1 && $number <= 12) {
                    $sessionsByParticipant[(int) $row->msk_participant_id][$number] = $number;
                }
            }
        }

        $photosByParticipant = [];
        if (Schema::hasTable('msk_participant_photos')) {
            foreach (DB::table('msk_participant_photos')->orderBy('id')->get() as $row) {
                $path = trim((string) ($row->path ?? ''));
                if ($path === '') {
                    continue;
                }

                $photosByParticipant[(int) $row->msk_participant_id][$path] = [
                    'path' => $path,
                    'name' => trim((string) ($row->original_name ?? '')) ?: 'Foto',
                ];
            }
        }

        if ($sessionsByParticipant === [] && $photosByParticipant === []) {
            return;
        }

        foreach (DB::table('msk_participants')->select(['id', 'session_numbers', 'photos'])->orderBy('id')->get() as $participant) {
            $sessions = $this->jsonArray($participant->session_numbers ?? null);
            foreach ($sessionsByParticipant[(int) $participant->id] ?? [] as $number) {
                $sessions[] = $number;
            }
            $sessions = array_values(array_unique(array_filter(
                array_map('intval', $sessions),
                static fn (int $number): bool => $number >= 1 && $number <= 12,
            )));
            sort($sessions);

            $photos = [];
            foreach ($this->jsonArray($participant->photos ?? null) as $photo) {
                if (! is_array($photo)) {
                    continue;
                }
                $path = trim((string) ($photo['path'] ?? ''));
                if ($path !== '') {
                    $photos[$path] = $photo;
                }
            }
            foreach ($photosByParticipant[(int) $participant->id] ?? [] as $path => $photo) {
                $photos[$path] ??= $photo;
            }

            DB::table('msk_participants')
                ->where('id', (int) $participant->id)
                ->update($this->existingColumnValues('msk_participants', [
                    'session_numbers' => json_encode($sessions),
                    'photos' => json_encode(array_values($photos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]));
        }
    }

    private function ensureEveryDiscipleshipPersonHasParticipant(): void
    {
        foreach (DB::table('discipleship_people')->select($this->existingColumns('discipleship_people', [
            'id',
            'branch_id',
            'branch_code',
            'public_id',
            'member_public_id',
            'full_name',
            'phone',
            'gender',
            'status',
            'notes',
            'created_at',
            'updated_at',
        ]))->orderBy('id')->get() as $person) {
            if ($this->linkedParticipant((int) $person->id) !== null) {
                continue;
            }

            $participant = $this->matchingUnlinkedParticipant($person);
            if ($participant !== null) {
                DB::table('msk_participants')
                    ->where('id', (int) $participant->id)
                    ->update(['discipleship_person_id' => (int) $person->id]);

                continue;
            }

            $this->insertPlaceholderParticipant($person);
        }
    }

    private function linkedParticipant(int $personId): ?object
    {
        if (! Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            return null;
        }

        return DB::table('msk_participants')
            ->where('discipleship_person_id', $personId)
            ->orderBy('id')
            ->first();
    }

    private function matchingUnlinkedParticipant(object $person): ?object
    {
        if (! $this->hasColumns('msk_participants', ['branch_id', 'full_name', 'whatsapp', 'discipleship_person_id'])) {
            return null;
        }

        $identityKey = $this->identityKey(
            (string) ($person->full_name ?? ''),
            (string) ($person->phone ?? ''),
        );
        if ($identityKey === '') {
            return null;
        }

        $matches = DB::table('msk_participants')
            ->where('branch_id', $person->branch_id ?? null)
            ->whereNull('discipleship_person_id')
            ->get()
            ->filter(fn (object $participant): bool => $this->identityKey(
                (string) ($participant->full_name ?? ''),
                (string) ($participant->whatsapp ?? ''),
            ) === $identityKey)
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function insertPlaceholderParticipant(object $person): void
    {
        $timestamp = $person->created_at ?? now();
        $branchCode = trim((string) ($person->branch_code ?? '')) ?: 'legacy';
        $publicId = trim((string) ($person->public_id ?? '')) ?: ('person_'.$person->id);

        DB::table('msk_participants')->insert($this->existingColumnValues('msk_participants', [
            'public_id' => 'dg_'.$publicId,
            'branch_code' => $branchCode,
            'member_public_id' => trim((string) ($person->member_public_id ?? '')) ?: null,
            'branch_id' => $person->branch_id ?? null,
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
            'updated_at' => $person->updated_at ?? $timestamp,
        ]));
    }

    /**
     * @return array<int, int>
     */
    private function personIdMap(): array
    {
        if (! Schema::hasTable('discipleship_people') || ! Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            return [];
        }

        $map = [];
        foreach (DB::table('msk_participants')
            ->whereNotNull('discipleship_person_id')
            ->select(['id', 'discipleship_person_id'])
            ->orderBy('id')
            ->get() as $participant) {
            $personId = (int) $participant->discipleship_person_id;
            if ($personId > 0 && DB::table('discipleship_people')->where('id', $personId)->exists()) {
                $map[$personId] = (int) $participant->id;
            }
        }

        return $map;
    }

    /**
     * @param array<int, int> $personIdMap
     */
    private function mergeDiscipleshipPeopleIntoParticipants(array $personIdMap): void
    {
        if ($personIdMap === []) {
            return;
        }

        foreach (DB::table('discipleship_people')->whereIn('id', array_keys($personIdMap))->orderBy('id')->get() as $person) {
            $participantId = $personIdMap[(int) $person->id] ?? null;
            if ($participantId === null) {
                continue;
            }

            $participant = DB::table('msk_participants')->where('id', $participantId)->first();
            if ($participant === null) {
                continue;
            }

            $updates = [
                'status' => $this->mergedStatus(
                    (string) ($participant->status ?? ''),
                    (string) ($person->status ?? ''),
                ),
                'notes' => $this->mergedProfileNotes(
                    (string) ($participant->notes ?? ''),
                    (string) ($person->notes ?? ''),
                ),
                'updated_at' => $this->latestTimestamp(
                    (string) ($participant->updated_at ?? ''),
                    (string) ($person->updated_at ?? ''),
                ) ?? now(),
            ];

            if ($this->blank($participant->full_name ?? null) && ! $this->blank($person->full_name ?? null)) {
                $updates['full_name'] = trim((string) $person->full_name);
            }
            if ($this->blank($participant->gender ?? null) && ! $this->blank($person->gender ?? null)) {
                $updates['gender'] = trim((string) $person->gender);
            }
            if ($this->blank($participant->whatsapp ?? null) && ! $this->blank($person->phone ?? null)) {
                $updates['whatsapp'] = trim((string) $person->phone);
            }

            $createdAt = $this->earliestTimestamp(
                (string) ($participant->created_at ?? ''),
                (string) ($person->created_at ?? ''),
            );
            if ($createdAt !== null) {
                $updates['created_at'] = $createdAt;
            }

            DB::table('msk_participants')
                ->where('id', $participantId)
                ->update($this->existingColumnValues('msk_participants', $updates));
        }
    }

    /**
     * @param array<int, int> $personIdMap
     */
    private function remapPersonReferences(array $personIdMap): void
    {
        if ($personIdMap === []) {
            return;
        }

        foreach ($this->personReferences as $reference) {
            if (! Schema::hasTable($reference['table']) || ! Schema::hasColumn($reference['table'], $reference['column'])) {
                continue;
            }

            foreach (array_chunk($personIdMap, 250, true) as $chunk) {
                foreach ($chunk as $oldPersonId => $newPersonId) {
                    DB::table($reference['table'])
                        ->where($reference['column'], $oldPersonId)
                        ->update([$reference['column'] => $newPersonId]);
                }
            }
        }
    }

    /**
     * @param array<int, int> $personIdMap
     */
    private function remapMeetingReportJson(array $personIdMap): void
    {
        if ($personIdMap === [] || ! Schema::hasTable('discipleship_meeting_reports')) {
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
                $items = $this->jsonArray($report->{$column} ?? null);
                $changed = false;

                foreach ($items as &$item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $oldPersonId = (int) ($item['person_id'] ?? 0);
                    if (isset($personIdMap[$oldPersonId])) {
                        $item['person_id'] = $personIdMap[$oldPersonId];
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

    private function dropLegacyForeignKeys(): void
    {
        $tables = array_values(array_unique(array_merge(
            ['msk_participants', 'msk_participant_sessions', 'msk_participant_photos'],
            array_map(static fn (array $reference): string => $reference['table'], $this->personReferences),
        )));

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $foreignTable = $foreignKey['foreign_table'] ?? $foreignKey['foreignTable'] ?? null;
                $columns = $foreignKey['columns'] ?? [];
                $referencesLegacyTable = in_array($foreignTable, ['discipleship_people', 'msk_participants'], true);
                $referencesPersonColumn = in_array($columns[0] ?? '', array_merge(
                    ['discipleship_person_id', 'msk_participant_id'],
                    array_column($this->personReferences, 'column'),
                ), true);

                if (! $referencesLegacyTable && ! $referencesPersonColumn) {
                    continue;
                }

                $dropTarget = DB::getDriverName() === 'sqlite'
                    ? ($foreignKey['columns'] ?? [])
                    : $foreignKey['name'];
                if ($dropTarget === []) {
                    continue;
                }

                try {
                    Schema::table($table, static function (Blueprint $blueprint) use ($dropTarget): void {
                        $blueprint->dropForeign($dropTarget);
                    });
                } catch (Throwable) {
                    // Some test schemas do not materialize named foreign keys.
                }
            }
        }
    }

    private function createPeopleForeignKeys(): void
    {
        if (! Schema::hasTable('people')) {
            return;
        }

        foreach ($this->personReferences as $reference) {
            if (! Schema::hasTable($reference['table'])
                || ! Schema::hasColumn($reference['table'], $reference['column'])
                || $this->hasForeignKey($reference['table'], $reference['column'])) {
                continue;
            }

            try {
                Schema::table($reference['table'], static function (Blueprint $table) use ($reference): void {
                    $foreign = $table->foreign($reference['column'], $reference['name'])
                        ->references('id')
                        ->on('people');

                    if ($reference['on_delete'] === 'cascade') {
                        $foreign->cascadeOnDelete();
                    } else {
                        $foreign->nullOnDelete();
                    }
                });
            } catch (Throwable) {
                // Existing installations may already have equivalent constraints with legacy names.
            }
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (($foreignKey['columns'] ?? []) === [$column]) {
                return true;
            }
        }

        return false;
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $this->dropIndexesUsingColumns($table, [$column]);

        Schema::table($table, static function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropColumn($column);
        });
    }

    /** @param array<int, string> $columns */
    private function dropIndexesUsingColumns(string $tableName, array $columns): void
    {
        foreach (Schema::getIndexes($tableName) as $index) {
            if (($index['primary'] ?? false) || array_intersect($columns, $index['columns'] ?? []) === []) {
                continue;
            }

            Schema::table($tableName, static function (Blueprint $table) use ($index): void {
                if ($index['unique']) {
                    $table->dropUnique($index['name']);
                } else {
                    $table->dropIndex($index['name']);
                }
            });
        }
    }

    private function mergedStatus(string $participantStatus, string $personStatus): string
    {
        return strtolower(trim($participantStatus)) === 'inactive'
            || strtolower(trim($personStatus)) === 'inactive'
            ? 'inactive'
            : 'active';
    }

    private function mergedProfileNotes(string $participantNotes, string $personNotes): ?string
    {
        $participantNotes = trim($participantNotes);
        $personNotes = trim($personNotes);

        if ($personNotes === '') {
            return $participantNotes !== '' ? $participantNotes : null;
        }

        if ($participantNotes === '') {
            return $personNotes;
        }

        if ($participantNotes === $personNotes) {
            return $participantNotes;
        }

        return $participantNotes."\n\nCatatan pemuridan:\n".$personNotes;
    }

    private function identityKey(string $fullName, string $whatsapp): string
    {
        $nameKey = strtolower(trim(preg_replace('/\s+/', ' ', $fullName) ?? $fullName));
        $whatsappKey = $this->normalizeWhatsappDigits($whatsapp);

        return $nameKey !== '' && $whatsappKey !== '' ? $nameKey.'|'.$whatsappKey : '';
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

    private function blank(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }

    private function earliestTimestamp(string $left, string $right): ?string
    {
        return $this->timestampBoundary([$left, $right], 'min');
    }

    private function latestTimestamp(string $left, string $right): ?string
    {
        return $this->timestampBoundary([$left, $right], 'max');
    }

    /**
     * @param array<int, string> $values
     */
    private function timestampBoundary(array $values, string $mode): ?string
    {
        $selectedValue = null;
        $selectedTimestamp = null;

        foreach ($values as $value) {
            $value = trim($value);
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

    /**
     * @return array<int, mixed>
     */
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

    /**
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    private function existingColumns(string $table, array $columns): array
    {
        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function existingColumnValues(string $table, array $values): array
    {
        return array_filter(
            $values,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param array<int, string> $columns
     */
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
