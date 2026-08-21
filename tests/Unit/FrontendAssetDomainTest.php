<?php

namespace Tests\Unit;

use App\Support\RuntimeBootstrap;
use Tests\TestCase;

class FrontendAssetDomainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RuntimeBootstrap::load();
    }

    public function test_each_route_family_resolves_to_only_its_asset_domain(): void
    {
        $this->assertSame('public', frontend_asset_domain('', 'page-public-menu-home'));
        $this->assertSame('public', frontend_asset_domain('', 'page-login'));
        $this->assertSame('discipleship', frontend_asset_domain('msk_classes', 'page-msk_classes'));
        $this->assertSame('discipleship', frontend_asset_domain('difficult_questions_admin', 'page-difficult-questions-admin'));
        $this->assertSame('developer', frontend_asset_domain('developer_dashboard', 'page-developer'));
        $this->assertSame('worship', frontend_asset_domain('worship_penatalayan'));
        $this->assertSame('core', frontend_asset_domain('settings', 'page-settings'));
        $this->assertSame('core', frontend_asset_domain('', 'page-file-preview-standalone'));
    }

    public function test_developer_and_worship_domains_include_shared_page_header_selectors(): void
    {
        $selectors = [
            '.discipleship-page-header {',
            '.discipleship-page-header__main {',
            '.discipleship-page-header__copy {',
            '.discipleship-page-header__kicker {',
            '.discipleship-page-header__stats {',
            '.discipleship-page-header__stat {',
            '.discipleship-page-header__stat-label',
            '.discipleship-page-header__stat-value',
        ];

        foreach (['developer', 'worship'] as $domain) {
            $css = file_get_contents(dirname(__DIR__, 2)."/resources/css/generated/{$domain}.css");

            $this->assertIsString($css);
            foreach ($selectors as $selector) {
                $this->assertStringContainsString($selector, $css);
            }
        }
    }
}
