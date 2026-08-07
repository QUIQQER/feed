<?php

declare(strict_types=1);

namespace QUITests\Feed;

require_once __DIR__ . '/../fixtures/TestSiteFeedType.php';

use PHPUnit\Framework\TestCase;
use QUI\Feed\Feed;
use QUI\Projects\Project;
use QUI\Projects\Site;

class SiteSelectionTranslationTest extends TestCase
{
    public function testSiteAndParentSelectorsUseLinkedLanguageIds(): void
    {
        $SourceProject = $this->createMock(Project::class);
        $SourceProject->method('getName')->willReturn('example');
        $SourceProject->method('getLang')->willReturn('de');
        $SourceProject->method('get')->willReturnCallback(
            fn(int $siteId): Site => $this->createLinkedSite($siteId * 10)
        );

        $TargetProject = $this->createMock(Project::class);
        $TargetProject->method('getName')->willReturn('example');
        $TargetProject->method('getLang')->willReturn('en');

        $Feed = $this->createMock(Feed::class);
        $Feed->method('getProject')->willReturn($SourceProject);

        self::assertSame(
            [1, 20, 'p30', 'news'],
            (new TestSiteFeedType())->translateSelection(
                $Feed,
                $TargetProject,
                [1, 2, 'p3', 'news']
            )
        );
    }

    public function testMissingTranslationsAreExcludedFromSelection(): void
    {
        $Site = $this->createMock(Site::class);
        $Site->method('getLangIds')->willReturn(['en' => false]);

        $SourceProject = $this->createMock(Project::class);
        $SourceProject->method('getName')->willReturn('example');
        $SourceProject->method('getLang')->willReturn('de');
        $SourceProject->method('get')->willReturn($Site);

        $TargetProject = $this->createMock(Project::class);
        $TargetProject->method('getName')->willReturn('example');
        $TargetProject->method('getLang')->willReturn('en');

        $Feed = $this->createMock(Feed::class);
        $Feed->method('getProject')->willReturn($SourceProject);

        self::assertSame(
            ['news'],
            (new TestSiteFeedType())->translateSelection(
                $Feed,
                $TargetProject,
                [2, 'p3', 'news']
            )
        );
    }

    private function createLinkedSite(int $englishSiteId): Site
    {
        $Site = $this->createMock(Site::class);
        $Site->method('getLangIds')->willReturn(['en' => $englishSiteId]);

        return $Site;
    }
}
