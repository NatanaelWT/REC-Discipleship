<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class NumericIdentifierMigrationTest extends TestCase
{
    public function test_public_identifiers_are_backfilled_then_removed(): void
    {
        $this->createLegacyTables();
        $this->seedResolvableRows();

        (require database_path('migrations/2026_06_20_000004_prepare_numeric_identifiers.php'))->up();
        (require database_path('migrations/2026_06_20_000005_drop_public_identifiers.php'))->up();

        foreach ([
            'difficult_questions',
            'discipleship_feedbacks',
            'discipleship_people',
            'discipleship_groups',
            'discipleship_relationships',
            'discipleship_group_people',
            'discipleship_meeting_reports',
            'msk_participants',
            'public_material_files',
        ] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'public_id'), $table.' still has public_id');
        }

        $this->assertFalse(Schema::hasColumn('discipleship_people', 'member_public_id'));
        $this->assertFalse(Schema::hasColumn('discipleship_group_people', 'person_public_id'));
        $this->assertFalse(Schema::hasColumn('discipleship_group_people', 'group_public_id'));
        $this->assertTrue(Schema::hasIndex('discipleship_people', ['branch_id']));
        $this->assertTrue(Schema::hasIndex('discipleship_groups', ['branch_id']));
        $this->assertTrue(Schema::hasIndex('discipleship_relationships', ['branch_id']));
        $this->assertTrue(Schema::hasIndex('msk_participants', ['branch_id']));

        $this->assertDatabaseHas('discipleship_relationships', [
            'id' => 1,
            'mentor_person_id' => 10,
            'disciple_person_id' => 11,
            'context_group_id' => 20,
        ]);
        $this->assertDatabaseHas('msk_participants', [
            'id' => 40,
            'discipleship_person_id' => 11,
        ]);
        $this->assertForeignKeyExists('discipleship_feedbacks', 'discipleship_group_id');
        $this->assertForeignKeyExists('discipleship_feedbacks', 'leader_person_id');
        $this->assertForeignKeyExists('discipleship_feedbacks', 'respondent_person_id');
        $this->assertForeignKeyExists('discipleship_meeting_reports', 'leader_person_id');
        $this->assertForeignKeyExists('discipleship_meeting_reports', 'discipleship_group_id');

        $absences = json_decode((string) DB::table('discipleship_meeting_reports')->where('id', 30)->value('absences'), true);
        $this->assertSame([[
            'person_id' => 11,
            'person_name_snapshot' => 'Member',
        ]], $absences);
    }

    public function test_unresolved_required_reference_stops_prepare_migration(): void
    {
        $this->createLegacyTables();
        $this->seedResolvableRows();
        DB::table('discipleship_relationships')->where('id', 1)->update([
            'disciple_person_id' => null,
            'disciple_person_public_id' => 'missing-person',
        ]);

        $this->expectException(RuntimeException::class);

        (require database_path('migrations/2026_06_20_000004_prepare_numeric_identifiers.php'))->up();
    }

    public function test_cross_branch_numeric_reference_stops_prepare_migration(): void
    {
        $this->createLegacyTables();
        $this->seedResolvableRows();
        DB::table('branches')->insert(['id' => 2, 'label' => 'Mulyosari']);
        DB::table('discipleship_groups')->insert([
            'id' => 21,
            'public_id' => 'group-other-branch',
            'branch_id' => 2,
        ]);
        DB::table('discipleship_feedbacks')->where('id', 60)->update([
            'discipleship_group_id' => 21,
        ]);

        $this->expectException(RuntimeException::class);

        (require database_path('migrations/2026_06_20_000004_prepare_numeric_identifiers.php'))->up();
    }

    public function test_active_person_resolves_duplicate_legacy_member_identifier(): void
    {
        $this->createLegacyTables();
        $this->seedResolvableRows();
        DB::table('discipleship_people')->where('id', 11)->update(['status' => 'inactive']);
        DB::table('discipleship_people')->insert([
            'id' => 12,
            'public_id' => 'person-member-active',
            'member_public_id' => 'member-member',
            'branch_id' => 1,
            'full_name' => 'Member',
            'phone' => '0822',
            'status' => 'active',
        ]);

        (require database_path('migrations/2026_06_20_000004_prepare_numeric_identifiers.php'))->up();

        $this->assertDatabaseHas('msk_participants', [
            'id' => 40,
            'discipleship_person_id' => 12,
        ]);
    }

    public function test_ambiguous_active_member_identifier_stops_prepare_migration(): void
    {
        $this->createLegacyTables();
        $this->seedResolvableRows();
        DB::table('discipleship_people')->insert([
            'id' => 12,
            'public_id' => 'person-member-duplicate',
            'member_public_id' => 'member-member',
            'branch_id' => 1,
            'full_name' => 'Member',
            'phone' => '0822',
            'status' => 'active',
        ]);

        $this->expectException(RuntimeException::class);

        (require database_path('migrations/2026_06_20_000004_prepare_numeric_identifiers.php'))->up();
    }

    public function test_virtual_injil_mentor_is_preserved_as_null_numeric_reference(): void
    {
        $this->createLegacyTables();
        $this->seedResolvableRows();
        DB::table('discipleship_relationships')->where('id', 1)->update([
            'mentor_person_id' => null,
            'mentor_person_public_id' => 'virtual_injil',
        ]);

        (require database_path('migrations/2026_06_20_000004_prepare_numeric_identifiers.php'))->up();

        $this->assertDatabaseHas('discipleship_relationships', [
            'id' => 1,
            'mentor_person_id' => null,
        ]);
    }

    private function createLegacyTables(): void
    {
        foreach ([
            'public_material_files',
            'msk_participants',
            'discipleship_meeting_reports',
            'discipleship_group_people',
            'discipleship_relationships',
            'discipleship_groups',
            'discipleship_people',
            'discipleship_feedbacks',
            'difficult_questions',
            'branches',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
        });
        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->string('member_public_id')->nullable();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->unique(['branch_id', 'public_id']);
            $table->foreign('branch_id')->references('id')->on('branches');
        });
        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->string('parent_group_public_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->string('source_group_public_id')->nullable();
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->string('initiated_by_person_public_id')->nullable();
            $table->unique(['branch_id', 'public_id']);
            $table->foreign('branch_id')->references('id')->on('branches');
        });
        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->string('mentor_person_public_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->string('disciple_person_public_id')->nullable();
            $table->unsignedBigInteger('context_group_id')->nullable();
            $table->string('context_group_public_id')->nullable();
            $table->unique(['branch_id', 'public_id']);
            $table->foreign('branch_id')->references('id')->on('branches');
        });
        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->string('group_public_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_public_id')->nullable();
        });
        Schema::create('discipleship_meeting_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->string('leader_person_public_id')->nullable();
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->string('discipleship_group_public_id')->nullable();
            $table->json('absences')->nullable();
            $table->json('meditation_sharers')->nullable();
        });
        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->unsignedBigInteger('branch_id');
            $table->string('member_public_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('whatsapp')->nullable();
            $table->unique(['branch_id', 'public_id']);
            $table->foreign('branch_id')->references('id')->on('branches');
        });
        Schema::create('difficult_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
        });
        Schema::create('discipleship_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->unsignedBigInteger('respondent_person_id')->nullable();
        });
        Schema::create('public_material_files', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();
        });
    }

    private function seedResolvableRows(): void
    {
        DB::table('branches')->insert(['id' => 1, 'label' => 'Kutisari']);
        DB::table('discipleship_people')->insert([
            [
                'id' => 10,
                'public_id' => 'person-leader',
                'member_public_id' => 'member-leader',
                'branch_id' => 1,
                'full_name' => 'Leader',
                'phone' => '0811',
                'status' => 'active',
            ],
            [
                'id' => 11,
                'public_id' => 'person-member',
                'member_public_id' => 'member-member',
                'branch_id' => 1,
                'full_name' => 'Member',
                'phone' => '0822',
                'status' => 'active',
            ],
        ]);
        DB::table('discipleship_groups')->insert([
            'id' => 20,
            'public_id' => 'group-main',
            'branch_id' => 1,
            'initiated_by_person_id' => null,
            'initiated_by_person_public_id' => 'person-leader',
        ]);
        DB::table('discipleship_relationships')->insert([
            'id' => 1,
            'public_id' => 'relation-1',
            'branch_id' => 1,
            'mentor_person_id' => null,
            'mentor_person_public_id' => 'person-leader',
            'disciple_person_id' => null,
            'disciple_person_public_id' => 'person-member',
            'context_group_id' => null,
            'context_group_public_id' => 'group-main',
        ]);
        DB::table('discipleship_group_people')->insert([
            'id' => 1,
            'public_id' => 'membership-1',
            'branch_id' => 1,
            'discipleship_group_id' => 20,
            'group_public_id' => 'group-main',
            'person_id' => 11,
            'person_public_id' => 'person-member',
        ]);
        DB::table('discipleship_meeting_reports')->insert([
            'id' => 30,
            'public_id' => 'report-1',
            'branch_id' => 1,
            'leader_person_id' => null,
            'leader_person_public_id' => 'person-leader',
            'discipleship_group_id' => null,
            'discipleship_group_public_id' => 'group-main',
            'absences' => json_encode([[
                'person_id' => null,
                'person_public_id' => 'person-member',
                'person_name_snapshot' => 'Member',
            ]]),
            'meditation_sharers' => json_encode([]),
        ]);
        DB::table('msk_participants')->insert([
            'id' => 40,
            'public_id' => 'msk-1',
            'branch_id' => 1,
            'member_public_id' => 'member-member',
            'full_name' => 'Member',
            'whatsapp' => '0822',
        ]);
        DB::table('difficult_questions')->insert(['id' => 50, 'public_id' => 'question-1']);
        DB::table('discipleship_feedbacks')->insert([
            'id' => 60,
            'public_id' => 'feedback-1',
            'branch_id' => 1,
            'discipleship_group_id' => 20,
            'leader_person_id' => 10,
            'respondent_person_id' => 11,
        ]);
        DB::table('public_material_files')->insert(['id' => 70, 'public_id' => 'material-1']);
    }

    private function assertForeignKeyExists(string $table, string $column): void
    {
        $hasForeignKey = collect(Schema::getForeignKeys($table))
            ->contains(static fn (array $foreignKey): bool => ($foreignKey['columns'] ?? []) === [$column]);

        $this->assertTrue($hasForeignKey, "{$table}.{$column} has no foreign key");
    }
}
