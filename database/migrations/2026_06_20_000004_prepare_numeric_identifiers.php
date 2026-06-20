<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, array{table:string,foreign:string,legacy:string,target:string}>
     */
    private array $references = [
        ['table' => 'discipleship_groups', 'foreign' => 'parent_group_id', 'legacy' => 'parent_group_public_id', 'target' => 'discipleship_groups'],
        ['table' => 'discipleship_groups', 'foreign' => 'source_group_id', 'legacy' => 'source_group_public_id', 'target' => 'discipleship_groups'],
        ['table' => 'discipleship_groups', 'foreign' => 'initiated_by_person_id', 'legacy' => 'initiated_by_person_public_id', 'target' => 'discipleship_people'],
        ['table' => 'discipleship_relationships', 'foreign' => 'mentor_person_id', 'legacy' => 'mentor_person_public_id', 'target' => 'discipleship_people'],
        ['table' => 'discipleship_relationships', 'foreign' => 'disciple_person_id', 'legacy' => 'disciple_person_public_id', 'target' => 'discipleship_people'],
        ['table' => 'discipleship_relationships', 'foreign' => 'context_group_id', 'legacy' => 'context_group_public_id', 'target' => 'discipleship_groups'],
        ['table' => 'discipleship_group_people', 'foreign' => 'discipleship_group_id', 'legacy' => 'group_public_id', 'target' => 'discipleship_groups'],
        ['table' => 'discipleship_group_people', 'foreign' => 'person_id', 'legacy' => 'person_public_id', 'target' => 'discipleship_people'],
        ['table' => 'discipleship_meeting_reports', 'foreign' => 'leader_person_id', 'legacy' => 'leader_person_public_id', 'target' => 'discipleship_people'],
        ['table' => 'discipleship_meeting_reports', 'foreign' => 'discipleship_group_id', 'legacy' => 'discipleship_group_public_id', 'target' => 'discipleship_groups'],
    ];

    /**
     * @var array<int, array{table:string,column:string,target:string,on_delete:string,name:string}>
     */
    private array $foreignKeys = [
        ['table' => 'discipleship_groups', 'column' => 'parent_group_id', 'target' => 'discipleship_groups', 'on_delete' => 'null', 'name' => 'dg_groups_parent_numeric_fk'],
        ['table' => 'discipleship_groups', 'column' => 'source_group_id', 'target' => 'discipleship_groups', 'on_delete' => 'null', 'name' => 'dg_groups_source_numeric_fk'],
        ['table' => 'discipleship_groups', 'column' => 'initiated_by_person_id', 'target' => 'discipleship_people', 'on_delete' => 'null', 'name' => 'dg_groups_initiator_numeric_fk'],
        ['table' => 'discipleship_relationships', 'column' => 'mentor_person_id', 'target' => 'discipleship_people', 'on_delete' => 'null', 'name' => 'dg_relations_mentor_numeric_fk'],
        ['table' => 'discipleship_relationships', 'column' => 'disciple_person_id', 'target' => 'discipleship_people', 'on_delete' => 'null', 'name' => 'dg_relations_disciple_numeric_fk'],
        ['table' => 'discipleship_relationships', 'column' => 'context_group_id', 'target' => 'discipleship_groups', 'on_delete' => 'null', 'name' => 'dg_relations_context_numeric_fk'],
        ['table' => 'discipleship_group_people', 'column' => 'discipleship_group_id', 'target' => 'discipleship_groups', 'on_delete' => 'cascade', 'name' => 'dg_group_people_group_numeric_fk'],
        ['table' => 'discipleship_group_people', 'column' => 'person_id', 'target' => 'discipleship_people', 'on_delete' => 'null', 'name' => 'dg_group_people_person_numeric_fk'],
        ['table' => 'discipleship_meeting_reports', 'column' => 'leader_person_id', 'target' => 'discipleship_people', 'on_delete' => 'null', 'name' => 'dg_reports_leader_numeric_fk'],
        ['table' => 'discipleship_meeting_reports', 'column' => 'discipleship_group_id', 'target' => 'discipleship_groups', 'on_delete' => 'null', 'name' => 'dg_reports_group_numeric_fk'],
        ['table' => 'discipleship_feedbacks', 'column' => 'discipleship_group_id', 'target' => 'discipleship_groups', 'on_delete' => 'null', 'name' => 'dg_feedbacks_group_numeric_fk'],
        ['table' => 'discipleship_feedbacks', 'column' => 'leader_person_id', 'target' => 'discipleship_people', 'on_delete' => 'null', 'name' => 'dg_feedbacks_leader_numeric_fk'],
        ['table' => 'discipleship_feedbacks', 'column' => 'respondent_person_id', 'target' => 'discipleship_people', 'on_delete' => 'null', 'name' => 'dg_feedbacks_respondent_numeric_fk'],
        ['table' => 'msk_participants', 'column' => 'discipleship_person_id', 'target' => 'discipleship_people', 'on_delete' => 'null', 'name' => 'msk_participants_person_numeric_fk'],
    ];

    public function up(): void
    {
        $referenceUpdates = $this->resolveReferenceUpdates();
        $participantUpdates = $this->resolveParticipantLinks();
        $reportUpdates = $this->resolveReportJson();

        if (Schema::hasTable('msk_participants') && ! Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            Schema::table('msk_participants', static function (Blueprint $table): void {
                $table->foreignId('discipleship_person_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('discipleship_people')
                    ->nullOnDelete();
            });
        }

        DB::transaction(function () use ($referenceUpdates, $participantUpdates, $reportUpdates): void {
            foreach ($referenceUpdates as $update) {
                DB::table($update['table'])->where('id', $update['id'])->update([
                    $update['foreign'] => $update['value'],
                ]);
            }

            foreach ($participantUpdates as $participantId => $personId) {
                DB::table('msk_participants')->where('id', $participantId)->update([
                    'discipleship_person_id' => $personId,
                ]);
            }

            foreach ($reportUpdates as $reportId => $payload) {
                DB::table('discipleship_meeting_reports')->where('id', $reportId)->update($payload);
            }
        });

        $this->ensureParticipantLinkIsUnique();
        $this->validateNumericReferences();
        $this->ensureNumericForeignKeys();
    }

    public function down(): void
    {
        if (! Schema::hasTable('msk_participants') || ! Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            return;
        }

        Schema::table('msk_participants', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discipleship_person_id');
        });
    }

    /**
     * @return array<int, array{table:string,id:int,foreign:string,value:int}>
     */
    private function resolveReferenceUpdates(): array
    {
        $updates = [];
        foreach ($this->references as $reference) {
            if (! $this->hasColumns($reference['table'], ['id', 'branch_id', $reference['foreign'], $reference['legacy']])
                || ! $this->hasColumns($reference['target'], ['id', 'branch_id', 'public_id'])) {
                continue;
            }

            foreach (DB::table($reference['table'])->select(['id', 'branch_id', $reference['foreign'], $reference['legacy']])->orderBy('id')->get() as $row) {
                $legacyId = trim((string) ($row->{$reference['legacy']} ?? ''));
                if ($legacyId === '') {
                    if ($row->{$reference['foreign']} !== null && ! DB::table($reference['target'])
                        ->where('id', $row->{$reference['foreign']})
                        ->where('branch_id', $row->branch_id)
                        ->exists()) {
                        throw new RuntimeException("Invalid cross-branch reference on {$reference['table']} row {$row->id}.");
                    }

                    continue;
                }

                $matches = DB::table($reference['target'])
                    ->where('branch_id', $row->branch_id)
                    ->where('public_id', $legacyId)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();

                if (count($matches) !== 1) {
                    throw new RuntimeException("Cannot resolve {$reference['table']}.{$reference['legacy']} for row {$row->id}.");
                }

                $resolvedId = $matches[0];
                if ($row->{$reference['foreign']} !== null && (int) $row->{$reference['foreign']} !== $resolvedId) {
                    throw new RuntimeException("Conflicting numeric and legacy references on {$reference['table']} row {$row->id}.");
                }

                if ($row->{$reference['foreign']} === null) {
                    $updates[] = [
                        'table' => $reference['table'],
                        'id' => (int) $row->id,
                        'foreign' => $reference['foreign'],
                        'value' => $resolvedId,
                    ];
                }
            }
        }

        return $updates;
    }

    /** @return array<int, int|null> */
    private function resolveParticipantLinks(): array
    {
        if (! $this->hasColumns('msk_participants', ['id', 'branch_id', 'member_public_id'])
            || ! $this->hasColumns('discipleship_people', ['id', 'branch_id', 'public_id', 'member_public_id'])) {
            return [];
        }

        $updates = [];
        $claimedPeople = [];
        foreach (DB::table('msk_participants')->select(['id', 'branch_id', 'member_public_id', 'full_name', 'whatsapp'])->orderBy('id')->get() as $participant) {
            $legacyId = trim((string) ($participant->member_public_id ?? ''));
            if ($legacyId === '') {
                $updates[(int) $participant->id] = null;

                continue;
            }

            $matches = DB::table('discipleship_people')
                ->where('branch_id', $participant->branch_id)
                ->where(static function ($query) use ($legacyId): void {
                    $query->where('member_public_id', $legacyId)->orWhere('public_id', $legacyId);
                })
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (count($matches) > 1) {
                $identityMatches = DB::table('discipleship_people')
                    ->whereIn('id', $matches)
                    ->where('full_name', $participant->full_name)
                    ->when(
                        trim((string) ($participant->whatsapp ?? '')) !== '',
                        static fn ($query) => $query->where('phone', $participant->whatsapp),
                    )
                    ->orderByRaw("case when status = 'active' then 0 else 1 end")
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();
                if ($identityMatches !== []) {
                    $matches = $identityMatches;
                }
            }

            if (count($matches) > 1) {
                $activeMatches = DB::table('discipleship_people')
                    ->whereIn('id', $matches)
                    ->where('status', 'active')
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();
                $matches = count($activeMatches) === 1 ? $activeMatches : $matches;
            }

            if ($matches === []) {
                $updates[(int) $participant->id] = null;

                continue;
            }

            if (count($matches) !== 1) {
                throw new RuntimeException("Cannot resolve MSK participant {$participant->id} to one discipleship person.");
            }

            $personId = $matches[0];
            if (isset($claimedPeople[$personId])) {
                throw new RuntimeException("Discipleship person {$personId} is linked to multiple MSK participants.");
            }

            $claimedPeople[$personId] = true;
            $updates[(int) $participant->id] = $personId;
        }

        return $updates;
    }

    /** @return array<int, array<string, string>> */
    private function resolveReportJson(): array
    {
        if (! $this->hasColumns('discipleship_meeting_reports', ['id', 'branch_id', 'absences', 'meditation_sharers'])) {
            return [];
        }

        $updates = [];
        foreach (DB::table('discipleship_meeting_reports')->select(['id', 'branch_id', 'absences', 'meditation_sharers'])->orderBy('id')->get() as $report) {
            $payload = [];
            foreach (['absences', 'meditation_sharers'] as $column) {
                $items = json_decode((string) ($report->{$column} ?? '[]'), true);
                $items = is_array($items) ? $items : [];
                $normalized = [];

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $personId = isset($item['person_id']) && (int) $item['person_id'] > 0
                        ? (int) $item['person_id']
                        : $this->resolveReportPersonId((int) $report->branch_id, trim((string) ($item['person_public_id'] ?? '')));

                    if ($personId === null || ! DB::table('discipleship_people')
                        ->where('id', $personId)
                        ->where('branch_id', $report->branch_id)
                        ->exists()) {
                        throw new RuntimeException("Cannot resolve {$column} person on meeting report {$report->id}.");
                    }

                    $normalized[] = [
                        'person_id' => $personId,
                        'person_name_snapshot' => trim((string) ($item['person_name_snapshot'] ?? '')),
                    ];
                }

                $payload[$column] = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $updates[(int) $report->id] = $payload;
        }

        return $updates;
    }

    private function resolveReportPersonId(int $branchId, string $legacyId): ?int
    {
        if ($legacyId === '') {
            return null;
        }

        $matches = DB::table('discipleship_people')
            ->where('branch_id', $branchId)
            ->where(static function ($query) use ($legacyId): void {
                $query->where('public_id', $legacyId)->orWhere('member_public_id', $legacyId);
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function ensureParticipantLinkIsUnique(): void
    {
        if (! Schema::hasTable('msk_participants') || ! Schema::hasColumn('msk_participants', 'discipleship_person_id')) {
            return;
        }

        if (! Schema::hasIndex('msk_participants', ['discipleship_person_id'], 'unique')) {
            Schema::table('msk_participants', static function (Blueprint $table): void {
                $table->unique('discipleship_person_id', 'msk_participants_person_unique');
            });
        }
    }

    private function validateNumericReferences(): void
    {
        foreach ($this->foreignKeys as $reference) {
            if (! $this->hasColumns($reference['table'], ['id', 'branch_id', $reference['column']])
                || ! $this->hasColumns($reference['target'], ['id', 'branch_id'])) {
                continue;
            }

            $invalidRow = DB::table($reference['table'].' as source')
                ->leftJoin($reference['target'].' as target', 'target.id', '=', 'source.'.$reference['column'])
                ->whereNotNull('source.'.$reference['column'])
                ->where(static function ($query): void {
                    $query->whereNull('target.id')
                        ->orWhereNull('source.branch_id')
                        ->orWhereColumn('target.branch_id', '!=', 'source.branch_id');
                })
                ->value('source.id');

            if ($invalidRow !== null) {
                throw new RuntimeException("Invalid numeric reference on {$reference['table']} row {$invalidRow}.");
            }
        }
    }

    private function ensureNumericForeignKeys(): void
    {
        foreach ($this->foreignKeys as $reference) {
            if (! $this->hasColumns($reference['table'], [$reference['column']])
                || ! $this->hasColumns($reference['target'], ['id'])
                || $this->hasForeignKey($reference['table'], $reference['column'])) {
                continue;
            }

            Schema::table($reference['table'], static function (Blueprint $table) use ($reference): void {
                $foreign = $table->foreign($reference['column'], $reference['name'])
                    ->references('id')
                    ->on($reference['target']);

                if ($reference['on_delete'] === 'cascade') {
                    $foreign->cascadeOnDelete();
                } else {
                    $foreign->nullOnDelete();
                }
            });
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
