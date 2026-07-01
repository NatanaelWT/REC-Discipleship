<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, array{branch_id:int, external_person_id:int, canonical_branch_id:int, canonical_person_id:int, canonical_label:string}> */
    private array $mappings = [
        [
            'branch_id' => 4,
            'external_person_id' => 850,
            'canonical_branch_id' => 2,
            'canonical_person_id' => 587,
            'canonical_label' => 'David Antonius',
        ],
        [
            'branch_id' => 3,
            'external_person_id' => 774,
            'canonical_branch_id' => 2,
            'canonical_person_id' => 583,
            'canonical_label' => 'Anneke Aryanti',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('discipleship_people')) {
            return;
        }

        $changedBranches = [];
        DB::transaction(function () use (&$changedBranches): void {
            foreach ($this->mappings as $mapping) {
                if (! $this->canRelink($mapping)) {
                    continue;
                }

                $this->relinkGroupLeaderships($mapping);
                $this->relinkMentorships($mapping);
                $this->relinkGroupInitiators($mapping);
                $this->relinkReports($mapping);
                $this->relinkFeedbacks($mapping);
                $this->closeExternalRootRelations($mapping);
                $this->archiveExternalPerson($mapping);

                $changedBranches[] = (int) $mapping['branch_id'];
                $changedBranches[] = (int) $mapping['canonical_branch_id'];
            }
        });

        $this->invalidateDiscipleshipReadCache($changedBranches);
    }

    public function down(): void
    {
        // Intentionally irreversible: this migration preserves canonical GM person links.
    }

    /** @param array{branch_id:int, external_person_id:int, canonical_branch_id:int, canonical_person_id:int, canonical_label:string} $mapping */
    private function canRelink(array $mapping): bool
    {
        $hasExternal = DB::table('discipleship_people')
            ->where('id', $mapping['external_person_id'])
            ->where('branch_id', $mapping['branch_id'])
            ->exists();
        $hasCanonical = DB::table('discipleship_people')
            ->where('id', $mapping['canonical_person_id'])
            ->where('branch_id', $mapping['canonical_branch_id'])
            ->exists();

        return $hasExternal && $hasCanonical;
    }

    /** @param array{branch_id:int, external_person_id:int, canonical_person_id:int} $mapping */
    private function relinkGroupLeaderships(array $mapping): void
    {
        if (! $this->hasColumns('discipleship_group_people', ['branch_id', 'person_id', 'role'])) {
            return;
        }

        DB::table('discipleship_group_people')
            ->where('branch_id', $mapping['branch_id'])
            ->where('person_id', $mapping['external_person_id'])
            ->where('role', '!=', 'member')
            ->update($this->valuesWithTimestamp('discipleship_group_people', [
                'person_id' => $mapping['canonical_person_id'],
            ]));
    }

    /** @param array{branch_id:int, external_person_id:int, canonical_person_id:int} $mapping */
    private function relinkMentorships(array $mapping): void
    {
        if (! $this->hasColumns('discipleship_relationships', ['branch_id', 'mentor_person_id'])) {
            return;
        }

        DB::table('discipleship_relationships')
            ->where('branch_id', $mapping['branch_id'])
            ->where('mentor_person_id', $mapping['external_person_id'])
            ->update($this->valuesWithTimestamp('discipleship_relationships', [
                'mentor_person_id' => $mapping['canonical_person_id'],
            ]));
    }

    /** @param array{branch_id:int, external_person_id:int, canonical_person_id:int} $mapping */
    private function relinkGroupInitiators(array $mapping): void
    {
        if (! $this->hasColumns('discipleship_groups', ['branch_id', 'initiated_by_person_id'])) {
            return;
        }

        DB::table('discipleship_groups')
            ->where('branch_id', $mapping['branch_id'])
            ->where('initiated_by_person_id', $mapping['external_person_id'])
            ->update($this->valuesWithTimestamp('discipleship_groups', [
                'initiated_by_person_id' => $mapping['canonical_person_id'],
            ]));

        if ($this->hasColumns('discipleship_group_multiplications', ['branch_id', 'initiated_by_person_id'])) {
            DB::table('discipleship_group_multiplications')
                ->where('branch_id', $mapping['branch_id'])
                ->where('initiated_by_person_id', $mapping['external_person_id'])
                ->update($this->valuesWithTimestamp('discipleship_group_multiplications', [
                    'initiated_by_person_id' => $mapping['canonical_person_id'],
                ]));
        }
    }

    /** @param array{branch_id:int, external_person_id:int, canonical_person_id:int, canonical_label:string} $mapping */
    private function relinkReports(array $mapping): void
    {
        if (! $this->hasColumns('discipleship_meeting_reports', ['branch_id', 'leader_person_id'])) {
            return;
        }

        DB::table('discipleship_meeting_reports')
            ->where('branch_id', $mapping['branch_id'])
            ->where('leader_person_id', $mapping['external_person_id'])
            ->update($this->valuesWithTimestamp('discipleship_meeting_reports', [
                'leader_person_id' => $mapping['canonical_person_id'],
            ]));
    }

    /** @param array{branch_id:int, external_person_id:int, canonical_person_id:int} $mapping */
    private function relinkFeedbacks(array $mapping): void
    {
        if (! $this->hasColumns('discipleship_feedbacks', ['branch_id', 'leader_person_id'])) {
            return;
        }

        DB::table('discipleship_feedbacks')
            ->where('branch_id', $mapping['branch_id'])
            ->where('leader_person_id', $mapping['external_person_id'])
            ->update($this->valuesWithTimestamp('discipleship_feedbacks', [
                'leader_person_id' => $mapping['canonical_person_id'],
            ]));

        if (! Schema::hasColumn('discipleship_feedbacks', 'respondent_person_id')) {
            return;
        }

        DB::table('discipleship_feedbacks')
            ->where('branch_id', $mapping['branch_id'])
            ->where('respondent_person_id', $mapping['external_person_id'])
            ->update($this->valuesWithTimestamp('discipleship_feedbacks', [
                'respondent_person_id' => $mapping['canonical_person_id'],
            ]));
    }

    /** @param array{branch_id:int, external_person_id:int} $mapping */
    private function closeExternalRootRelations(array $mapping): void
    {
        if (! $this->hasColumns('discipleship_relationships', ['branch_id', 'disciple_person_id', 'status'])) {
            return;
        }

        $values = ['status' => 'closed'];
        if (Schema::hasColumn('discipleship_relationships', 'end_date')) {
            $values['end_date'] = date('Y-m-d');
        }
        if (Schema::hasColumn('discipleship_relationships', 'reason_end')) {
            $values['reason_end'] = 'converted_to_cross_branch_leader';
        }

        DB::table('discipleship_relationships')
            ->where('branch_id', $mapping['branch_id'])
            ->where('disciple_person_id', $mapping['external_person_id'])
            ->where('status', 'active')
            ->update($this->valuesWithTimestamp('discipleship_relationships', $values));
    }

    /** @param array{branch_id:int, external_person_id:int} $mapping */
    private function archiveExternalPerson(array $mapping): void
    {
        DB::table('discipleship_people')
            ->where('id', $mapping['external_person_id'])
            ->where('branch_id', $mapping['branch_id'])
            ->update($this->valuesWithTimestamp('discipleship_people', [
                'status' => 'inactive',
            ]));
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
