<?php

namespace Tests\Feature;

use Tests\TestCase;

class MainDashboardRemovedTest extends TestCase
{
    public function test_main_dashboard_route_is_removed(): void
    {
        $this->get('/dashboard')->assertNotFound();
    }

    public function test_legacy_dashboard_page_no_longer_opens_main_dashboard_or_account_access(): void
    {
        $response = $this->get('/index.php?page=dashboard');

        $response->assertOk();
        $response->assertDontSee('Dashboard Utama');
        $response->assertDontSee('Akun Tersedia');
    }
}
