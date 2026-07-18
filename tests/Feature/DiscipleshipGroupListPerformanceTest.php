<?php

namespace Tests\Feature;

use App\Services\Branches\BranchCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\AssertsDiscipleshipWorkspace;
use Tests\TestCase;

class DiscipleshipGroupListPerformanceTest extends TestCase
{
    use AssertsDiscipleshipWorkspace;

    public function test_group_list_renders_shared_workspace_with_groups_tab_active(): void
    {
        $this->createTables();
        $this->actingAsRecUser();

        $content = (string) $this->get('/pemuridan/kelompok')->assertOk()->getContent();

        $this->assertDiscipleshipWorkspace($content, 'groups');
        $this->assertUnifiedDiscipleshipSidebar($content, 'Kutisari');
    }

    public function test_group_list_tab_fragment_returns_only_the_marked_panel(): void
    {
        $this->createTables();
        $this->actingAsRecUser();

        $response = $this->withHeaders([
            'X-Discipleship-Fragment' => 'tab',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get('/pemuridan/kelompok')->assertOk();

        $response->assertSee('Daftar Kelompok DG');
        $this->assertDiscipleshipTabFragment((string) $response->getContent(), 'groups');
    }

    public function test_group_list_lazy_loads_rows_and_searches_server_side(): void
    {
        $this->createTables();
        $people = [];
        for ($index = 1; $index <= 600; $index++) {
            $people[] = ['branch_id' => 1, 'full_name' => sprintf('Orang %04d', $index), 'status' => 'active'];
        }
        DB::table('orang')->insert($people);
        $groups = [];
        for ($index = 1; $index <= 300; $index++) {
            $groups[] = ['branch_id' => 1, 'status' => 'active', 'stage' => 'DG 1'];
        }
        DB::table('kelompok_dg')->insert($groups);
        $links = [];
        for ($index = 1; $index <= 300; $index++) {
            $links[] = ['branch_id' => 1, 'discipleship_group_id' => $index, 'person_id' => $index, 'role' => 'leader', 'stage' => null, 'status' => 'active'];
            $links[] = ['branch_id' => 1, 'discipleship_group_id' => $index, 'person_id' => 300 + $index, 'role' => 'member', 'stage' => 'DG 1', 'status' => 'active'];
        }
        DB::table('keanggotaan_kelompok_dg')->insert($links);
        $this->actingAsRecUser();
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $response = $this->get('/pemuridan/kelompok');

        $response->assertOk()
            ->assertSee('Orang 0001')
            ->assertSee('Orang 0050')
            ->assertDontSee('Orang 0051')
            ->assertDontSee('Orang 0300')
            ->assertDontSee('rec-pagination', false)
            ->assertDontSee('Halaman 1 dari')
            ->assertSee('data-discipleship-groups-list', false)
            ->assertSee('data-discipleship-groups-search-form', false)
            ->assertSee('data-discipleship-groups-search-input', false)
            ->assertSee('data-tree-group-detail-url-template=', false)
            ->assertSee('data-tree-v2-history-modal', false)
            ->assertSee('data-tree-v2-history-open="1"', false)
            ->assertSee('Riwayat Kelompok')
            ->assertSee('tree-group-history-modal-card discipleship-tree-panel', false)
            ->assertSee('data-tree-v2-action-do="add_member"', false)
            ->assertSee('data-tree-v2-action-do="complete_group"', false)
            ->assertSee('data-tree-v2-action-do="upgrade_group"', false)
            ->assertSee('data-next-cursor="', false)
            ->assertDontSee('discipleship-page-header__stats', false)
            ->assertDontSee('discipleship-page-header__stat', false)
            ->assertDontSee('data-groups-stat=', false)
            ->assertDontSee('>Cari</button>', false);
        $this->assertLessThanOrEqual(12, $queries);

        preg_match('/data-next-cursor="([^"]+)"/', (string) $response->getContent(), $cursorMatch);
        $this->assertNotEmpty($cursorMatch[1] ?? '');
        $pageTwo = $this->get('/pemuridan/kelompok/rows?'.http_build_query(['cursor' => $cursorMatch[1]]));
        $pageTwo->assertOk()
            ->assertJsonStructure(['html', 'stats', 'has_more', 'next_cursor', 'empty'])
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('empty', false)
            ->assertJsonPath('stats.total', 300);
        $this->assertNotEmpty($pageTwo->json('next_cursor'));
        $this->assertArrayNotHasKey('next_page', $pageTwo->json());
        $this->assertStringContainsString('Orang 0051', (string) $pageTwo->json('html'));
        $this->assertStringContainsString('data-tree-v2-history-open="51"', (string) $pageTwo->json('html'));
        $this->assertStringContainsString('Orang 0100', (string) $pageTwo->json('html'));
        $this->assertStringNotContainsString('Orang 0101', (string) $pageTwo->json('html'));

        $search = $this->get('/pemuridan/kelompok/rows?q=Orang+0300');
        $search->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('stats.total', 1);
        $this->assertStringContainsString('Orang 0300', (string) $search->json('html'));
        $this->assertStringNotContainsString('Orang 0299', (string) $search->json('html'));
    }

    public function test_inactive_group_shows_its_last_leader_and_members(): void
    {
        $this->createTables();
        DB::table('orang')->insert([
            ['id' => 1, 'branch_id' => 1, 'full_name' => 'Pemimpin Historis', 'status' => 'active'],
            ['id' => 2, 'branch_id' => 1, 'full_name' => 'Pendamping Historis', 'status' => 'active'],
            ['id' => 3, 'branch_id' => 1, 'full_name' => 'Anggota Historis', 'status' => 'active'],
        ]);
        DB::table('kelompok_dg')->insert([
            'id' => 1,
            'branch_id' => 1,
            'status' => 'completed',
            'stage' => 'DG 2',
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            ['branch_id' => 1, 'discipleship_group_id' => 1, 'person_id' => 1, 'role' => 'leader', 'stage' => null, 'status' => 'closed', 'ended_on' => '2026-05-01'],
            ['branch_id' => 1, 'discipleship_group_id' => 1, 'person_id' => 2, 'role' => 'co_leader', 'stage' => null, 'status' => 'closed', 'ended_on' => '2026-05-01'],
            ['branch_id' => 1, 'discipleship_group_id' => 1, 'person_id' => 3, 'role' => 'member', 'stage' => 'DG 2', 'status' => 'closed', 'ended_on' => '2026-05-01'],
        ]);
        $this->actingAsRecUser();

        $this->get('/pemuridan/kelompok?status=inactive')
            ->assertOk()
            ->assertSee('Pemimpin Historis')
            ->assertSee('Riwayat pendamping: Pendamping Historis')
            ->assertSee('Anggota Historis')
            ->assertSee('1 peserta tercatat')
            ->assertDontSee('Belum ada peserta')
            ->assertDontSee('Tanpa pendamping');
    }

    public function test_group_without_any_people_history_is_not_listed_or_counted(): void
    {
        $this->createTables();
        DB::table('kelompok_dg')->insert([
            'branch_id' => 1,
            'status' => 'completed',
            'stage' => 'DG 1',
        ]);
        $this->actingAsRecUser();

        $this->get('/pemuridan/kelompok')
            ->assertOk()
            ->assertDontSee('data-groups-stat=', false)
            ->assertSee('Belum ada kelompok.');
    }

    private function createTables(): void
    {
        Schema::dropIfExists('keanggotaan_kelompok_dg');
        Schema::dropIfExists('kelompok_dg');
        Schema::dropIfExists('orang');
        $this->createBranchesTable();

        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name');
            $table->string('status')->default('active');
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
            $table->unsignedBigInteger('person_id');
            $table->string('role');
            $table->string('stage')->nullable();
            $table->string('status')->default('active');
            $table->date('ended_on')->nullable();
        });
    }

    private function createBranchesTable(): void
    {
        Schema::dropIfExists('cabang');

        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('camp_gap_participant_target')->default(50);
            $table->unsignedInteger('msk_completion_target')->default(50);
            $table->unsignedInteger('dg1_completion_target')->default(50);
            $table->unsignedInteger('dg2_completion_target')->default(50);
            $table->unsignedInteger('dg3_completion_target')->default(50);
            $table->timestamps();
        });

        DB::table('cabang')->insert([
            ['id' => 1, 'label' => 'Kutisari', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'label' => 'GM', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'label' => 'Darmo', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'label' => 'Merr', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'label' => 'Batam', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'label' => 'Nginden', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(BranchCatalog::class)->clearCache();
    }
}
