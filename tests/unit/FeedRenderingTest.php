<?php

declare(strict_types=1);

namespace QUITests\Feed;

use PHPUnit\Framework\TestCase;
use QUI\Feed\FeedItemCollection;
use QUI\Feed\Handler\CSV\Channel;
use QUI\Feed\Handler\CSV\Feed as CsvFeed;
use QUI\Feed\Handler\GoogleSitemap\Feed as SitemapFeed;
use QUI\Feed\Utils\SimpleXML;
use ReflectionMethod;

class FeedRenderingTest extends TestCase
{
    public function testCollectionRejectsInvalidLinksAndAddsDefaults(): void
    {
        $Collection = new FeedItemCollection();
        $Collection->add(['title' => 'missing link']);
        $Collection->add(['link' => '   ']);
        $Collection->add([
            'title' => 'valid',
            'link' => 'https://example.test/item',
            'date' => 100
        ]);

        self::assertSame(1, $Collection->count());
        self::assertSame([
            'title' => 'valid',
            'link' => 'https://example.test/item',
            'date' => 100,
            'permalink' => 'https://example.test/item',
            'e_date' => 100
        ], $Collection->getItems()[0]);
    }

    public function testCollectionSortsAndDeduplicatesCanonicalLinks(): void
    {
        $Collection = new FeedItemCollection();
        $Collection->add([
            'title' => 'older duplicate',
            'link' => 'https://example.test/duplicate/',
            'date' => 100
        ]);
        $Collection->add([
            'title' => 'newest duplicate',
            'link' => 'https://example.test/duplicate',
            'date' => 300
        ]);
        $Collection->add([
            'title' => 'middle',
            'link' => 'https://example.test/middle',
            'date' => '200'
        ]);

        $Collection->sortByDate();
        $Collection->deduplicate();

        self::assertSame(2, $Collection->count());
        self::assertSame('newest duplicate', $Collection->getItems()[0]['title']);
        self::assertSame('middle', $Collection->getItems()[1]['title']);
    }

    public function testCollectionCreatesChannelItems(): void
    {
        $Collection = new FeedItemCollection();
        $Collection->add([
            'title' => 'Channel item',
            'link' => 'https://example.test/channel-item',
            'date' => 100
        ]);
        $Channel = new Channel();

        $Collection->addToChannel($Channel);

        self::assertCount(1, $Channel->getItems());
        self::assertSame('Channel item', $Channel->getItems()[0]->getAttribute('title'));
    }

    public function testCsvFeedEscapesValuesAndFormatsDates(): void
    {
        $Feed = new CsvFeed();
        $Channel = $Feed->createChannel();
        $Channel->setHost('https://example.test/');
        $Channel->createItem([
            'title' => 'Title, with comma',
            'description' => 'Description',
            'language' => 'en',
            'date' => 1_704_067_200,
            'e_date' => '1704067300',
            'link' => 'https://example.test/item',
            'permalink' => 'https://example.test/item',
            'author' => true
        ]);

        $rows = $this->parseCsv($Feed->getCSV());

        self::assertSame([
            'title',
            'description',
            'language',
            'date',
            'editDate',
            'link',
            'permalink',
            'author',
            'image'
        ], $rows[0]);
        self::assertSame('Title, with comma', $rows[1][0]);
        self::assertSame(date(DATE_ATOM, 1_704_067_200), $rows[1][3]);
        self::assertSame(date(DATE_ATOM, 1_704_067_300), $rows[1][4]);
        self::assertSame('1', $rows[1][7]);
        self::assertSame('', $rows[1][8]);
    }

    public function testSimpleXmlAddsCdataWithoutEscapingContent(): void
    {
        $Xml = new SimpleXML('<root><value/></root>');
        $Xml->value->addCData('A < B & C');

        self::assertStringContainsString('<![CDATA[A < B & C]]>', (string)$Xml->asXML());
    }

    public function testSitemapIndexAddsPageSuffixBeforeXmlExtension(): void
    {
        $Feed = new SitemapFeed();
        $Channel = $Feed->createChannel();
        $Channel->setAttribute('link', 'https://example.test/feed=12.xml');
        $Channel->createItem([
            'link' => 'https://example.test/item',
            'e_date' => 1_704_067_200
        ]);

        (new ReflectionMethod($Feed, 'setPageSize'))->invoke($Feed, 1);
        (new ReflectionMethod($Feed, 'setPage'))->invoke($Feed, 0);

        $xml = (string)$Feed->getXML()->asXML();

        self::assertStringContainsString(
            '<loc>https://example.test/feed=12-1.xml</loc>',
            $xml
        );
        self::assertStringNotContainsString('feed=12.xml-1.xml', $xml);
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseCsv(string $csv): array
    {
        $Handle = fopen('php://temp', 'r+');
        self::assertIsResource($Handle);
        fwrite($Handle, $csv);
        rewind($Handle);
        $rows = [];

        while (($row = fgetcsv($Handle, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        fclose($Handle);

        return $rows;
    }
}
