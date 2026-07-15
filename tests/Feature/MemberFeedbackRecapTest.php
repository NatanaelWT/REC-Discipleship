<?php

namespace Tests\Feature;

use App\Services\Branches\BranchCatalog;
use App\Services\MemberFeedbackJournals\MemberFeedbackQuestionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MemberFeedbackRecapTest extends TestCase
{
    public function test_member_feedback_recap_renders_for_branch_user(): void
    {
        $this->createTables();
        $ids = $this->seedFeedbackFixture();
        $this->seedFeedback($ids, respondentName: 'Anggota Test', noteContent: 'Catatan privat untuk pemimpin.');

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/umpan-balik-anggota');

        $response->assertOk();
        $response->assertSee('Jurnal Umpan Balik');
        $response->assertSee('Pemimpin Test');
        $response->assertSee('Anggota Test');
        $response->assertSee('card discipleship-page-header', false);
        $response->assertDontSee('discipleship-page-header__stats', false);
        $response->assertDontSee('discipleship-page-header__stat', false);
        $response->assertDontSee('member-feedback-recap-score-card', false);
        $response->assertDontSee('member-feedback-recap-panel-grid', false);
        $response->assertDontSee('Pengisian per Pertemuan');
        $response->assertDontSee('Skor Pertanyaan Terendah');
        $response->assertDontSee('data-filter-role="member-feedback-session"', false);
        $response->assertDontSee('Semua Pertemuan');
        $response->assertDontSee('Update Terakhir');
        $response->assertSee('data-member-feedback-progress="dg1"', false);
        $response->assertSee('data-member-feedback-group-open', false);
        $response->assertSee('data-member-feedback-group-modal', false);
        $response->assertSee('data-member-feedback-detail-open', false);
        $response->assertSee('data-member-feedback-detail-modal', false);
        $response->assertSee('discipleship-list-panel member-feedback-recap-panel', false);
        $response->assertSee('card table-card-plain dg-recap-section-card member-feedback-recap-group-card', false);
        $response->assertDontSee('Daftar Jurnal Umpan Balik Anggota');
    }

    public function test_fragment_and_full_page_use_the_same_feedback_summary_table(): void
    {
        $this->createTables();
        $ids = $this->seedFeedbackFixture();
        $this->seedFeedback($ids, respondentName: 'Anggota Fragment');

        $this->actingAsRecUser();

        $fullPage = $this->get('/pemuridan/umpan-balik-anggota')->assertOk();
        $fragment = $this->withHeader('X-Discipleship-Fragment', 'tab')
            ->get('/pemuridan/umpan-balik-anggota')
            ->assertOk();

        $fullPage->assertSee('data-discipleship-workspace', false);
        $fragment->assertDontSee('data-discipleship-workspace', false);
        $fragment->assertSee('id="discipleship-tabpanel-feedback"', false);
        $fragment->assertSee('id="member-feedback-recap-group-table"', false);

        $this->assertSame(
            $this->elementHtmlById($fullPage->getContent(), 'member-feedback-recap-group-table'),
            $this->elementHtmlById($fragment->getContent(), 'member-feedback-recap-group-table'),
        );
    }

    public function test_feedback_detail_modal_template_contains_full_feedback_contents(): void
    {
        $this->createTables();
        $ids = $this->seedFeedbackFixture();
        $this->seedFeedback($ids, respondentName: 'Anggota Detail', noteContent: 'Catatan lengkap di modal feedback.');

        $this->actingAsRecUser();

        $this->get('/pemuridan/umpan-balik-anggota')
            ->assertOk()
            ->assertSee('data-member-feedback-group-template', false)
            ->assertSee('data-member-feedback-detail-template', false)
            ->assertSee('Feedback Anggota Detail')
            ->assertSee('Skor Pertanyaan')
            ->assertSee('Apakah pemimpin DG dapat menjadi fasilitator yang baik?')
            ->assertSee('10 / 10')
            ->assertSee('3 / 5')
            ->assertSee('Catatan Tertulis')
            ->assertSee('Catatan lengkap di modal feedback.');
    }

    public function test_group_section_lists_all_active_groups_with_unique_session_counts(): void
    {
        $this->createTables();
        $withFeedback = $this->seedFeedbackFixture(groupName: 'Kelompok Terisi');
        $this->seedFeedbackFixture(
            leaderName: 'Pemimpin Tanpa Feedback',
            memberName: 'Anggota Tanpa Feedback',
            groupName: 'Kelompok Tanpa Feedback',
        );
        $this->seedFeedback($withFeedback, respondentName: 'Anggota Satu', feedbackSession: 3);
        $this->seedFeedback($withFeedback, respondentName: 'Anggota Satu Duplikat', feedbackSession: 3);
        $this->seedFeedback($withFeedback, respondentName: 'Anggota Satu', feedbackSession: 12);

        $this->actingAsRecUser();

        $content = $this->get('/pemuridan/umpan-balik-anggota')->assertOk()->getContent();

        $this->assertStringContainsString('Pengisi Feedback per Kelompok', $content);
        $this->assertStringNotContainsString('discipleship-page-header__stats', $content);
        $this->assertStringContainsString('DG 1 (Pemimpin Test)', $content);
        $this->assertStringContainsString('DG 1 (Pemimpin Tanpa Feedback)', $content);
        $this->assertMatchesRegularExpression('/DG 1 \(Pemimpin Test\).*?1 orang.*?1 orang.*?1 orang/s', $content);
        $this->assertMatchesRegularExpression('/DG 1 \(Pemimpin Tanpa Feedback\).*?1 orang.*?0 orang.*?0 orang/s', $content);
        $this->assertStringNotContainsString('2 orang', $content);
    }

    public function test_central_user_can_view_all_branches_and_filter_to_one_branch(): void
    {
        $this->createTables();
        $kutisari = $this->seedFeedbackFixture(branchId: 1, leaderName: 'Pemimpin Kutisari', memberName: 'Anggota Kutisari');
        $gm = $this->seedFeedbackFixture(branchId: 2, leaderName: 'Pemimpin GM', memberName: 'Anggota GM');
        $this->seedFeedback($kutisari, respondentName: 'Anggota Kutisari');
        $this->seedFeedback($gm, respondentName: 'Anggota GM');

        $this->actingAsRecUser('recpusat', null, 'pemuridan_pusat');

        $this->get('/pemuridan/umpan-balik-anggota?rekap_cabang=all')
            ->assertOk()
            ->assertSee('data-discipleship-branch-filter', false)
            ->assertSee('<option value="all" selected>Semua Cabang</option>', false)
            ->assertDontSee('central-rekap-toolbar', false)
            ->assertSee('Pemimpin Kutisari')
            ->assertSee('Pemimpin GM');

        $this->get('/pemuridan/umpan-balik-anggota?rekap_cabang=gm')
            ->assertOk()
            ->assertDontSee('Pemimpin Kutisari')
            ->assertSee('Pemimpin GM');
    }

    public function test_thematic_note_board_is_removed_but_detail_keeps_feedback_notes(): void
    {
        $this->createTables();
        $ids = $this->seedFeedbackFixture();
        $this->seedFeedback($ids, respondentName: 'Nama Pengisi Rahasia', noteContent: 'Masukan lengkap di modal detail.');

        $this->actingAsRecUser();

        $content = $this->get('/pemuridan/umpan-balik-anggota')->assertOk()->getContent();
        $this->assertStringNotContainsString('Catatan Tematik', $content);
        $this->assertStringNotContainsString('Masukan Anggota per Dimensi', $content);
        $this->assertStringContainsString('Masukan lengkap di modal detail.', $content);
        $this->assertStringContainsString('Nama Pengisi Rahasia', $content);
    }

    public function test_balance_questions_do_not_pull_down_directional_overall_score_raw(): void
    {
        $this->createTables();
        $ids = $this->seedFeedbackFixture();
        $this->seedFeedback($ids, respondentName: 'Anggota Test', balanceScore: 5);

        $this->actingAsRecUser();

        $this->get('/pemuridan/umpan-balik-anggota')
            ->assertOk()
            ->assertSee('10,0/10')
            ->assertSee('Keseimbangan');
    }

    public function test_public_feedback_submit_invalidates_recap_cache(): void
    {
        $this->createTables();
        $ids = $this->seedFeedbackFixture();
        $this->actingAsRecUser();

        $this->get('/pemuridan/umpan-balik-anggota')
            ->assertOk()
            ->assertSee('Pengisi Feedback per Kelompok')
            ->assertDontSee('Belum ada jurnal umpan balik anggota pada scope ini.');

        $payload = [
            'action' => 'save_public_member_feedback',
            'public_cabang' => 'kutisari',
            'group_id' => (string) $ids['group_id'],
            'respondent_person_id' => (string) $ids['member_id'],
            'feedback_session' => '3',
            'ratings' => $this->fullRatingPayload(),
            'notes' => [
                'leader_notes' => 'Feedback baru setelah cache kosong.',
            ],
        ];

        $this->post('/publik/umpan-balik-anggota/form', $payload)
            ->assertRedirect(route('public.member-feedback.form', [
                'submitted' => 1,
                'cabang' => 'kutisari',
                'feedback_session' => 3,
            ]));

        $this->get('/pemuridan/umpan-balik-anggota')
            ->assertOk()
            ->assertSee('Feedback baru setelah cache kosong.');
    }

    private function elementHtmlById(string $html, string $id): string
    {
        $document = new \DOMDocument();
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
            $element = $document->getElementById($id);
            $this->assertNotNull($element, 'Elemen #'.$id.' tidak ditemukan.');

            return (string) $document->saveHTML($element);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private function createTables(): void
    {
        Schema::dropIfExists('jurnal_umpan_balik');
        Schema::dropIfExists('keanggotaan_kelompok_dg');
        Schema::dropIfExists('kelompok_dg');
        Schema::dropIfExists('orang');
        Schema::dropIfExists('cabang');

        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('cabang')->insert([
            ['id' => 1, 'label' => 'Kutisari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'label' => 'GM', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(BranchCatalog::class)->clearCache();

        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('kelompok_dg', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('status')->default('active');
            $table->string('stage')->nullable();
            $table->timestamps();
        });

        Schema::create('keanggotaan_kelompok_dg', function (Blueprint $table): void {
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

        Schema::create('jurnal_umpan_balik', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedTinyInteger('feedback_session');
            $table->unsignedBigInteger('discipleship_group_id')->nullable();
            $table->unsignedBigInteger('leader_person_id')->nullable();
            $table->unsignedBigInteger('respondent_person_id')->nullable();
            $table->string('respondent_name_snapshot')->nullable();
            $table->string('leader_name_snapshot')->nullable();
            $table->string('group_label_snapshot')->nullable();
            $table->string('group_progress_snapshot', 80)->nullable();
            $table->longText('ratings')->nullable();
            $table->longText('notes')->nullable();
            $table->string('source', 80)->default('public_form');
            $table->timestamps();
        });
    }

    /**
     * @return array{leader_id:int,member_id:int,group_id:int,branch_id:int,leader_name:string,member_name:string,group_name:string}
     */
    private function seedFeedbackFixture(
        int $branchId = 1,
        string $leaderName = 'Pemimpin Test',
        string $memberName = 'Anggota Test',
        string $groupName = 'Kelompok Test',
    ): array
    {
        $leaderId = DB::table('orang')->insertGetId([
            'branch_id' => $branchId,
            'full_name' => $leaderName,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('orang')->insertGetId([
            'branch_id' => $branchId,
            'full_name' => $memberName,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => $branchId,
            'status' => 'active',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('keanggotaan_kelompok_dg')->insert([
            [
                'branch_id' => $branchId,
                'discipleship_group_id' => $groupId,
                'person_id' => $leaderId,
                'role' => 'leader',
                'stage' => null,
                'status' => 'active',
                'started_on' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => $branchId,
                'discipleship_group_id' => $groupId,
                'person_id' => $memberId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return [
            'leader_id' => $leaderId,
            'member_id' => $memberId,
            'group_id' => $groupId,
            'branch_id' => $branchId,
            'leader_name' => $leaderName,
            'member_name' => $memberName,
            'group_name' => $groupName,
        ];
    }

    /**
     * @param  array{leader_id:int,member_id:int,group_id:int,branch_id:int,leader_name:string,member_name:string,group_name:string}  $ids
     */
    private function seedFeedback(
        array $ids,
        string $respondentName = 'Anggota Test',
        string $noteContent = 'Catatan feedback.',
        int $balanceScore = 3,
        int $feedbackSession = 3,
    ): void
    {
        DB::table('jurnal_umpan_balik')->insert([
            'branch_id' => $ids['branch_id'],
            'feedback_session' => $feedbackSession,
            'discipleship_group_id' => $ids['group_id'],
            'leader_person_id' => $ids['leader_id'],
            'respondent_person_id' => $ids['member_id'],
            'respondent_name_snapshot' => $respondentName,
            'leader_name_snapshot' => $ids['leader_name'],
            'group_label_snapshot' => 'DG 1 ('.$ids['leader_name'].') - '.$respondentName,
            'group_progress_snapshot' => 'DG 1',
            'ratings' => json_encode($this->ratingRows($balanceScore)),
            'notes' => json_encode([[
                'section_key' => 'leadership',
                'note_key' => 'leader_notes',
                'content' => $noteContent,
            ]]),
            'source' => 'public_form',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function fullRatingPayload(): array
    {
        $payload = [];
        foreach (app(MemberFeedbackQuestionCatalog::class)->ratingQuestions() as $question) {
            $payload[$question['key']] = $question['scale'] === 5 ? 3 : 10;
        }

        return $payload;
    }

    /**
     * @return array<int, array{section_key:string,question_key:string,score:int,scale:int}>
     */
    private function ratingRows(int $balanceScore): array
    {
        $rows = [];
        foreach (app(MemberFeedbackQuestionCatalog::class)->ratingQuestions() as $question) {
            $isBalance = in_array($question['key'], ['meeting_duration', 'meeting_member_count'], true);
            $rows[] = [
                'section_key' => $question['section_key'],
                'question_key' => $question['key'],
                'score' => $isBalance ? $balanceScore : 10,
                'scale' => $question['scale'],
            ];
        }

        return $rows;
    }
}

