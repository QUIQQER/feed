<?php

declare(strict_types=1);

namespace QUITests\Feed;

require_once __DIR__ . '/../fixtures/TestSiteFeedType.php';

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Feed\Feed;
use QUI\Projects\Project;
use ReflectionProperty;

class SiteFeedQueryTest extends TestCase
{
    private Connection $originalConnection;
    private Connection $connection;
    private TestSiteFeedType $FeedType;
    private Project $Project;
    private Feed $Feed;

    /** @var array<string, mixed> */
    private array $feedAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = QUI::getDataBaseConnection();
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ]);
        $this->setConnection($this->connection);
        $this->createSiteTable();

        $this->Project = $this->createMock(Project::class);
        $this->Project->method('getAttribute')->willReturnCallback(
            static fn(string $name): string => $name === 'db_table' ? 'feed_sites_phpunit' : ''
        );

        $this->feedAttributes = [
            'feedlimit' => 10,
            'feedOrder' => 'editDate',
            'feedSearch' => 'needle',
            'feedSearchFieldTitle' => true,
            'feedSearchFieldShort' => false,
            'feedSearchFieldContent' => false
        ];
        $this->Feed = $this->createMock(Feed::class);
        $this->Feed->method('getProject')->willReturn($this->Project);
        $this->Feed->method('getAttribute')->willReturnCallback(
            fn(string $name): mixed => $this->feedAttributes[$name] ?? null
        );
        $this->FeedType = new TestSiteFeedType();
    }

    protected function tearDown(): void
    {
        $this->setConnection($this->originalConnection);

        parent::tearDown();
    }

    public function testAllSiteQueryAppliesStateSearchFieldAndEditOrder(): void
    {
        self::assertSame([3, 1], $this->FeedType->getAllIds($this->Feed));
    }

    public function testSelectedSiteQueryCombinesIdsAndTypesAndHonoursLimit(): void
    {
        $this->feedAttributes['feedSearch'] = '';
        $this->feedAttributes['feedOrder'] = '';
        $this->feedAttributes['feedlimit'] = 2;

        self::assertSame(
            [3, 2],
            $this->FeedType->getSelectedIds($this->Feed, [1, 'news'])
        );
        self::assertSame(
            [3, 2, 1],
            $this->FeedType->getSelectedIds($this->Feed, [1, 'news'], false)
        );
    }

    public function testSelectedSiteQueryReturnsNoSitesForEmptySelection(): void
    {
        self::assertSame([], $this->FeedType->getSelectedIds($this->Feed, []));
    }

    public function testZeroFeedLimitReturnsAllSites(): void
    {
        $this->feedAttributes['feedSearch'] = '';
        $this->feedAttributes['feedlimit'] = 0;

        for ($id = 6; $id <= 13; $id++) {
            $date = sprintf('2026-06-%02d', $id);
            $this->insertSite($id, true, false, 'article', 'additional site', $date, $date);
        }

        self::assertCount(11, $this->FeedType->getAllIds($this->Feed));
    }

    private function createSiteTable(): void
    {
        $Schema = new Schema();
        $Sites = $Schema->createTable('feed_sites_phpunit');
        $Sites->addColumn('id', 'integer');
        $Sites->addColumn('active', 'boolean');
        $Sites->addColumn('deleted', 'boolean');
        $Sites->addColumn('type', 'string');
        $Sites->addColumn('title', 'string');
        $Sites->addColumn('short', 'text');
        $Sites->addColumn('content', 'text');
        $Sites->addColumn('release_from', 'string');
        $Sites->addColumn('c_date', 'string');
        $Sites->addColumn('e_date', 'string');
        $Sites->setPrimaryKey(['id']);

        foreach ($Schema->toSql($this->connection->getDatabasePlatform()) as $statement) {
            $this->connection->executeStatement($statement);
        }

        $this->insertSite(1, true, false, 'article', 'needle first', '2026-01-01', '2026-01-01');
        $this->insertSite(2, true, false, 'news', 'other', '2026-02-01', '2026-03-01');
        $this->insertSite(3, true, false, 'news', 'needle latest', '2026-03-01', '2026-02-01');
        $this->insertSite(4, false, false, 'news', 'needle inactive', '2026-04-01', '2026-04-01');
        $this->insertSite(5, true, true, 'news', 'needle deleted', '2026-05-01', '2026-05-01');
    }

    private function insertSite(
        int $id,
        bool $active,
        bool $deleted,
        string $type,
        string $title,
        string $releaseDate,
        string $editDate
    ): void {
        $this->connection->insert('feed_sites_phpunit', [
            'id' => $id,
            'active' => (int)$active,
            'deleted' => (int)$deleted,
            'type' => $type,
            'title' => $title,
            'short' => 'short text',
            'content' => 'content text',
            'release_from' => $releaseDate,
            'c_date' => $releaseDate,
            'e_date' => $editDate
        ]);
    }

    private function setConnection(Connection $Connection): void
    {
        $QueryBuilder = new ReflectionProperty(QUI::class, 'QueryBuilder');
        $QueryBuilder->setValue(null, $Connection);
    }
}
