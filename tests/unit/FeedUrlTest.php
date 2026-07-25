<?php

declare(strict_types=1);

namespace QUITests\Feed;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\Feed\Feed;
use QUI\Feed\Handler\CSV\Feed as CsvFeed;
use QUI\Feed\Handler\GoogleSitemap\Feed as SitemapFeed;
use QUI\Feed\Interfaces\FeedTypeInterface;
use QUI\Projects\Project;
use ReflectionClass;
use ReflectionProperty;

class FeedUrlTest extends TestCase
{
    /**
     * @return array<string, array{FeedTypeInterface, string}>
     */
    public static function feedTypeProvider(): array
    {
        return [
            'XML feed' => [new SitemapFeed(['mimeType' => 'application/xml']), 'xml'],
            'CSV feed' => [new CsvFeed(['mimeType' => 'text/csv']), 'csv']
        ];
    }

    #[DataProvider('feedTypeProvider')]
    public function testUrlContainsSystemBasePath(FeedTypeInterface $FeedType, string $extension): void
    {
        $Project = $this->createMock(Project::class);
        $Project->method('getVHost')
            ->with(true, true)
            ->willReturn('https://example.test');

        $Feed = (new ReflectionClass(Feed::class))->newInstanceWithoutConstructor();

        (new ReflectionProperty(Feed::class, 'feedId'))->setValue($Feed, 2);
        (new ReflectionProperty(Feed::class, 'Project'))->setValue($Feed, $Project);
        (new ReflectionProperty(Feed::class, 'FeedType'))->setValue($Feed, $FeedType);

        self::assertSame(
            'https://example.test' . URL_DIR . 'feed=2.' . $extension,
            $Feed->getUrl()
        );
    }
}
