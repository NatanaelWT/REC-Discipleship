<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, array{branch_id:int, external_person_id:int, canonical_branch_id:int, canonical_person_id:int}> */
    private array $mappings = [
        ['branch_id' => 1, 'external_person_id' => 790, 'canonical_branch_id' => 2, 'canonical_person_id' => 626],
        ['branch_id' => 3, 'external_person_id' => 776, 'canonical_branch_id' => 2, 'canonical_person_id' => 626],
        ['branch_id' => 6, 'external_person_id' => 854, 'canonical_branch_id' => 1, 'canonical_person_id' => 664],
        ['branch_id' => 4, 'external_person_id' => 850, 'canonical_branch_id' => 2, 'canonical_person_id' => 587],
        ['branch_id' => 3, 'external_person_id' => 774, 'canonical_branch_id' => 2, 'canonical_person_id' => 583],
    ];

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
        ['table' => 'discipleship_meeting_absences', 'column' => 'person_id'],
        ['table' => 'discipleship_meeting_meditation_sharers', 'column' => 'person_id'],
        ['table' => 'discipleship_feedbacks', 'column' => 'leader_person_id'],
        ['table' => 'discipleship_feedbacks', 'column' => 'respondent_person_id'],
    ];

    /** @var array<int, array{table:string,column:string}> */
    private array $jsonPersonReferences = [
        ['table' => 'discipleship_meeting_reports', 'column' => 'absences'],
        ['table' => 'discipleship_meeting_reports', 'column' => 'meditation_sharers'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('discipleship_people')) {
            return;
        }

        $changedBranches = [];

        DB::transaction(function () use (&$changedBranches): void {
            foreach ($this->mappings as $mapping) {
                if (! $this->canonicalExists($mapping)) {
                    continue;
                }

                $this->relinkPersonReferences($mapping);
                $this->relinkManualJourneyRecords($mapping);
                $this->relinkMskParticipant($mapping);
                $this->relinkJsonPersonReferences($mapping);
                $this->deleteExternalPerson($mapping);

                $changedBranches[] = (int) $mapping['branch_id'];
                $changedBranches[] = (int) $mapping['canonical_branch_id'];
            }
        });

        $this->invalidateDiscipleshipReadCache($changedBranches);
    }

    public function down(): void
    {
        // Intentionally irreversible: duplicate external identities are merged into canonical people.
    }

    /** @param array{canonical_branch_id:int, canonical_person_id:int} $mapping */
    private function canonicalExists(array $mapping): bool
    {
        return DB::table('discipleship_people')
            ->where('id', $mapping['canonical_person_id'])
            ->where('branch_id', $mapping['canonical_branch_id'])
            ->exists();
    }

    /** @param array{external_person_id:int, canonical_person_id:int} $mapping */
    private function relinkPersonReferences(array $mapping): void
    {
        foreach ($this->personReferences as $reference) {
            if (! $this->hasColumns($reference['table'], [$reference['column']])) {
                continue;
            }

            DB::table($reference['table'])
                ->where($reference['column'], $mapping['external_person_id'])
                ->update($this->valuesWithTimestamp($reference['table'], [
                    $reference['column'] => $mapping['canonical_person_id'],
                ]));
        }
    }

    /** @param array{external_person_id:int, canonical_person_id:int} $mapping */
    private function relinkManualJourneyRecords(array $mapping): void
    {
        if (! $this->hasColumns('discipleship_manual_journey_records', ['id', 'branch_id', 'person_id', 'stage'])) {
            return;
        }

        $rows = DB::table('discipleship_manual_journey_records')
            ->where('person_id', $mapping['external_person_id'])
            ->get(['id', 'branch_id', 'stage']);

        foreach ($rows as $row) {
            $hasCanonicalStage = DB::table('discipleship_manual_journey_records')
                ->where('branch_id', $row->branch_id)
                ->where('person_id', $mapping['canonical_person_id'])
                ->where('stage', $row->stage)
                ->where('id', '!=', $row->id)
                ->exists();

            if ($hasCanonicalStage) {
                DB::table('discipleship_manual_journey_records')
                    ->where('id', $row->id)
                    ->delete();

                continue;
            }

            DB::table('discipleship_manual_journey_records')
                ->where('id', $row->id)
                ->update($this->valuesWithTimestamp('discipleship_manual_journey_records', [
                    'person_id' => $mapping['canonical_person_id'],
                ]));
        }
    }

    /** @param array{external_person_id:int, canonical_person_id:int} $mapping */
    private function relinkMskParticipant(array $mapping): void
    {
        if (! $this->hasColumns('msk_participants', ['discipleship_person_id'])) {
            return;
        }

        $hasCanonicalParticipant = DB::table('msk_participants')
            ->where('discipleship_person_id', $mapping['canonical_person_id'])
            ->exists();
        $replacement = $hasCanonicalParticipant ? null : $mapping['canonical_person_id'];

        DB::table('msk_participants')
            ->where('discipleship_person_id', $mapping['external_person_id'])
            ->update($this->valuesWithTimestamp('msk_participants', [
                'discipleship_person_id' => $replacement,
            ]));
    }

    /** @param array{external_person_id:int, canonical_person_id:int} $mapping */
    private function relinkJsonPersonReferences(array $mapping): void
    {
        foreach ($this->jsonPersonReferences as $reference) {
            if (! $this->hasColumns($reference['table'], ['id', $reference['column']])) {
                continue;
            }

            DB::table($reference['table'])
                ->where($reference['column'], 'like', '%'.$mapping['external_person_id'].'%')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($mapping, $reference): void {
                    foreach ($rows as $row) {
                        $raw = $row->{$reference['column']};
                        if (! is_string($raw) || trim($raw) === '') {
                            continue;
                        }

                        $decoded = json_decode($raw, true);
                        if (! is_array($decoded)) {
                            continue;
                        }

                        $changed = false;
                        $updated = $this->replaceNestedPersonId(
                            $decoded,
                            $mapping['external_person_id'],
                            $mapping['canonical_person_id'],
                            $changed
                        );

                        if (! $changed) {
                            continue;
                        }

                        DB::table($reference['table'])
                            ->where('id', $row->id)
                            ->update($this->valuesWithTimestamp($reference['table'], [
                                $reference['column'] => json_encode($updated, JSON_UNESCAPED_UNICODE),
                            ]));
                    }
                });
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function replaceNestedPersonId(mixed $value, int $externalPersonId, int $canonicalPersonId, bool &$changed): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $child) {
            if ($key === 'person_id' && (string) $child === (string) $externalPersonId) {
                $value[$key] = is_string($child) ? (string) $canonicalPersonId : $canonicalPersonId;
                $changed = true;
                continue;
            }

            $value[$key] = $this->replaceNestedPersonId($child, $externalPersonId, $canonicalPersonId, $changed);
        }

        return $value;
    }

    /** @param array{branch_id:int, external_person_id:int} $mapping */
    private function deleteExternalPerson(array $mapping): void
    {
        DB::table('discipleship_people')
            ->where('id', $mapping['external_person_id'])
            ->where('branch_id', $mapping['branch_id'])
            ->delete();
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

    /** @param array<string, mixed> $values */
    private function valuesWithTimestamp(string $table, array $values): array
    {
        if (Schema::hasColumn($table, 'updated_at')) {
            $values['updated_at'] = now();
        }

        return $values;
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
