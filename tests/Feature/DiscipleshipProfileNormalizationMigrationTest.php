<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DiscipleshipProfileNormalizationMigrationTest extends TestCase
{
    public function test_profiles_are_moved_to_msk_participants_and_person_profile_columns_are_dropped(): void
    {
        $this->createTables();

        DB::table('discipleship_people')->insert([
            [
                'id' => 1,
                'branch_id' => 1,
                'full_name' => 'Person Without MSK',
                'phone' => '0811111111',
                'gender' => 'Perempuan',
                'status' => 'active',
                'notes' => 'Catatan pemuridan',
                'created_at' => '2026-07-01 08:00:00',
                'updated_at' => '2026-07-01 09:00:00',
            ],
            [
                'id' => 2,
                'branch_id' => 1,
                'full_name' => 'Legacy Person Name',
                'phone' => '0822222222',
                'gender' => 'Laki-laki',
                'status' => 'active',
                'notes' => null,
                'created_at' => '2026-07-01 08:00:00',
                'updated_at' => '2026-07-01 09:00:00',
            ],
            [
                'id' => 3,
                'branch_id' => 1,
                'full_name' => 'Unlinked MSK',
                'phone' => '833333333',
                'gender' => 'Perempuan',
                'status' => 'active',
                'notes' => null,
                'created_at' => '2026-07-01 08:00:00',
                'updated_at' => '2026-07-01 09:00:00',
            ],
        ]);

        DB::table('msk_participants')->insert([
            [
                'id' => 20,
                'branch_id' => 1,
                'discipleship_person_id' => 2,
                'full_name' => 'Canonical MSK Name',
                'gender' => null,
                'whatsapp' => null,
                'batch_month' => '2026-06',
                'notes' => 'Catatan MSK',
                'completed_at' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([1, 2]),
                'photos' => json_encode([]),
                'created_at' => '2026-07-01 10:00:00',
                'updated_at' => '2026-07-01 10:00:00',
            ],
            [
                'id' => 21,
                'branch_id' => 1,
                'discipleship_person_id' => null,
                'full_name' => 'Unlinked MSK',
                'gender' => 'Perempuan',
                'whatsapp' => '0833333333',
                'batch_month' => '2026-06',
                'notes' => null,
                'completed_at' => null,
                'journey_bridge_status' => 'belum',
                'status' => 'active',
                'session_numbers' => json_encode([1]),
                'photos' => json_encode([]),
                'created_at' => '2026-07-01 10:00:00',
                'updated_at' => '2026-07-01 10:00:00',
            ],
        ]);

        $migration = require database_path('migrations/2026_07_02_000003_normalize_discipleship_profiles_to_msk_participants.php');
        $migration->up();

        foreach (['full_name', 'phone', 'gender'] as $column) {
            $this->assertFalse(Schema::hasColumn('discipleship_people', $column));
        }

        $this->assertDatabaseHas('discipleship_people', [
            'id' => 1,
            'branch_id' => 1,
            'status' => 'active',
            'notes' => 'Catatan pemuridan',
        ]);
        $this->assertDatabaseHas('msk_participants', [
            'discipleship_person_id' => 1,
            'full_name' => 'Person Without MSK',
            'gender' => 'Perempuan',
            'whatsapp' => '0811111111',
            'journey_bridge_status' => 'belum',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('msk_participants', [
            'id' => 20,
            'discipleship_person_id' => 2,
            'full_name' => 'Canonical MSK Name',
            'gender' => 'Laki-laki',
            'whatsapp' => '0822222222',
            'batch_month' => '2026-06',
            'notes' => 'Catatan MSK',
        ]);
        $this->assertDatabaseHas('msk_participants', [
            'id' => 21,
            'discipleship_person_id' => 3,
            'full_name' => 'Unlinked MSK',
        ]);
        $this->assertSame(3, DB::table('msk_participants')->count());
        $this->assertTrue(Schema::hasIndex('msk_participants', ['discipleship_person_id'], 'unique'));
    }

    public function test_duplicate_msk_links_stop_the_migration(): void
    {
        $this->createTables(withUniqueLink: false);

        DB::table('discipleship_people')->insert([
            'id' => 1,
            'branch_id' => 1,
            'full_name' => 'Duplicate Link',
            'phone' => null,
            'gender' => null,
            'status' => 'active',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('msk_participants')->insert([
            [
                'branch_id' => 1,
                'discipleship_person_id' => 1,
                'full_name' => 'Duplicate Link A',
                'status' => 'active',
                'journey_bridge_status' => 'belum',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_person_id' => 1,
                'full_name' => 'Duplicate Link B',
                'status' => 'active',
                'journey_bridge_status' => 'belum',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->expectException(RuntimeException::class);

        $migration = require database_path('migrations/2026_07_02_000003_normalize_discipleship_profiles_to_msk_participants.php');
        $migration->up();
    }

    private function createTables(bool $withUniqueLink = true): void
    {
        Schema::dropIfExists('msk_participants');
        Schema::dropIfExists('discipleship_people');

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('msk_participants', function (Blueprint $table) use ($withUniqueLink): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_person_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_day_month')->nullable();
            $table->string('birth_place')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('batch_month')->nullable();
            $table->text('notes')->nullable();
            $table->string('completed_at')->nullable();
            $table->string('journey_bridge_status')->default('belum');
            $table->string('status')->default('active');
            $table->json('session_numbers')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();

            if ($withUniqueLink) {
                $table->unique('discipleship_person_id', 'msk_participants_person_unique');
            }
        });
    }
}
