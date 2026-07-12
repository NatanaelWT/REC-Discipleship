<?php

namespace Tests\Concerns;

use DOMDocument;
use DOMElement;
use DOMXPath;

trait AssertsDiscipleshipWorkspace
{
    protected function assertDiscipleshipWorkspace(string $html, string $activeTab): void
    {
        $xpath = $this->discipleshipMarkupXpath($html);
        $workspace = '//*[@data-discipleship-workspace]';

        $this->assertSame(1.0, $xpath->evaluate('count('.$workspace.')'));
        $this->assertSame(1.0, $xpath->evaluate('count(//*[@id="app-sidebar"])'));
        $this->assertSame(1.0, $xpath->evaluate('count(//*[contains(concat(" ", normalize-space(@class), " "), " app-shell ")])'));
        $this->assertSame(0.0, $xpath->evaluate('count('.$workspace.'//header[contains(concat(" ", normalize-space(@class), " "), " discipleship-workspace__header ")])'));

        $tabs = $xpath->query($workspace.'//*[@data-discipleship-tabs]/*[@data-discipleship-tab]');
        $this->assertNotFalse($tabs);
        $this->assertCount(4, $tabs);

        $expectedTabs = [
            'dashboard' => ['label' => 'Dashboard', 'path' => '/pemuridan/dashboard'],
            'people' => ['label' => 'Anggota DG', 'path' => '/pemuridan/anggota'],
            'groups' => ['label' => 'Kelompok DG', 'path' => '/pemuridan/kelompok'],
            'tree' => ['label' => 'Pohon Pemuridan', 'path' => '/pemuridan/pohon'],
        ];

        foreach (array_values($expectedTabs) as $index => $expected) {
            $tab = $tabs->item($index);
            $this->assertInstanceOf(DOMElement::class, $tab);
            $this->assertSame($expected['label'], $this->normalizedNodeText($tab));
            $this->assertSame($expected['path'], parse_url($tab->getAttribute('href'), PHP_URL_PATH));
        }

        foreach ($expectedTabs as $key => $expected) {
            $tab = $xpath->query($workspace.'//*[@data-discipleship-tab and @data-tab-key="'.$key.'"]')?->item(0);
            $this->assertInstanceOf(DOMElement::class, $tab);
            $isActive = $key === $activeTab;
            $this->assertSame('tab', $tab->getAttribute('role'));
            $this->assertSame($isActive ? 'true' : 'false', $tab->getAttribute('aria-selected'));
            $this->assertSame($isActive ? '0' : '-1', $tab->getAttribute('tabindex'));
            $this->assertSame('discipleship-tabpanel-'.$key, $tab->getAttribute('aria-controls'));
            $this->assertSame($isActive ? 'page' : '', $tab->getAttribute('aria-current'));
        }

        $panels = $xpath->query($workspace.'//*[@data-discipleship-tab-panel]');
        $this->assertNotFalse($panels);
        $this->assertCount(1, $panels);
        $panel = $panels->item(0);
        $this->assertInstanceOf(DOMElement::class, $panel);
        $this->assertSame($activeTab, $panel->getAttribute('data-tab-key'));
        $this->assertSame('tabpanel', $panel->getAttribute('role'));
        $this->assertSame('discipleship-tab-'.$activeTab, $panel->getAttribute('aria-labelledby'));
        $this->assertNotSame('', trim($panel->getAttribute('data-page-title')));
    }

    protected function assertDiscipleshipTabFragment(string $html, string $tabKey): void
    {
        $xpath = $this->discipleshipMarkupXpath($html);
        $panels = $xpath->query('//*[@data-discipleship-tab-panel]');

        $this->assertNotFalse($panels);
        $this->assertCount(1, $panels);
        $panel = $panels->item(0);
        $this->assertInstanceOf(DOMElement::class, $panel);
        $this->assertSame($tabKey, $panel->getAttribute('data-tab-key'));
        $this->assertSame('tabpanel', $panel->getAttribute('role'));
        $this->assertSame('discipleship-tab-'.$tabKey, $panel->getAttribute('aria-labelledby'));
        $this->assertNotSame('', trim($panel->getAttribute('data-page-title')));

        $this->assertSame(0.0, $xpath->evaluate('count(//*[@data-discipleship-workspace])'));
        $this->assertSame(0.0, $xpath->evaluate('count(//*[@data-discipleship-tabs])'));
        $this->assertSame(0.0, $xpath->evaluate('count(//*[@id="app-sidebar"])'));
        $this->assertSame(0.0, $xpath->evaluate('count(//*[contains(concat(" ", normalize-space(@class), " "), " app-shell ")])'));
        $this->assertStringNotContainsString('<!doctype', strtolower($html));
    }

    protected function assertUnifiedDiscipleshipSidebar(string $html, string $expectedBranchLabel = 'Kutisari'): void
    {
        $xpath = $this->discipleshipMarkupXpath($html);
        $sidebar = '//*[@id="app-sidebar"]//nav[contains(concat(" ", normalize-space(@class), " "), " sidebar-nav ")]';
        $branchNav = $sidebar.'//*[@data-discipleship-branch-nav]';
        $branchGroup = $branchNav.'/details[summary[starts-with(normalize-space(.), "'.htmlspecialchars($expectedBranchLabel, ENT_QUOTES).'")]]';

        $this->assertSame(1.0, $xpath->evaluate('count('.$branchNav.')'));
        $this->assertSame(1.0, $xpath->evaluate('count('.$branchGroup.')'));
        $this->assertSame(0.0, $xpath->evaluate('count('.$sidebar.'/details[summary[starts-with(normalize-space(.), "Pemuridan")]])'));
        $this->assertSame(0.0, $xpath->evaluate('count('.$sidebar.'/details[summary[starts-with(normalize-space(.), "Cabang")]])'));
        $dashboardLink = $xpath->query($branchGroup.'/div[contains(concat(" ", normalize-space(@class), " "), " nav-sub ")]/a[normalize-space(.) = "Dashboard"]')?->item(0);

        $this->assertInstanceOf(DOMElement::class, $dashboardLink);
        $this->assertSame('/pemuridan/dashboard', parse_url($dashboardLink->getAttribute('href'), PHP_URL_PATH));
        $this->assertStringContainsString(' active ', ' '.$dashboardLink->getAttribute('class').' ');
        $this->assertSame(0.0, $xpath->evaluate('count('.$branchGroup.'//a[normalize-space(.) = "Anggota DG" or normalize-space(.) = "Kelompok DG" or normalize-space(.) = "Pohon Pemuridan"])'));
    }

    protected function discipleshipMarkupXpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previousErrorHandling = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorHandling);

        $this->assertTrue($loaded);

        return new DOMXPath($document);
    }

    private function normalizedNodeText(DOMElement $node): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $node->textContent));
    }
}
