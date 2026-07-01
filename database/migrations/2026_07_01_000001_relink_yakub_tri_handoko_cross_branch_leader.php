<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KUTISARI_BRANCH_ID = 1;

    private const GM_BRANCH_ID = 2;

    private const KUTISARI_EXTERNAL_PERSON_ID = 790;

    private const GM_PERSON_ID = 626;

    public function up(): void
    {
        if (! Schema::hasTable('discipleship_people')) {
            return;
        }

        $hasExternal = DB::table('discipleship_people')
            ->where('id', self::KUTISARI_EXTERNAL_PERSON_ID)
            ->where('branch_id', self::KUTISARI_BRANCH_ID)
            ->exists();
        $hasCanonical = DB::table('discipleship_people')
            ->where('id', self::GM_PERSON_ID)
            ->where('branch_id', self::GM_BRANCH_ID)
            ->exists();

        if (! $hasExternal || ! $hasCanonical) {
            return;
        }

        DB::transaction(function (): void {
            $this->relinkGroupLeaderships();
            $this->relinkMentorships();
            $this->relinkGroupInitiators();
            $this->relinkReports();
            $this->relinkFeedbacks();
            $this->closeExternalRootRelations();
            $this->archiveExternalPerson();
        });

        $this->invalidateDiscipleshipReadCache();
    }

    public function down(): void
    {
        // Intentionally irreversible: this migration preserves the canonical GM person link.
    }

    private function relinkGroupLeaderships(): void
    {
        if (! $this->hasColumns('discipleship_group_people', ['branch_id', 'person_id', 'role'])) {
            return;
        }

        DB::table('discipleship_group_people')
            ->where('branch_id', self::KUTISARI_BRANCH_ID)
            ->where('person_id', self::KUTISARI_EXTERNAL_PERSON_ID)
            ->where('role', '!=', 'member')
            ->update($this->valuesWithTimestamp('discipleship_group_people', [
                'person_id' => self::GM_PERSON_ID,
            ]));
    }

    private function relinkMentorships(): void
    {
        if (! $this->hasColumns('discipleship_relationships', ['branch_id', 'mentor_person_id'])) {
            return;
        }

        DB::table('discipleship_relationships')
            ->where('branch_id', self::KUTISARI_BRANCH_ID)
            ->where('mentor_person_id', self::KUTISARI_EXTERNAL_PERSON_ID)
            ->update($this->valuesWithTimestamp('discipleship_relationships', [
                'mentor_person_id' => self::GM_PERSON_ID,
            ]));
    }

    private function relinkGroupInitiators(): void
    {
        if (! $this->hasColumns('discipleship_groups', ['branch_id', 'initiated_by_person_id'])) {
            return;
        }

        DB::table('discipleship_groups')
            ->where('branch_id', self::KUTISARI_BRANCH_ID)
            ->where('initiated_by_person_id', self::KUTISARI_EXTERNAL_PERSON_ID)
            ->update($this->valuesWithTimestamp('discipleship_groups', [
                'initiated_by_person_id' => self::GM_PERSON_ID,
            ]));

        if ($this->hasColumns('discipleship_group_multiplications', ['branch_id', 'initiated_by_person_id'])) {
            DB::table('discipleship_group_multiplications')
                ->where('branch_id', self::KUTISARI_BRANCH_ID)
                ->where('initiated_by_person_id', self::KUTISARI_EXTERNAL_PERSON_ID)
                ->update($this->valuesWithTimestamp('discipleship_group_multiplications', [
                    'initiated_by_person_id' => self::GM_PERSON_ID,
                ]));
        }
    }

    private function relinkReports(): void
    {
        if (! $this->hasColumns('discipleship_meeting_reports', ['branch_id', 'leader_person_id'])) {
            return;
        }

        DB::table('discipleship_meeting_reports')
            ->where('branch_id', self::KUTISARI_BRANCH_ID)
            ->where('leader_person_id', self::KUTISARI_EXTERNAL_PERSON_ID)
            ->update($this->valuesWithTimestamp('discipleship_meeting_reports', [
                'leader_person_id' => self::GM_PERSON_ID,
            ]));
    }

    private function relinkFeedbacks(): void
    {
        if (! $this->hasColumns('discipleship_feedbacks', ['branch_id', 'leader_person_id'])) {
            return;
        }

        DB::table('discipleship_feedbacks')
            ->where('branch_id', self::KUTISARI_BRANCH_ID)
            ->where('leader_person_id', self::KUTISARI_EXTERNAL_PERSON_ID)
            ->update($this->valuesWithTimestamp('discipleship_feedbacks', [
                'leader_person_id' => self::GM_PERSON_ID,
            ]));
    }

    private function closeExternalRootRelations(): void
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
            ->where('branch_id', self::KUTISARI_BRANCH_ID)
            ->where('disciple_person_id', self::KUTISARI_EXTERNAL_PERSON_ID)
            ->where('status', 'active')
            ->update($this->valuesWithTimestamp('discipleship_relationships', $values));
    }

    private function archiveExternalPerson(): void
    {
        DB::table('discipleship_people')
            ->where('id', self::KUTISARI_EXTERNAL_PERSON_ID)
            ->where('branch_id', self::KUTISARI_BRANCH_ID)
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

    private function invalidateDiscipleshipReadCache(): void
    {
        $store = Cache::store(app()->environment('testing') ? 'array' : 'file');
        $version = (string) hrtime(true);

        foreach ([self::KUTISARI_BRANCH_ID, self::GM_BRANCH_ID] as $branchId) {
            $store->forever('rec.discipleship-read.version.branch.'.$branchId, $version);
        }
    }
};
