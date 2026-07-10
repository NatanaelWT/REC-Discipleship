<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DgJournalGroupNameSnapshotMigrationTest extends TestCase
{
    public function test_group_name_snapshot_is_removed_from_dg_journal_tables(): void
    {
        foreach (['jurnal_temu_dg', 'jurnal_umpan_balik'] as $tableName) {
            Schema::dropIfExists($tableName);
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('discipleship_group_id')->nullable();
                $table->string('group_name_snapshot')->nullable();
                $table->timestamps();
            });
        }

        $migration = require database_path('migrations/2026_07_10_000001_drop_group_name_snapshot_from_dg_journals.php');
        $migration->up();
        $migration->up();

        $this->assertFalse(Schema::hasColumn('jurnal_temu_dg', 'group_name_snapshot'));
        $this->assertFalse(Schema::hasColumn('jurnal_umpan_balik', 'group_name_snapshot'));

        $migration->down();

        $this->assertTrue(Schema::hasColumn('jurnal_temu_dg', 'group_name_snapshot'));
        $this->assertTrue(Schema::hasColumn('jurnal_umpan_balik', 'group_name_snapshot'));
    }
}
