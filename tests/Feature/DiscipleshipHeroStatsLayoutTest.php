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
            '/\.discipleship-page-header__stats\s*\{[^}]*grid-auto-rows:\s*72px;[^}]*max-width:\s*420px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.discipleship-page-header__stat\s*\{[^}]*height:\s*72px;[^}]*min-height:\s*72px;[^}]*max-height:\s*72px;/s',
            $css,
        );
    }

    public function test_five_discipleship_pages_use_the_shared_header_partial(): void
    {
        $bladeIncludePages = [
            resource_path('views/discipleship/groups/index.blade.php'),
            resource_path('views/discipleship/people-list/index.blade.php'),
        ];
        $renderedPartialPages = [
            resource_path('views/discipleship/spiritual-journey/index.blade.php'),
            resource_path('views/discipleship/meeting-reports/recap.blade.php'),
            resource_path('views/discipleship/msk-participants/index.blade.php'),
        ];

        foreach ($bladeIncludePages as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertStringContainsString("@include('discipleship.partials.page-header'", $source);
        }

        foreach ($renderedPartialPages as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertStringContainsString("view('discipleship.partials.page-header'", $source);
        }

        $allSources = implode("\n", array_map(
            static fn (string $path): string => (string) file_get_contents($path),
            [...$bladeIncludePages, ...$renderedPartialPages],
        ));
        $this->assertStringNotContainsString('groups-hero-card', $allSources);
        $this->assertStringNotContainsString('people-hero-card', $allSources);
        $this->assertStringNotContainsString('journey-hero-card', $allSources);
        $this->assertStringNotContainsString('dg-recap-hero-card', $allSources);
        $this->assertStringNotContainsString('msk-hero-card', $allSources);
    }
}
