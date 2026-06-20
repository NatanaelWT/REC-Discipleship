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
                'branch_id' => 1,
                'full_name' => 'Anggota Kutisari',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 2,
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
        Schema::dropIfExists('discipleship_group_people');
        Schema::dropIfExists('discipleship_relationships');
        Schema::dropIfExists('discipleship_groups');
        Schema::dropIfExists('discipleship_people');

        Schema::create('discipleship_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status', 40)->default('active');
            $table->timestamps();
        });

        Schema::create('discipleship_groups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name')->nullable();
            $table->string('status', 40)->default('active');
            $table->string('start_stage', 40)->nullable();
            $table->string('current_stage', 40)->nullable();
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
            $table->string('status', 40)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('reason_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discipleship_group_people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('role')->nullable();
            $table->string('stage')->nullable();
            $table->string('status', 40)->default('active');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->timestamps();
        });
    }
}
