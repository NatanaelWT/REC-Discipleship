<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BranchIdOnlyMigrationTest extends TestCase
{
    public function test_branch_references_are_backfilled_and_legacy_columns_are_removed(): void
    {
        $this->createLegacyTables();
        $this->seedLegacyRows();

        $migration = require database_path('migrations/2026_06_20_000002_use_branch_id_only.php');
        $migration->up();

        $this->assertFalse(Schema::hasColumn('branches', 'code'));
        $this->assertFalse(Schema::hasColumn('branches', 'sort_order'));
        $this->assertTrue(Schema::hasColumn('branches', 'label'));

        foreach (['users', 'msk_participants'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'branch_code'));
            $this->assertTrue(Schema::hasColumn($table, 'branch_id'));
        }

        $this->assertFalse(Schema::hasColumn('worship_schedules', 'branch_code'));
        $this->assertFalse(Schema::hasColumn('worship_schedules', 'branch_id'));
        $this->assertDatabaseHas('users', ['username' => 'branch_user', 'branch_id' => 2]);
        $this->assertDatabaseHas('users', ['username' => 'central_user', 'branch_id' => null]);
        $this->assertDatabaseMissing('branches', ['label' => 'Pusat']);
        $this->assertDatabaseHas('msk_participants', ['legacy_key' => 'msk-1', 'branch_id' => 1]);

        $this->assertTrue($this->hasIndex('msk_participants', ['branch_id', 'legacy_key'], true));
    }

    private function createLegacyTables(): void
    {
        Schema::dropIfExists('worship_schedules');
        Schema::dropIfExists('msk_participants');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('branch_code', 40)->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
        });

        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_key');
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->unique(['branch_code', 'legacy_key'], 'msk_legacy_branch_key_unique');
        });

        Schema::create('worship_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7)->unique();
            $table->string('branch_code', 40)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->timestamps();
        });
    }

    private function seedLegacyRows(): void
    {
        DB::table('branches')->insert([
            [
                'id' => 1,
                'code' => 'kutisari',
                'label' => 'Kutisari',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'gm',
                'label' => 'GM',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 7,
                'code' => 'pusat',
                'label' => 'Pusat',
                'sort_order' => 6,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('users')->insert([
            [
                'username' => 'branch_user',
                'branch_code' => 'gm',
                'branch_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'username' => 'central_user',
                'branch_code' => 'pusat',
                'branch_id' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('msk_participants')->insert([
            'legacy_key' => 'msk-1',
            'branch_code' => 'kutisari',
            'branch_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('worship_schedules')->insert([
            'month' => '2026-06',
            'branch_code' => null,
            'branch_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<int, string> $columns */
    private function hasIndex(string $table, array $columns, bool $unique): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns && $index['unique'] === $unique) {
                return true;
            }
        }

        return false;
    }
}
