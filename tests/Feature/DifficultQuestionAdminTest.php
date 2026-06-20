<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DifficultQuestionAdminTest extends TestCase
{
    public function test_legacy_difficult_question_query_is_rejected(): void
    {
        $response = $this->get('/pemuridan/pertanyaan-sulit?page=discipleship_targets');

        $response->assertNotFound();
    }

    public function test_admin_page_renders_for_central_discipleship_user(): void
    {
        $this->createDifficultQuestionsTable();
        $this->loginAsCentralDiscipleshipAdmin();

        DB::table('difficult_questions')->insert([
            'public_id' => 'dq_test_1',
            'asker_name' => 'Tester',
            'question' => 'Apa arti pemuridan?',
            'password_hash' => null,
            'password_lookup_hash' => 'lookup-test',
            'status' => 'pending',
            'answer' => null,
            'answered_by_username' => null,
            'answered_at' => null,
            'created_at' => '2026-06-13 08:00:00',
            'updated_at' => '2026-06-13 08:00:00',
        ]);

        $response = $this->get('/pemuridan/pertanyaan-sulit');

        $response->assertStatus(200);
        $response->assertSee('Pertanyaan Sulit');
        $response->assertSee('Apa arti pemuridan?');
        $response->assertDontSee('name="answer_text"', false);
    }

    public function test_admin_can_save_answer_through_laravel_route(): void
    {
        $this->createDifficultQuestionsTable();
        $this->actingAsRecUser('developer', null, 'developer');

        DB::table('difficult_questions')->insert([
            'public_id' => 'dq_test_2',
            'asker_name' => 'Tester',
            'question' => 'Pertanyaan uji',
            'password_hash' => null,
            'password_lookup_hash' => 'lookup-test',
            'status' => 'pending',
            'answer' => null,
            'answered_by_username' => null,
            'answered_at' => null,
            'created_at' => '2026-06-13 08:00:00',
            'updated_at' => '2026-06-13 08:00:00',
        ]);

        $response = $this->post('/pemuridan/pertanyaan-sulit/dq_test_2/jawaban', [
            'answer_text' => 'Jawaban dari admin.',
        ]);

        $response->assertRedirect('/pemuridan/pertanyaan-sulit?answered=1');
        $this->assertDatabaseHas('difficult_questions', [
            'public_id' => 'dq_test_2',
            'status' => 'answered',
            'answer' => 'Jawaban dari admin.',
            'answered_by_username' => 'developer',
        ]);
    }

    public function test_public_answer_lookup_renders_matched_question(): void
    {
        $this->createDifficultQuestionsTable();

        session()->put('difficult_answer_lookup_hash', 'lookup-public');

        DB::table('difficult_questions')->insert([
            'public_id' => 'dq_public_1',
            'asker_name' => 'Penanya',
            'question' => 'Apakah jawaban publik tampil?',
            'password_hash' => null,
            'password_lookup_hash' => 'lookup-public',
            'status' => 'answered',
            'answer' => 'Jawaban publik tersedia.',
            'answered_by_username' => 'admin_pusat',
            'answered_at' => '2026-06-13 09:00:00',
            'created_at' => '2026-06-13 08:00:00',
            'updated_at' => '2026-06-13 09:00:00',
        ]);

        $response = $this->get('/publik/pertanyaan-sulit/jawaban?looked=1');

        $response->assertStatus(200);
        $response->assertSee('Hasil Pencarian');
        $response->assertSee('Apakah jawaban publik tampil?');
        $response->assertSee('Jawaban publik tersedia.');
    }

    public function test_central_discipleship_user_cannot_save_an_answer(): void
    {
        $this->createDifficultQuestionsTable();
        $this->loginAsCentralDiscipleshipAdmin();

        $questionId = DB::table('difficult_questions')->insertGetId([
            'public_id' => 'dq_readonly',
            'asker_name' => 'Tester',
            'question' => 'Pertanyaan read only',
            'password_hash' => null,
            'password_lookup_hash' => 'lookup-readonly',
            'status' => 'pending',
            'answer' => null,
            'answered_by_username' => null,
            'answered_at' => null,
            'created_at' => '2026-06-13 08:00:00',
            'updated_at' => '2026-06-13 08:00:00',
        ]);

        $this->post('/pemuridan/pertanyaan-sulit/dq_readonly/jawaban', [
            'answer_text' => 'Tidak boleh disimpan.',
        ])->assertRedirect('/pemuridan/dashboard?error=access_denied');

        $this->assertDatabaseHas('difficult_questions', [
            'id' => $questionId,
            'status' => 'pending',
            'answer' => null,
        ]);
    }

    private function createDifficultQuestionsTable(): void
    {
        Schema::dropIfExists('difficult_questions');

        Schema::create('difficult_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 120)->unique();
            $table->string('asker_name')->nullable();
            $table->longText('question');
            $table->string('password_hash')->nullable();
            $table->string('password_lookup_hash', 128)->index();
            $table->string('status', 80)->default('pending')->index();
            $table->longText('answer')->nullable();
            $table->string('answered_by_username', 120)->nullable();
            $table->timestamp('answered_at')->nullable()->index();
            $table->timestamps();
        });
    }

    private function loginAsCentralDiscipleshipAdmin(): void
    {
        $this->actingAsRecUser('admin_pusat', null, 'pemuridan_pusat');
    }
}
