<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscipleshipPeopleListTest extends TestCase
{
    public function test_legacy_people_list_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/anggota?page=people_list');

        $response->assertNotFound();
    }

    public function test_people_list_page_renders_for_logged_in_branch_user(): void
    {
        $this->createDiscipleshipTables();
        DB::table('discipleship_people')->insert([
            [
                'public_id' => 'person-kutisari',
                'branch_code' => 'kutisari',
                'full_name' => 'Anggota Kutisari',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'public_id' => 'person-gm',
                'branch_code' => 'gm',
                'full_name' => 'Anggota GM Rahasia',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/anggota');

        $response->assertStatus(200);
        $response->assertSee('Daftar Anggota DG');
        $response->assertSee('Anggota Kutisari');
        $response->assertDontSee('Anggota GM Rahasia');
    }

    private function createDiscipleshipTables(): void
    {
        Schema::dropIfExists('discipleship_group_leaderships');
        Schema::dropIfExists('discipleship_group_memberships');
        Schema::dropIfExists('discipleship_relationships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->string('branch_code', 40);
            $table->string('member_public_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
        });

        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->string('branch_code', 40);
            $table->string('name')->nullable();
            $table->string('status', 40)->default('active');
            $table->string('start_stage', 40)->nullable();
            $table->string('current_stage', 40)->nullable();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->string('parent_group_public_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_relationships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('mentor_person_id')->nullable();
            $table->string('mentor_person_public_id')->nullable();
            $table->unsignedBigInteger('disciple_person_id')->nullable();
            $table->string('disciple_person_public_id')->nullable();
            $table->unsignedBigInteger('context_group_id')->nullable();
            $table->string('context_group_public_id')->nullable();
            $table->string('relation_type')->nullable();
            $table->string('stage_at_start')->nullable();
            $table->string('status', 40)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->string('group_public_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_public_id')->nullable();
            $table->string('role')->nullable();
            $table->string('stage')->nullable();
            $table->string('status', 40)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_leaderships', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->string('branch_code', 40);
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->string('group_public_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_public_id')->nullable();
            $table->string('role')->nullable();
            $table->string('status', 40)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_change')->nullable();
            $table->timestamps();
        });
    }
}
