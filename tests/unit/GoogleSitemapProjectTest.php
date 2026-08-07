<?php

declare(strict_types=1);

namespace QUITests\Feed;

require_once __DIR__ . '/../fixtures/TestGoogleSitemapFeed.php';

use QUI\Feed\Feed;
use QUI\Projects\Project;

class GoogleSitemapProjectTest extends \PHPUnit\Framework\TestCase
{
    public function testPathLanguagesOnSameVhostAreIncluded(): void
    {
        $RootProject = $this->createProject('de', 'www.example.test', '');
        $EnglishProject = $this->createProject('en', 'www.example.test', 'en');
        $SpanishProject = $this->createProject('es', 'www.example.es', '');
        $Feed = $this->createFeed($RootProject, true);
        $FeedType = (new TestGoogleSitemapFeed())->setTestProjects(
            ['de', 'en', 'es'],
            [
                'en' => $EnglishProject,
                'es' => $SpanishProject
            ]
        );

        self::assertSame(
            [$RootProject, $EnglishProject],
            $FeedType->getFeedProjects($Feed)
        );
        self::assertTrue($FeedType->includesProjectLanguage($Feed, $EnglishProject));
        self::assertFalse($FeedType->includesProjectLanguage($Feed, $SpanishProject));
    }

    public function testPathLanguagesRemainDisabledByDefault(): void
    {
        $RootProject = $this->createProject('de', 'www.example.test', '');
        $EnglishProject = $this->createProject('en', 'www.example.test', 'en');
        $Feed = $this->createFeed($RootProject, false);
        $FeedType = (new TestGoogleSitemapFeed())->setTestProjects(
            ['de', 'en'],
            ['en' => $EnglishProject]
        );

        self::assertSame([$RootProject], $FeedType->getFeedProjects($Feed));
        self::assertFalse($FeedType->includesProjectLanguage($Feed, $EnglishProject));
    }

    public function testPathLanguageFeedDoesNotAggregateSiblingLanguages(): void
    {
        $EnglishProject = $this->createProject('en', 'www.example.test', 'en');
        $SpanishProject = $this->createProject('es', 'www.example.test', 'es');
        $Feed = $this->createFeed($EnglishProject, true);
        $FeedType = (new TestGoogleSitemapFeed())->setTestProjects(
            ['en', 'es'],
            ['es' => $SpanishProject]
        );

        self::assertSame([$EnglishProject], $FeedType->getFeedProjects($Feed));
    }

    private function createProject(string $language, string $host, string $path): Project
    {
        $Project = $this->createMock(Project::class);
        $Project->method('getName')->willReturn('example');
        $Project->method('getLang')->willReturn($language);
        $Project->method('getVHostRoute')->willReturn([
            'host' => $host,
            'httpshost' => '',
            'path' => $path,
            'project' => 'example',
            'lang' => $language
        ]);

        return $Project;
    }

    private function createFeed(Project $Project, bool $includePathLanguages): Feed
    {
        $Feed = $this->createMock(Feed::class);
        $Feed->method('getProject')->willReturn($Project);
        $Feed->method('getAttribute')->willReturnCallback(
            static fn(string $attribute): mixed => $attribute === 'includeVhostPathLanguages'
                ? $includePathLanguages
                : null
        );

        return $Feed;
    }
}
