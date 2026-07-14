<?php

namespace Tests\Feature;

use App\Services\Branches\BranchCatalog;
use App\Services\DiscipleshipPeopleTree\PeopleTreeModelStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\AssertsDiscipleshipWorkspace;
use Tests\TestCase;

class PeopleTreePageTest extends TestCase
{
    use AssertsDiscipleshipWorkspace;

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
        $response->assertSee('tree-v2-person-profile-modal', false);
        $response->assertSee('data-tree-person-detail-url-template=', false);
        $response->assertSee('data-tree-group-detail-url-template=', false);
        $response->assertDontSee('data-tree-v2-person-profile-template=', false);
        $response->assertDontSee('data-tree-v2-history-template=', false);
        $response->assertSee('tree-v2-history-actions', false);
        $response->assertSee('data-tree-v2-action-do="add_member"', false);
        $response->assertSee('data-tree-v2-action-do="upgrade_group"', false);
        $response->assertSee('data-tree-v2-profile-action="add_group"', false);
        $response->assertSee('data-tree-v2-profile-action="edit_person"', false);
        $response->assertSee('data-tree-v2-profile-action="leave_group"', false);
        $response->assertSee('data-tree-v2-profile-action="delete_person"', false);
        $response->assertDontSee('Lihat Riwayat Pemuridan');
        $response->assertDontSee('spiritual-journey-view-modal', false);
        $response->assertDontSee('data-tree-v2-proxy="view-person-journey"', false);
    }

    public function test_people_tree_renders_shared_workspace_and_branch_workspace_sidebar(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        $this->actingAsRecUser();

        $content = (string) $this->get('/pemuridan/pohon')->assertOk()->getContent();

        $this->assertDiscipleshipWorkspace($content, 'tree');
        $this->assertUnifiedDiscipleshipSidebar($content, 'Kutisari');

        $xpath = $this->discipleshipMarkupXpath($content);
        $branchNav = '//*[@id="app-sidebar"]//*[@data-discipleship-branch-nav]';
        $this->assertSame(1.0, $xpath->evaluate('count('.$branchNav.')'));
        $this->assertSame(1.0, $xpath->evaluate('count('.$branchNav.'//details[@data-discipleship-branch-group="kutisari" and @open])'));
        $this->assertSame(1.0, $xpath->evaluate('count('.$branchNav.'//details[@data-discipleship-branch-group="kutisari"]/summary[starts-with(normalize-space(.), "Kutisari")])'));
        $this->assertSame(1.0, $xpath->evaluate('count('.$branchNav.'//details[@data-discipleship-branch-group="kutisari"]//a[normalize-space(.) = "Dashboard" and contains(@href, "branch_id=1")])'));
    }

    public function test_people_tree_tab_fragment_returns_only_the_marked_panel(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        $this->actingAsRecUser();

        $response = $this->withHeaders([
            'X-Discipleship-Fragment' => 'tab',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html',
        ])->get('/pemuridan/pohon')->assertOk();

        $response->assertSee('Leader Test');
        $this->assertDiscipleshipTabFragment((string) $response->getContent(), 'tree');
    }

    public function test_central_and_developer_have_switchable_branch_sidebar_without_legacy_toolbar(): void
    {
        $this->createTables();
        $this->seedPeopleTree();

        $this->actingAsRecUser('central_reader', null, 'pemuridan_pusat');
        $centralContent = (string) $this->get('/pemuridan/pohon?branch_id=all&q=Yohanes&per_page=10&edit=123&error=invalid_person&conflict=1')->assertOk()->getContent();
        $centralXpath = $this->discipleshipMarkupXpath($centralContent);
        $branchNav = '//*[@id="app-sidebar"]//*[@data-discipleship-branch-nav]';

        $this->assertSame(1.0, $centralXpath->evaluate('count('.$branchNav.')'));
        $this->assertSame(1.0, $centralXpath->evaluate('count('.$branchNav.'//form[contains(concat(" ", normalize-space(@class), " "), " discipleship-branch-filter ")])'));
        $this->assertSame(0.0, $centralXpath->evaluate('count('.$branchNav.'//details[@data-discipleship-branch-group])'));
        $this->assertSame(1.0, $centralXpath->evaluate('count('.$branchNav.'//select[@name="branch_id"]/option[@value="all" and @selected])'));
        $this->assertSame(1.0, $centralXpath->evaluate('count('.$branchNav.'//select[@name="branch_id"]/option[normalize-space(.) = "Kutisari"])'));
        $this->assertSame(1.0, $centralXpath->evaluate('count('.$branchNav.'//select[@name="branch_id"]/option[normalize-space(.) = "GM"])'));
        $this->assertSame(0.0, $centralXpath->evaluate('count(//*[contains(concat(" ", normalize-space(@class), " "), " central-rekap-toolbar ")])'));

        $dashboardLink = $centralXpath->query($branchNav.'//a[normalize-space(.) = "Dashboard"]')?->item(0);
        $this->assertNotNull($dashboardLink);
        parse_str((string) parse_url($dashboardLink->getAttribute('href'), PHP_URL_QUERY), $dashboardQuery);
        $this->assertSame('all', $dashboardQuery['branch_id'] ?? null);

        $testingBranchId = (int) DB::table('cabang')->insertGetId([
            'label' => 'Testing',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(BranchCatalog::class)->clearCache();

        $this->actingAsRecUser('developer', null, 'developer');
        $developerContent = (string) $this->get('/pemuridan/pohon?branch_id='.$testingBranchId)->assertOk()->getContent();
        $developerXpath = $this->discipleshipMarkupXpath($developerContent);

        $this->assertSame(1.0, $developerXpath->evaluate('count('.$branchNav.'//select[@name="branch_id"]/option[@value="'.$testingBranchId.'" and @selected])'));
        $this->assertSame(1.0, $developerXpath->evaluate('count('.$branchNav.'//select[@name="branch_id"]/option[normalize-space(.) = "Semua Cabang"])'));
        $this->assertSame(0.0, $developerXpath->evaluate('count(//*[contains(concat(" ", normalize-space(@class), " "), " central-rekap-toolbar ")])'));
    }

    public function test_people_tree_renders_cross_branch_leader_without_local_duplicate(): void
    {
        $this->createTables();
        $this->seedCrossBranchLeaderGroup();

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/pohon')
            ->assertOk()
            ->assertSee('Yakub Tri Handoko (GM)')
            ->assertSee('Anggota Kutisari Lintas');

        $response->assertDontSee('data-phone=', false)
            ->assertDontSee('data-notes=', false)
            ->assertDontSee('data-tree-v2-person-profile-template=', false);

        $profileResponse = $this->get('/pemuridan/pohon/orang/626/detail')
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'edit_url', 'edit']);
        $profileHtml = (string) $profileResponse->json('html');
        $this->assertStringNotContainsString('MSK dan pemuridan aktif', $profileHtml);
        $this->assertStringNotContainsString('Riwayat Sebagai Anggota', $profileHtml);
        $this->assertStringNotContainsString('Belum DG', $profileHtml);
        $this->assertStringContainsString('External', $profileHtml);
        $this->assertStringContainsString('Riwayat Memimpin', $profileHtml);
        $this->assertStringContainsString('Anggota Kutisari Lintas', $profileHtml);

        $this->assertSame(
            0,
            DB::table('orang')->where('branch_id', 1)->where('full_name', 'Yakub Tri Handoko')->count(),
        );
    }

    public function test_people_tree_excludes_msk_only_people_from_tree_nodes(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        $mskOnlyId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Peserta MSK Saja',
            'whatsapp' => '089999999999',
            'batch_month' => '2026-06',
            'status' => 'active',
            'session_numbers' => json_encode(range(1, 12)),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsRecUser();
        $this->get('/pemuridan/pohon')
            ->assertOk()
            ->assertSee('<option value="'.$mskOnlyId.'" data-person-id="">Peserta MSK Saja</option>', false)
            ->assertDontSee('data-tree-v2-node-action="person" data-person-id="'.$mskOnlyId.'"', false);

        $store = app(PeopleTreeModelStore::class);
        $model = $store->modelForBranch('kutisari');
        $participants = $store->participantsForBranches(['kutisari'], false);
        $people = $store->peopleForModel($model, [], $participants, false);

        $this->assertContains((string) $mskOnlyId, array_map(
            static fn (array $row): string => (string) ($row['member_id'] ?? ''),
            $participants,
        ));
        $this->assertNotContains((string) $mskOnlyId, array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            $model['discipleship_persons'],
        ));
        $this->assertNotContains('Peserta MSK Saja', array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $people,
        ));

        $store->replaceBranchModel('kutisari', $model);

        $this->assertDatabaseHas('orang', [
            'id' => $mskOnlyId,
            'full_name' => 'Peserta MSK Saja',
        ]);
    }

    public function test_central_selected_branch_tree_renders_cross_branch_leader_in_branch_context(): void
    {
        $this->createTables();
        $this->seedCrossBranchLeaderGroup();

        $this->actingAsRecUser('central_reader', null, 'pemuridan_pusat');

        $this->get('/pemuridan/pohon?branch_id=1')
            ->assertOk()
            ->assertSee('Yakub Tri Handoko (GM)')
            ->assertSee('Anggota Kutisari Lintas')
            ->assertSee('DG 1 (Yakub Tri Handoko (GM))')
            ->assertDontSee('data-tree-v2-person-profile-template="626"', false)
            ->assertSee('data-tree-v2-node-action="person" data-person-id="626"', false)
            ->assertSee('tree-v2-node tree-v2-person is-male is-actionable', false)
            ->assertDontSee('data-tree-v2-profile-action="edit_person"', false)
            ->assertDontSee('data-tree-v2-action-modal', false);

        $detail = $this->get('/pemuridan/pohon/orang/626/detail?branch_id=1')
            ->assertOk()
            ->assertJsonPath('edit_url', null);
        $this->assertStringContainsString('Riwayat Memimpin', (string) $detail->json('html'));
        $this->assertStringNotContainsString('Peserta ini belum terhubung ke data pemuridan.', (string) $detail->json('html'));
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

    public function test_tree_detail_endpoints_are_authorized_and_scoped_to_visible_branch_graph(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        $ownPersonId = (int) DB::table('orang')->where('full_name', 'Anggota Test')->value('id');
        $ownGroupId = (int) DB::table('kelompok_dg')->where('branch_id', 1)->value('id');

        $otherPersonId = DB::table('orang')->insertGetId([
            'branch_id' => 2,
            'full_name' => 'Orang Cabang GM',
            'status' => 'active',
            'session_numbers' => json_encode([]),
            'photos' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherGroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 2,
            'status' => 'active',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 2,
            'discipleship_group_id' => $otherGroupId,
            'person_id' => $otherPersonId,
            'role' => 'leader',
            'status' => 'active',
            'started_on' => '2026-07-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsRecUser();

        $this->get('/pemuridan/pohon/orang/'.$ownPersonId.'/detail')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['title', 'html', 'edit_url', 'edit']);
        $this->get('/pemuridan/pohon/kelompok/'.$ownGroupId.'/detail')
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'edit_url']);

        $this->get('/pemuridan/pohon/orang/'.$otherPersonId.'/detail')->assertNotFound();
        $this->get('/pemuridan/pohon/kelompok/'.$otherGroupId.'/detail')->assertNotFound();
    }

    public function test_tree_person_detail_query_count_is_bounded_for_one_visible_person(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        $personId = (int) DB::table('orang')->where('full_name', 'Anggota Test')->value('id');
        $this->actingAsRecUser();

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = $query->sql;
            }
        });

        $this->get('/pemuridan/pohon/orang/'.$personId.'/detail')->assertOk();

        $this->assertLessThanOrEqual(6, count($queries), implode("\n", $queries));
    }

    public function test_tree_group_detail_query_count_is_bounded_for_one_visible_group(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        $groupId = (int) DB::table('kelompok_dg')->where('branch_id', 1)->value('id');
        $this->actingAsRecUser();

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = $query->sql;
            }
        });

        $this->get('/pemuridan/pohon/kelompok/'.$groupId.'/detail')->assertOk();

        $this->assertLessThanOrEqual(6, count($queries), implode("\n", $queries));
    }

    public function test_tree_initial_graph_query_count_does_not_grow_per_person(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        $groupId = (int) DB::table('kelompok_dg')->where('branch_id', 1)->value('id');
        $now = now();
        $memberships = [];
        for ($index = 1; $index <= 100; $index++) {
            $personId = DB::table('orang')->insertGetId([
                'branch_id' => 1,
                'full_name' => sprintf('Anggota Skala %03d', $index),
                'status' => 'active',
                'session_numbers' => json_encode([]),
                'photos' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $memberships[] = [
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('keanggotaan_kelompok_dg')->insert($memberships);
        $this->actingAsRecUser();

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = $query->sql;
            }
        });

        $response = $this->get('/pemuridan/pohon')->assertOk();

        $response->assertSee('Anggota Skala 100');
        $this->assertLessThanOrEqual(10, count($queries), implode("\n", $queries));
        $this->assertFalse(collect($queries)->contains(
            static fn (string $sql): bool => str_contains(strtolower($sql), 'jurnal_temu_dg')
                || str_contains(strtolower($sql), '"photos"')
                || str_contains(strtolower($sql), '"birth_date"'),
        ));
    }

    public function test_person_without_active_membership_stays_in_their_latest_group(): void
    {
        $this->createTables();
        $this->seedPeopleTree();

        $leaderId = (int) DB::table('orang')->where('full_name', 'Leader Test')->value('id');
        $oldGroupId = (int) DB::table('kelompok_dg')->orderBy('id')->value('id');
        $personId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Riwayat Terakhir',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $latestGroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => $oldGroupId,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'completed',
                'started_on' => '2025-01-01',
                'ended_on' => '2025-06-01',
                'end_reason' => 'stage_transition',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $latestGroupId,
                'person_id' => $leaderId,
                'role' => 'leader',
                'stage' => null,
                'status' => 'active',
                'started_on' => '2025-07-01',
                'ended_on' => null,
                'end_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $latestGroupId,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 2',
                'status' => 'completed',
                'started_on' => '2025-07-01',
                'ended_on' => '2026-02-01',
                'end_reason' => 'left_group',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAsRecUser();
        $this->get('/pemuridan/pohon')->assertOk()->assertSee('Anggota Riwayat Terakhir');

        $store = app(PeopleTreeModelStore::class);
        $model = $store->modelForBranch('kutisari');
        $people = $store->peopleForModel($model, [], [], false);
        $groups = collect(build_people_tree_group_rows($model, index_by_id($people)))->keyBy('id');

        $this->assertNotContains((string) $personId, $groups[(string) $oldGroupId]['member_ids']);
        $this->assertContains((string) $personId, $groups[(string) $latestGroupId]['member_ids']);
    }

    public function test_group_history_roster_shows_one_row_per_person(): void
    {
        $this->createTables();
        $this->seedPeopleTree();

        $leaderId = (int) DB::table('orang')->where('full_name', 'Leader Test')->value('id');
        $memberId = (int) DB::table('orang')->where('full_name', 'Anggota Test')->value('id');
        $groupId = (int) DB::table('kelompok_dg')->orderBy('id')->value('id');
        $historicalOnlyId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Histori Saja',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('keanggotaan_kelompok_dg')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => $leaderId,
                'role' => 'leader',
                'stage' => null,
                'status' => 'closed',
                'started_on' => '2025-11-01',
                'ended_on' => '2025-12-01',
                'end_reason' => 'person_archived',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => $memberId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'closed',
                'started_on' => '2025-11-01',
                'ended_on' => '2025-12-01',
                'end_reason' => 'person_archived',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => $historicalOnlyId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'closed',
                'started_on' => '2025-11-01',
                'ended_on' => '2025-12-01',
                'end_reason' => 'person_archived',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => $historicalOnlyId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'closed',
                'started_on' => '2026-01-01',
                'ended_on' => '2026-02-01',
                'end_reason' => 'left_group',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAsRecUser();

        $response = $this->get('/pemuridan/pohon')->assertOk();
        $response->assertDontSee('data-tree-v2-history-template=', false);
        $historyResponse = $this->get('/pemuridan/pohon/kelompok/'.$groupId.'/detail')
            ->assertOk()
            ->assertJsonStructure(['title', 'html', 'edit_url']);
        $historyHtml = (string) $historyResponse->json('html');

        $this->assertSame(1, substr_count($historyHtml, '<strong>Leader Test</strong>'));
        $this->assertSame(1, substr_count($historyHtml, '<strong>Anggota Test</strong>'));
        $this->assertSame(1, substr_count($historyHtml, '<strong>Anggota Histori Saja</strong>'));
        $this->assertStringContainsString('1 pemimpin - 2 anggota', $historyHtml);
        $this->assertStringContainsString('Keluar dari kelompok', $historyHtml);
        $this->assertStringNotContainsString('Person Archived', $historyHtml);
    }

    public function test_upgraded_completed_group_cannot_be_reactivated(): void
    {
        $this->createTables();
        $this->seedPeopleTree();

        $leaderId = (int) DB::table('orang')->where('full_name', 'Leader Test')->value('id');
        $memberId = (int) DB::table('orang')->where('full_name', 'Anggota Test')->value('id');
        $parentGroupId = (int) DB::table('kelompok_dg')->orderBy('id')->value('id');
        DB::table('kelompok_dg')->where('id', $parentGroupId)->update([
            'status' => 'completed',
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->where('discipleship_group_id', $parentGroupId)->update([
            'status' => 'closed',
            'ended_on' => '2026-07-01',
            'end_reason' => 'continued_to_child_group',
            'updated_at' => now(),
        ]);
        $childGroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 2',
            'parent_group_id' => $parentGroupId,
            'source_group_id' => $parentGroupId,
            'initiated_by_person_id' => $leaderId,
            'multiplied_at' => '2026-07-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => $childGroupId,
                'person_id' => $leaderId,
                'role' => 'leader',
                'stage' => null,
                'status' => 'active',
                'started_on' => '2026-07-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $childGroupId,
                'person_id' => $memberId,
                'role' => 'member',
                'stage' => 'DG 2',
                'status' => 'active',
                'started_on' => '2026-07-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAsRecUser();

        $content = $this->get('/pemuridan/pohon')->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<article\b(?=[^>]*data-tree-v2-node-action="group")(?=[^>]*data-group-id="'.preg_quote((string) $parentGroupId, '/').'")(?=[^>]*data-has-child-group="1")[^>]*>/',
            $content,
        );

        $this->post('/pemuridan/pohon/kelompok/aktifkan', [
            'id' => (string) $parentGroupId,
            'return_page' => 'people_tree',
        ])->assertRedirect('/pemuridan/pohon?error=group_has_child');

        $this->assertDatabaseHas('kelompok_dg', [
            'id' => $parentGroupId,
            'status' => 'completed',
        ]);
    }

    public function test_latest_historical_group_prefers_highest_stage_when_dates_match(): void
    {
        $this->createTables();
        $this->seedPeopleTree();

        $leaderId = (int) DB::table('orang')->where('full_name', 'Leader Test')->value('id');
        $personId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Tanggal Sama',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sameTimestamp = '2026-07-07 12:12:03';
        $dg1GroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'completed',
            'stage' => 'DG 1',
            'created_at' => $sameTimestamp,
            'updated_at' => $sameTimestamp,
        ]);
        $dg2GroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'completed',
            'stage' => 'DG 2',
            'parent_group_id' => $dg1GroupId,
            'source_group_id' => $dg1GroupId,
            'initiated_by_person_id' => $leaderId,
            'multiplied_at' => '2026-04-09',
            'created_at' => $sameTimestamp,
            'updated_at' => $sameTimestamp,
        ]);
        $dg3GroupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'completed',
            'stage' => 'DG 3',
            'parent_group_id' => $dg2GroupId,
            'source_group_id' => $dg2GroupId,
            'initiated_by_person_id' => $leaderId,
            'multiplied_at' => '2026-04-09',
            'created_at' => $sameTimestamp,
            'updated_at' => $sameTimestamp,
        ]);

        DB::table('keanggotaan_kelompok_dg')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => $dg2GroupId,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 2',
                'status' => 'closed',
                'started_on' => '2026-04-09',
                'ended_on' => '2026-04-09',
                'end_reason' => 'continued_to_child_group',
                'created_at' => $sameTimestamp,
                'updated_at' => $sameTimestamp,
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $dg3GroupId,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 3',
                'status' => 'closed',
                'started_on' => '2026-04-09',
                'ended_on' => '2026-04-09',
                'end_reason' => 'group_completed',
                'created_at' => $sameTimestamp,
                'updated_at' => $sameTimestamp,
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $dg1GroupId,
                'person_id' => $personId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'closed',
                'started_on' => '2026-04-09',
                'ended_on' => '2026-04-09',
                'end_reason' => 'continued_to_child_group',
                'created_at' => $sameTimestamp,
                'updated_at' => $sameTimestamp,
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $dg1GroupId,
                'person_id' => $leaderId,
                'role' => 'leader',
                'stage' => null,
                'status' => 'closed',
                'started_on' => '2026-04-09',
                'ended_on' => '2026-04-09',
                'end_reason' => null,
                'created_at' => $sameTimestamp,
                'updated_at' => $sameTimestamp,
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $dg2GroupId,
                'person_id' => $leaderId,
                'role' => 'leader',
                'stage' => null,
                'status' => 'closed',
                'started_on' => '2026-04-09',
                'ended_on' => '2026-04-09',
                'end_reason' => null,
                'created_at' => $sameTimestamp,
                'updated_at' => $sameTimestamp,
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $dg3GroupId,
                'person_id' => $leaderId,
                'role' => 'leader',
                'stage' => null,
                'status' => 'closed',
                'started_on' => '2026-04-09',
                'ended_on' => '2026-04-09',
                'end_reason' => null,
                'created_at' => $sameTimestamp,
                'updated_at' => $sameTimestamp,
            ],
        ]);

        $store = app(PeopleTreeModelStore::class);
        $model = $store->modelForBranch('kutisari');
        $people = $store->peopleForModel($model, [], [], false);
        $groups = collect(build_people_tree_group_rows($model, index_by_id($people)))->keyBy('id');

        $this->assertNotContains((string) $personId, $groups[(string) $dg2GroupId]['member_ids']);
        $this->assertContains((string) $personId, $groups[(string) $dg3GroupId]['member_ids']);
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

    public function test_adding_archived_msk_participant_reactivates_existing_person_instead_of_duplicating(): void
    {
        $this->createTables();
        $this->seedPeopleTree();

        $leaderId = (int) DB::table('orang')->where('full_name', 'Leader Test')->value('id');
        $groupId = (int) DB::table('kelompok_dg')->orderBy('id')->value('id');
        $archivedPersonId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Peserta Arsip MSK',
            'whatsapp' => '081298765432',
            'gender' => 'Perempuan',
            'status' => 'inactive',
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('orang')->where('id', $archivedPersonId)->update([
            'gender' => 'Perempuan',
            'whatsapp' => '081298765432',
            'batch_month' => '2026-01',
            'session_numbers' => json_encode(range(1, 12)),
            'photos' => json_encode([]),
            'updated_at' => now(),
        ]);

        $this->actingAsRecUser();
        $this->post('/pemuridan/pohon/orang', [
            'member_id' => (string) $archivedPersonId,
            'leader_id' => (string) $leaderId,
            'group_id' => (string) $groupId,
            'return_page' => 'people_tree',
        ])->assertRedirect('/pemuridan/pohon?saved=1');

        $this->assertSame(
            1,
            DB::table('orang')->where('full_name', 'Peserta Arsip MSK')->count(),
        );
        $this->assertDatabaseHas('orang', [
            'id' => $archivedPersonId,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('keanggotaan_kelompok_dg', [
            'discipleship_group_id' => $groupId,
            'person_id' => $archivedPersonId,
            'role' => 'member',
            'status' => 'active',
        ]);
        $this->assertFalse(Schema::hasTable('relasi_dg'));
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
                    'status' => 'active',
                    'stage' => 'DG 1',
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

        $leaderId = (int) DB::table('orang')->where('full_name', 'Leader Baru')->value('id');
        $memberId = (int) DB::table('orang')->where('full_name', 'Anggota Baru')->value('id');
        $groupId = (int) DB::table('kelompok_dg')->value('id');

        $this->assertGreaterThan(0, $leaderId);
        $this->assertGreaterThan(0, $memberId);
        $this->assertGreaterThan(0, $groupId);
        $this->assertDatabaseHas('orang', [
            'full_name' => 'Leader Baru',
        ]);
        $this->assertDatabaseHas('orang', [
            'full_name' => 'Anggota Baru',
        ]);
        $this->assertFalse(Schema::hasTable('relasi_dg'));
        $this->assertDatabaseHas('keanggotaan_kelompok_dg', [
            'discipleship_group_id' => $groupId,
            'person_id' => $leaderId,
            'role' => 'leader',
        ]);
        $this->assertDatabaseHas('keanggotaan_kelompok_dg', [
            'discipleship_group_id' => $groupId,
            'person_id' => $memberId,
            'role' => 'member',
        ]);
    }

    public function test_people_tree_save_group_accepts_cross_branch_leader_but_rejects_cross_branch_member(): void
    {
        $this->createTables();
        $this->seedPeopleTree();
        DB::table('orang')->insert([
            'id' => 626,
            'branch_id' => 2,
            'full_name' => 'Yakub Tri Handoko',
            'gender' => 'Laki-laki',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('orang')->insert([
            'id' => 900,
            'branch_id' => 2,
            'full_name' => 'Anggota GM Tidak Valid',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsRecUser();

        $this->post('/pemuridan/pohon/kelompok', [
            'leader_id' => '626',
            'assistant_id' => '',
            'progress' => 'DG 1',
            'parent_group_id' => '',
            'notes' => 'Kelompok dipimpin lintas cabang',
            'return_page' => 'people_tree',
        ])->assertRedirect('/pemuridan/pohon?saved=1');

        $newGroupId = (int) DB::table('kelompok_dg')
            ->where('notes', 'Kelompok dipimpin lintas cabang')
            ->value('id');
        $this->assertGreaterThan(0, $newGroupId);
        $this->assertDatabaseHas('keanggotaan_kelompok_dg', [
            'branch_id' => 1,
            'discipleship_group_id' => $newGroupId,
            'person_id' => 626,
            'role' => 'leader',
        ]);
        $this->assertSame(
            0,
            DB::table('orang')->where('branch_id', 1)->where('full_name', 'Yakub Tri Handoko')->count(),
        );

        $beforeGroupCount = DB::table('kelompok_dg')->count();
        $this->post('/pemuridan/pohon/kelompok', [
            'leader_id' => '626',
            'assistant_id' => '',
            'progress' => 'DG 1',
            'parent_group_id' => '',
            'member_ids' => ['900'],
            'notes' => 'Kelompok dengan member lintas cabang',
            'return_page' => 'people_tree',
        ])->assertRedirect('/pemuridan/pohon?error=invalid_group');

        $this->assertSame($beforeGroupCount, DB::table('kelompok_dg')->count());
        $this->assertDatabaseMissing('keanggotaan_kelompok_dg', [
            'branch_id' => 1,
            'person_id' => 900,
            'role' => 'member',
        ]);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('dg_manual');
        Schema::dropIfExists('keanggotaan_kelompok_dg');
        Schema::dropIfExists('relasi_dg');
        Schema::dropIfExists('kelompok_dg');
        Schema::dropIfExists('orang');
        $this->createBranchesTable();

        Schema::create('orang', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('full_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('batch_month')->nullable();
            $table->string('completed_at')->nullable();
            $table->string('journey_bridge_status')->default('belum');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->json('session_numbers')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });

        Schema::create('kelompok_dg', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('status')->default('active');
            $table->string('stage')->nullable();
            $table->unsignedBigInteger('parent_group_id')->nullable();
            $table->unsignedBigInteger('source_group_id')->nullable();
            $table->unsignedBigInteger('initiated_by_person_id')->nullable();
            $table->date('multiplied_at')->nullable();
            $table->text('notes')->nullable();
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

        Schema::create('dg_manual', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('person_id');
            $table->string('stage');
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
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

    private function seedPeopleTree(): void
    {
        $leaderId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Leader Test',
            'whatsapp' => '0811111111',
            'gender' => 'Laki-laki',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Test',
            'whatsapp' => '0822222222',
            'gender' => 'Perempuan',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('keanggotaan_kelompok_dg')->insert([
            'branch_id' => 1,
            'discipleship_group_id' => $groupId,
            'person_id' => $leaderId,
            'role' => 'leader',
            'status' => 'active',
            'started_on' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('keanggotaan_kelompok_dg')->insert([
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

    private function seedCrossBranchLeaderGroup(): void
    {
        $memberId = DB::table('orang')->insertGetId([
            'branch_id' => 1,
            'full_name' => 'Anggota Kutisari Lintas',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('orang')->insert([
            'id' => 626,
            'branch_id' => 2,
            'full_name' => 'Yakub Tri Handoko',
            'gender' => 'Laki-laki',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $groupId = DB::table('kelompok_dg')->insertGetId([
            'branch_id' => 1,
            'status' => 'active',
            'stage' => 'DG 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keanggotaan_kelompok_dg')->insert([
            [
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => 626,
                'role' => 'leader',
                'stage' => null,
                'status' => 'active',
                'started_on' => '2026-07-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'discipleship_group_id' => $groupId,
                'person_id' => $memberId,
                'role' => 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-07-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}


