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

        DB::table('pertanyaan_sulit')->insert([
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
        $response->assertSee('name="answer_text"', false);
    }

    public function test_public_submission_stores_optional_whatsapp_number(): void
    {
        $this->createDifficultQuestionsTable();

        $this->get('/publik/pertanyaan-sulit/kirim')
            ->assertOk()
            ->assertSee('Nomor WhatsApp (opsional)')
            ->assertSee('name="asker_whatsapp"', false)
            ->assertDontSee('Lihat Jawaban');

        $response = $this->post('/publik/pertanyaan-sulit/kirim', [
            'asker_name' => 'Tester',
            'asker_whatsapp' => '0812 3456 7890',
            'question_text' => 'Apakah nomor WhatsApp tersimpan?',
            'question_password' => 'secret-test',
            'question_password_confirm' => 'secret-test',
        ]);

        $response->assertRedirect('/publik/pertanyaan-sulit/kirim?submitted=1');
        $this->assertDatabaseHas('pertanyaan_sulit', [
            'asker_name' => 'Tester',
            'asker_whatsapp' => '6281234567890',
            'question' => 'Apakah nomor WhatsApp tersimpan?',
            'status' => 'pending',
        ]);
    }

    public function test_admin_page_filters_by_month_and_search(): void
    {
        $this->createDifficultQuestionsTable();
        $this->loginAsCentralDiscipleshipAdmin();

        DB::table('pertanyaan_sulit')->insert([
            [
                'asker_name' => 'Anna',
                'asker_whatsapp' => '6281111111111',
                'question' => 'Bagaimana menjelaskan pemuridan?',
                'password_hash' => null,
                'password_lookup_hash' => 'lookup-anna',
                'status' => 'pending',
                'answer' => null,
                'answered_by_username' => null,
                'answered_at' => null,
                'created_at' => '2026-06-13 08:00:00',
                'updated_at' => '2026-06-13 08:00:00',
            ],
            [
                'asker_name' => 'Budi',
                'asker_whatsapp' => '6282222222222',
                'question' => 'Pertanyaan bulan lain',
                'password_hash' => null,
                'password_lookup_hash' => 'lookup-budi',
                'status' => 'pending',
                'answer' => null,
                'answered_by_username' => null,
                'answered_at' => null,
                'created_at' => '2026-07-13 08:00:00',
                'updated_at' => '2026-07-13 08:00:00',
            ],
            [
                'asker_name' => 'Clara',
                'asker_whatsapp' => null,
                'question' => 'Pertanyaan Juni lain',
                'password_hash' => null,
                'password_lookup_hash' => 'lookup-clara',
                'status' => 'answered',
                'answer' => 'Sudah dijawab',
                'answered_by_username' => 'admin_pusat',
                'answered_at' => '2026-06-14 08:00:00',
                'created_at' => '2026-06-14 08:00:00',
                'updated_at' => '2026-06-14 08:00:00',
            ],
        ]);

        $response = $this->get('/pemuridan/pertanyaan-sulit?month=2026-06&q=Anna');

        $response->assertOk();
        $response->assertSee('value="2026-06"', false);
        $response->assertSee('value="Anna"', false);
        $response->assertSee('Anna');
        $response->assertSee('Bagaimana menjelaskan pemuridan?');
        $response->assertDontSee('Budi');
        $response->assertDontSee('Clara');
        $response->assertDontSee('discipleship-page-header__stats', false);
        $response->assertDontSee('Dengan WA');
    }

    public function test_developer_can_save_answer_from_difficult_question_admin(): void
    {
        $this->createDifficultQuestionsTable();
        $this->actingAsRecUser('developer', null, 'developer');

        $questionId = DB::table('pertanyaan_sulit')->insertGetId([
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

        $this->get('/pemuridan/pertanyaan-sulit')
            ->assertOk()
            ->assertSee('name="answer_text"', false);

        $response = $this->post("/pemuridan/pertanyaan-sulit/{$questionId}/jawaban", [
            'answer_text' => 'Jawaban dari admin.',
        ]);

        $response->assertRedirect('/pemuridan/pertanyaan-sulit?answered=1');
        $this->assertDatabaseHas('pertanyaan_sulit', [
            'id' => $questionId,
            'status' => 'answered',
            'answer' => 'Jawaban dari admin.',
            'answered_by_username' => 'developer',
        ]);
    }

    public function test_public_answer_lookup_renders_matched_question(): void
    {
        $this->createDifficultQuestionsTable();

        $this->get('/publik/pertanyaan-sulit/jawaban')
            ->assertOk()
            ->assertSee('Buka Jawaban')
            ->assertDontSee('Kirim Pertanyaan Baru');

        session()->put('difficult_answer_lookup_hash', 'lookup-public');

        DB::table('pertanyaan_sulit')->insert([
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

    public function test_central_discipleship_user_can_view_and_save_an_answer(): void
    {
        $this->createDifficultQuestionsTable();
        $this->loginAsCentralDiscipleshipAdmin();

        $questionId = DB::table('pertanyaan_sulit')->insertGetId([
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

        $this->get('/pemuridan/pertanyaan-sulit')
            ->assertOk()
            ->assertSee('Pertanyaan read only')
            ->assertSee('name="answer_text"', false);

        $this->post("/pemuridan/pertanyaan-sulit/{$questionId}/jawaban", [
            'answer_text' => 'Jawaban dari pusat.',
        ])->assertRedirect('/pemuridan/pertanyaan-sulit?answered=1');

        $this->assertDatabaseHas('pertanyaan_sulit', [
            'id' => $questionId,
            'status' => 'answered',
            'answer' => 'Jawaban dari pusat.',
            'answered_by_username' => 'admin_pusat',
        ]);
    }

    public function test_branch_user_sees_difficult_question_tab_but_cannot_answer(): void
    {
        $this->createDifficultQuestionsTable();
        $this->actingAsRecUser('admin_kutisari', 'kutisari', 'pemuridan_cabang');

        $questionId = DB::table('pertanyaan_sulit')->insertGetId([
            'asker_name' => 'Tester Cabang',
            'question' => 'Pertanyaan untuk dilihat cabang',
            'password_hash' => null,
            'password_lookup_hash' => 'lookup-branch-view',
            'status' => 'pending',
            'answer' => null,
            'answered_by_username' => null,
            'answered_at' => null,
            'created_at' => '2026-06-13 08:00:00',
            'updated_at' => '2026-06-13 08:00:00',
        ]);

        $this->get('/pemuridan/pertanyaan-sulit')
            ->assertOk()
            ->assertSee('id="discipleship-tab-questions"', false)
            ->assertSee('data-tab-key="questions"', false)
            ->assertSee('Pertanyaan untuk dilihat cabang')
            ->assertDontSee('name="answer_text"', false);

        $this->post("/pemuridan/pertanyaan-sulit/{$questionId}/jawaban", [
            'answer_text' => 'Jawaban yang tidak boleh tersimpan.',
        ])->assertRedirect('/pemuridan/dashboard?error=access_denied');

        $this->assertDatabaseHas('pertanyaan_sulit', [
            'id' => $questionId,
            'status' => 'pending',
            'answer' => null,
            'answered_by_username' => null,
        ]);
    }

    private function createDifficultQuestionsTable(): void
    {
        Schema::dropIfExists('pertanyaan_sulit');

        Schema::create('pertanyaan_sulit', function (Blueprint $table): void {
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
