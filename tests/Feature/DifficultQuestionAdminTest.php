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
            'asker_name' => 'Tester',
            'asker_whatsapp' => '6281234567890',
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
        $response->assertSee('+6281234567890');
        $response->assertSee('Apa arti pemuridan?');
        $response->assertDontSee('name="answer_text"', false);
    }

    public function test_public_submission_stores_optional_whatsapp_number(): void
    {
        $this->createDifficultQuestionsTable();

        $this->get('/publik/pertanyaan-sulit/kirim')
            ->assertOk()
            ->assertSee('Nomor WhatsApp (opsional)')
            ->assertSee('name="asker_whatsapp"', false);

        $response = $this->post('/publik/pertanyaan-sulit/kirim', [
            'asker_name' => 'Tester',
            'asker_whatsapp' => '0812 3456 7890',
            'question_text' => 'Apakah nomor WhatsApp tersimpan?',
            'question_password' => 'secret-test',
            'question_password_confirm' => 'secret-test',
        ]);

        $response->assertRedirect('/publik/pertanyaan-sulit/kirim?submitted=1');
        $this->assertDatabaseHas('difficult_questions', [
            'asker_name' => 'Tester',
            'asker_whatsapp' => '6281234567890',
            'question' => 'Apakah nomor WhatsApp tersimpan?',
            'status' => 'pending',
        ]);
    }

    public function test_developer_cannot_save_answer_from_discipleship_preview(): void
    {
        $this->createDifficultQuestionsTable();
        $this->actingAsRecUser('developer', null, 'developer');

        $questionId = DB::table('difficult_questions')->insertGetId([
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

        $response = $this->post("/pemuridan/pertanyaan-sulit/{$questionId}/jawaban", [
            'answer_text' => 'Jawaban dari admin.',
        ]);

        $response->assertRedirect('/developer?error=access_denied');
        $this->assertDatabaseHas('difficult_questions', [
            'id' => $questionId,
            'status' => 'pending',
            'answer' => null,
            'answered_by_username' => null,
        ]);
    }

    public function test_public_answer_lookup_renders_matched_question(): void
    {
        $this->createDifficultQuestionsTable();

        session()->put('difficult_answer_lookup_hash', 'lookup-public');

        DB::table('difficult_questions')->insert([
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

        $this->post("/pemuridan/pertanyaan-sulit/{$questionId}/jawaban", [
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
            $table->string('asker_name')->nullable();
            $table->string('asker_whatsapp')->nullable();
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
