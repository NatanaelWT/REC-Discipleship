<?php

namespace Tests\Feature;

use Tests\TestCase;

class JemaatFeaturesRemovedTest extends TestCase
{
    public function test_jemaat_routes_are_removed(): void
    {
        foreach ([
            '/jemaat/dashboard',
            '/jemaat/data',
            '/jemaat/kelengkapan',
            '/jemaat/keluarga',
            '/jemaat/ulang-tahun',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_legacy_jemaat_pages_no_longer_open_jemaat_features(): void
    {
        foreach ([
            'member_dashboard',
            'members',
            'member_completeness',
            'member_families',
            'member_birthdays',
        ] as $page) {
            $response = $this->get('/index.php?page=' . $page);

            $response->assertOk();
            $response->assertDontSee('Data Jemaat');
            $response->assertDontSee('Pendataan Jemaat');
            $response->assertDontSee('Kelengkapan Data Jemaat');
            $response->assertDontSee('List Keluarga Jemaat');
            $response->assertDontSee('Ulang Tahun Jemaat Bulanan');
        }
    }
}
