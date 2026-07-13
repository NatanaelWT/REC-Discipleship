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
}
