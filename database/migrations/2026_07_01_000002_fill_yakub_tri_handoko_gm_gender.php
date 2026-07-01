<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KUTISARI_BRANCH_ID = 1;

    private const GM_BRANCH_ID = 2;

    private const GM_PERSON_ID = 626;

    public function up(): void
    {
        if (! $this->hasColumns('discipleship_people', ['id', 'branch_id', 'full_name', 'gender'])) {
            return;
        }

        DB::table('discipleship_people')
            ->where('id', self::GM_PERSON_ID)
            ->where('branch_id', self::GM_BRANCH_ID)
            ->where('full_name', 'Yakub Tri Handoko')
            ->where(static function ($query): void {
                $query->whereNull('gender')->orWhere('gender', '');
            })
            ->update($this->valuesWithTimestamp('discipleship_people', [
                'gender' => 'Laki-laki',
            ]));

        $this->invalidateDiscipleshipReadCache();
    }

    public function down(): void
    {
        // Intentionally irreversible: this migration fills missing profile data for the canonical GM person.
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
