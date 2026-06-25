<?php

namespace Tests\Feature;

use Tests\TestCase;

class DiscipleshipHeroStatsLayoutTest extends TestCase
{
    public function test_shared_discipleship_stat_cards_use_fixed_dimensions(): void
    {
        $css = file_get_contents(public_path('assets/style.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.discipleship-hero-stats\s*\{[^}]*grid-auto-rows:\s*72px;[^}]*max-width:\s*420px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.discipleship-hero-stat\s*\{[^}]*height:\s*72px;[^}]*min-height:\s*72px;[^}]*max-height:\s*72px;/s',
            $css,
        );
    }
}
