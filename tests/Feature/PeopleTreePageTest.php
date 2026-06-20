<?php

namespace Tests\Feature;

use App\Services\DiscipleshipPeopleTree\PeopleTreeModelStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PeopleTreePageTest extends TestCase
{
    public function test_legacy_people_tree_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/pohon?page=people_tree');

        $response->assertNotFound();
    }

    public function test_people_tree_v2_route_redirects_to_main_tree_route(): void
    {
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/pohon-v2?rekap_cabang=kutisari');

        $response->assertRedirect('/pemuridan/pohon?rekap_cabang=kutisari');
    }

    public function test_people_tree_page_renders_from_laravel_tables(): void
    {
        $this->createTables();
        $this->seedPeopleTree();

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/pohon');

        $response->assertStatus(200);
        $response->assertSee('Pohon Pemuridan');
        $response->assertSee('Leader Test');
        $response->assertSee('Anggota Test');
        $response->assertDontSee('?page=people_tree', false);
    }

    public function test_central_all_branch_tree_renders_people_from_every_branch(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        $this->actingAsRecUser('central_reader', null, 'pemuridan_pusat');

        $allBranches = $this->get('/pemuridan/pohon?branch_id=all');

        $allBranches->assertOk()
            ->assertSee('Semua Cabang')
            ->assertSee('Kutisari')
            ->assertSee('Leader Test')
            ->assertSee('Anggota Test');

        $this->get('/pemuridan/pohon?branch_id=1')
            ->assertOk()
            ->assertSee('Leader Test')
            ->assertSee('Anggota Test');
    }

    public function test_people_tree_write_invalidates_cached_read_model(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        $this->actingAsRecUser();

        $this->get('/pemuridan/pohon')
            ->assertOk()
            ->assertDontSee('Peserta Cache Baru');

        $this->post('/pemuridan/pohon/orang', [
            'leader_id' => 'virtual_injil',
            'group_id' => '',
            'full_name' => 'Peserta Cache Baru',
            'return_page' => 'people_tree',
        ])->assertRedirect('/pemuridan/pohon?saved=1');

        $this->get('/pemuridan/pohon')
            ->assertOk()
            ->assertSee('Peserta Cache Baru');
    }

    public function test_people_tree_store_maps_temporary_ids_to_numeric_foreign_keys(): void
    {
        $this->createTables();

        app(PeopleTreeModelStore::class)->replaceBranchModel('kutisari', [
            'discipleship_persons' => [
                ['id' => 'new_person_leader', 'full_name' => 'Leader Baru', 'status' => 'active'],
                ['id' => 'new_person_member', 'full_name' => 'Anggota Baru', 'status' => 'active'],
            ],
            'discipleship_groups' => [
                [
                    'id' => 'new_group_main',
                    'name' => 'Kelompok Baru',
                    'status' => 'active',
                    'start_stage' => 'DG 1',
                    'current_stage' => 'DG 1',
                ],
            ],
            'discipleship_relations' => [
                [
                    'id' => 'new_relation_main',
                    'mentor_person_id' => 'new_person_leader',
                    'disciple_person_id' => 'new_person_member',
                    'context_group_id' => 'new_group_main',
                    'status' => 'active',
                ],
            ],
            'group_memberships' => [
                [
                    'id' => 'new_membership_main',
                    'group_id' => 'new_group_main',
                    'person_id' => 'new_person_member',
                    'role' => 'member',
                    'status' => 'active',
                ],
            ],
            'group_leaderships' => [
                [
                    'id' => 'new_leadership_main',
                    'group_id' => 'new_group_main',
                    'leader_person_id' => 'new_person_leader',
                    'role' => 'leader',
                    'status' => 'active',
                ],
            ],
            'group_multiplications' => [],
        ]);

        $leaderId = (int) DB::table('discipleship_people')->where('full_name', 'Leader Baru')->value('id');
        $memberId = (int) DB::table('discipleship_people')->where('full_name', 'Anggota Baru')->value('id');
        $groupId = (int) DB::table('discipleship_groups')->where('name', 'Kelompok Baru')->value('id');

        $this->assertGreaterThan(0, $leaderId);
        $this->assertGreaterThan(0, $memberId);
        $this->assertGreaterThan(0, $groupId);
        $this->assertDatabaseHas('discipleship_relationships', [
            'mentor_person_id' => $leaderId,
            'disciple_person_id' => $memberId,
            'context_group_id' => $groupId,
        ]);
        $this->assertDatabaseHas('discipleship_group_people', [
            'discipleship_group_id' => $groupId,
            'person_id' => $leaderId,
            'role' => 'leader',
        ]);
        $this->assertDatabaseHas('discipleship_group_people', [
            'discipleship_group_id' => $groupId,
            'person_id' => $memberId,
            'role' => 'member',
        ]);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('msk_participants');
        Schema::dropIfExists('discipleship_group_people');
        Schema::dropIfExists('discipleship_relationships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->string('campus')->nullable();
            $table->string('major')->nullable();
            $table->string('occupation')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('start_stage')->nullable();
            $table->string('current_stage')->nullable();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->date('multiplied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->unsignedBigInteger('context_group_id')->nullable();
            $table->string('relation_type')->nullable();
            $table->string('stage_at_start')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('role')->default('member');
            $table->string('stage')->nullable();
            $table->string('status')->default('active');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('msk_participants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_person_id')->nullable()->unique();
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
        });
    }

    private function seedPeopleTree(): void
    {
        $leaderId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Leader Test',
            'phone' => '0811111111',
            'gender' => 'Laki-laki',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('discipleship_people')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Test',
            'phone' => '0822222222',
            'gender' => 'Perempuan',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('discipleship_groups')->insertGetId([
            'branch_id' => 1,
            'name' => 'Kelompok Test',
            'status' => 'active',
            'start_stage' => 'DG 1',
            'current_stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_relationships')->insert([
            'branch_id' => 1,
            'mentor_person_id' => $leaderId,
            'disciple_person_id' => $memberId,
            'context_group_id' => $groupId,
            'relation_type' => 'discipleship',
            'stage_at_start' => 'DG 1',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_group_people')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $leaderId,
            'role' => 'leader',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discipleship_group_people')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $memberId,
            'role' => 'member',
            'stage' => 'DG 1',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
