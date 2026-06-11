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
    }

    public function test_legacy_page_query_redirects_to_clean_laravel_route(): void
    {
        $response = $this->get('/?page=public_materials&menu=materi_dg_1');

        $response->assertRedirect('/materi?menu=materi_dg_1');
    }
}
