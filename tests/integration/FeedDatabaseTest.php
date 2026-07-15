<?php

declare(strict_types=1);

namespace QUITests\Feed;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Feed\EventHandler;
use QUI\Feed\Manager;
use ReflectionMethod;
use ReflectionProperty;

class FeedDatabaseTest extends TestCase
{
    private Connection $originalConnection;
    private Connection $connection;
    private string $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = QUI::getDataBaseConnection();
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ]);
        $this->table = QUI::getDBTableName(Manager::TABLE);

        $Schema = new Schema();
        $Feeds = $Schema->createTable($this->table);
        $Feeds->addColumn('id', 'integer', ['autoincrement' => true]);
        $Feeds->addColumn('project', 'string', ['default' => '']);
        $Feeds->addColumn('lang', 'string', ['length' => 2, 'default' => '']);
        $Feeds->addColumn('feed_settings', 'text', ['notnull' => false]);
        $Feeds->addColumn('type_id', 'string', ['notnull' => false]);
        $Feeds->setPrimaryKey(['id']);

        foreach ($Schema->toSql($this->connection->getDatabasePlatform()) as $statement) {
            $this->connection->executeStatement($statement);
        }

        $this->setConnection($this->connection);
        $this->insertFeed(1, 'project-a', '{"directOutput":false}');
        $this->insertFeed(2, 'project-b', '{"directOutput":true}');
        $this->insertFeed(3, 'project-c', 'invalid json');
    }

    protected function tearDown(): void
    {
        $this->setConnection($this->originalConnection);

        parent::tearDown();
    }

    public function testManagerListsSortsPaginatesAndCountsFeeds(): void
    {
        $Manager = new Manager();

        self::assertCount(3, $Manager->getList());
        self::assertSame(3, $Manager->count());

        $page = $Manager->getList([
            'page' => 2,
            'perPage' => 1,
            'sortOn' => 'id',
            'sortBy' => 'DESC'
        ]);

        self::assertCount(1, $page);
        self::assertSame(2, (int)$page[0]['id']);
    }

    public function testSetupPatchRepairsInvalidFeedSettings(): void
    {
        $patchV2 = new ReflectionMethod(EventHandler::class, 'patchV2');
        $patchV2->invoke(null);

        $settings = $this->connection->createQueryBuilder()
            ->select('feed_settings')
            ->from($this->table)
            ->where('id = :id')
            ->setParameter('id', 3)
            ->executeQuery()
            ->fetchOne();

        self::assertSame(['directOutput' => true], json_decode((string)$settings, true));
    }

    private function insertFeed(int $id, string $project, string $settings): void
    {
        $this->connection->insert($this->table, [
            'id' => $id,
            'project' => $project,
            'lang' => 'de',
            'feed_settings' => $settings,
            'type_id' => 'phpunit-feed-type'
        ]);
    }

    private function setConnection(Connection $Connection): void
    {
        $QueryBuilder = new ReflectionProperty(QUI::class, 'QueryBuilder');
        $QueryBuilder->setValue(null, $Connection);
    }
}
