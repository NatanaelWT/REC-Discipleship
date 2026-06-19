<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_public_portal_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Website Manajemen Pemuridan REC Indonesia');
        $response->assertDontSee('?page=', false);
        $response->assertSee('/publik/jurnal-dg', false);
        $response->assertSee('/publik/umpan-balik-anggota', false);
        $response->assertSee('/publik/pertanyaan-sulit/kirim', false);
        $response->assertSee('/publik/pertanyaan-sulit/jawaban', false);
    }

    public function test_legacy_page_query_is_rejected(): void
    {
        $response = $this->get('/?page=public_materials&menu=materi_dg_1');

        $response->assertNotFound();
    }

    public function test_index_php_without_page_query_redirects_home(): void
    {
        $response = $this->get('/index.php');

        $response->assertRedirect('/');
    }

    public function test_public_empty_menu_renders_from_laravel_view(): void
    {
        $response = $this->get('/publik/menu-kosong?menu=materi_dg_1');

        $response->assertOk();
        $response->assertSee('Materi DG-1 (BePI)');
        $response->assertSee('Halaman ini masih kosong dan akan diisi berikutnya.');
        $response->assertSee('href="/"', false);
        $response->assertDontSee('?page=', false);
    }

    public function test_legacy_public_empty_menu_query_is_rejected(): void
    {
        $response = $this->get('/index.php?page=public_menu_empty&menu=materi_dg_1');

        $response->assertNotFound();
    }
}
