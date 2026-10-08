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
use QUI\Cache\LongTermCache;
use QUI\Feed\Handler\GoogleSitemap\Feed as SitemapFeed;
use QUI\Feed\Utils\Utils;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use ReflectionMethod;
use ReflectionProperty;

class FeedDatabaseTest extends TestCase
{
    private Connection $originalConnection;
    private Connection $connection;
    private string $table;
    private ?Pool $originalPool;
    private array $originalCacheRuntime;
    private array $originalProjects;

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

        $pool = new ReflectionProperty(LongTermCache::class, 'Pool');
        $runtime = new ReflectionProperty(LongTermCache::class, 'runtime');
        $this->originalPool = $pool->getValue();
        $this->originalCacheRuntime = $runtime->getValue();
        $pool->setValue(null, new Pool(new Ephemeral()));
        $runtime->setValue(null, []);

        $projects = new ReflectionProperty(QUI\Projects\Manager::class, 'projects');
        $this->originalProjects = $projects->getValue();
        $Project = $this->createMock(QUI\Projects\Project::class);
        $Project->method('getVHost')->willReturn('https://example.test');
        $projects->setValue(null, ['phpunit-feed-valid' => ['de' => $Project]]);

        LongTermCache::set(Utils::getFeedTypeCachePath(), [[
            'id' => 'phpunit-feed-type',
            'class' => SitemapFeed::class,
            'title' => 'Test sitemap',
            'mimeType' => 'application/xml'
        ]]);

        $this->setConnection($this->connection);
        $this->insertFeed(1, 'project-a', '{"directOutput":false}');
        $this->insertFeed(2, 'project-b', '{"directOutput":true}');
        $this->insertFeed(3, 'project-c', 'invalid json');
    }

    protected function tearDown(): void
    {
        $this->setConnection($this->originalConnection);
        (new ReflectionProperty(LongTermCache::class, 'Pool'))->setValue(null, $this->originalPool);
        (new ReflectionProperty(LongTermCache::class, 'runtime'))->setValue(null, $this->originalCacheRuntime);
        (new ReflectionProperty(QUI\Projects\Manager::class, 'projects'))->setValue(null, $this->originalProjects);

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

    public function testDeleteOrphanRemovesOnlyItsRowAndAllItsCachedPages(): void
    {
        LongTermCache::set('quiqqer/feed/1', 'orphan');
        LongTermCache::set('quiqqer/feed/1/2', 'orphan page');
        LongTermCache::set('quiqqer/feed/10', 'other feed');

        $Manager = new Manager();
        $Manager->deleteFeed(1);

        self::assertSame([2, 3], array_map('intval', array_column($Manager->getList(), 'id')));
        self::assertSame('other feed', LongTermCache::get('quiqqer/feed/10'));
        $this->assertCacheMissing('quiqqer/feed/1');
        $this->assertCacheMissing('quiqqer/feed/1/2');
    }

    public function testDeletingUnknownFeedDoesNotReportSuccess(): void
    {
        $this->expectException(QUI\Exception::class);
        (new Manager())->deleteFeed(999);
    }

    public function testDatabaseFailureIsNotSwallowedByDelete(): void
    {
        $this->connection->createSchemaManager()->dropTable($this->table);
        $this->expectException(\Doctrine\DBAL\Exception::class);
        (new Manager())->deleteFeed(1);
    }

    public function testProjectHookDeletesAllLanguagesButPreservesOtherProjects(): void
    {
        $this->insertFeed(4, 'project-a', '{}');
        $this->connection->update($this->table, ['lang' => 'en'], ['id' => 4]);
        $this->insertFeed(5, 'project-a-other', '{}');
        LongTermCache::set('quiqqer/feed/1', 'de');
        LongTermCache::set('quiqqer/feed/4/2', 'en page');
        LongTermCache::set('quiqqer/feed/5', 'unrelated');

        $events = simplexml_load_file(dirname(__DIR__, 2) . '/events.xml');
        $handler = (string)$events->xpath('//event[@on="onDeleteProject"]')[0]['fire'];
        self::assertSame('\\QUI\\Feed\\EventHandler::onDeleteProject', $handler);
        $handler('project-a');
        $handler('project-a'); // Repeated cleanup is harmless.

        self::assertSame([2, 3, 5], array_map('intval', array_column((new Manager())->getList(), 'id')));
        self::assertSame('unrelated', LongTermCache::get('quiqqer/feed/5'));
        $this->assertCacheMissing('quiqqer/feed/1');
        $this->assertCacheMissing('quiqqer/feed/4/2');
    }

    public function testProjectNameIsBoundAsAnExactValue(): void
    {
        (new Manager())->deleteProjectFeeds("project-a' OR 1=1 --");
        self::assertSame(3, (new Manager())->count());
    }

    public function testBackendListRetainsOrphansAndHealthyFeedWithPagination(): void
    {
        $this->connection->update($this->table, ['project' => 'phpunit-feed-valid'], ['id' => 2]);
        $callables = new ReflectionProperty(QUI\Ajax::class, 'callables');
        $permissions = new ReflectionProperty(QUI\Ajax::class, 'permissions');
        $originalCallables = $callables->getValue();
        $originalPermissions = $permissions->getValue();

        try {
            require dirname(__DIR__, 2) . '/ajax/getList.php';
            $callback = $callables->getValue()['package_quiqqer_feed_ajax_getList']['callable'];
            $result = $callback(json_encode(['sortOn' => 'id', 'sortBy' => 'ASC']));

            self::assertCount(3, $result['data']);
            self::assertTrue($result['data'][0]['missingProject']);
            self::assertSame('project-a', $result['data'][0]['project']);
            self::assertSame('', $result['data'][0]['url']);
            self::assertFalse($result['data'][1]['missingProject']);
            self::assertSame('Test sitemap', $result['data'][1]['feedtype_title']);
            self::assertSame('https://example.test' . URL_DIR . 'feed=2.xml', $result['data'][1]['url']);
            self::assertSame(3, (new Manager())->count());

            $page = $callback(json_encode([
                'page' => 2,
                'perPage' => 1,
                'sortOn' => 'id',
                'sortBy' => 'ASC'
            ]));
            self::assertCount(1, $page['data']);
            self::assertSame(2, (int)$page['data'][0]['id']);
        } finally {
            $callables->setValue(null, $originalCallables);
            $permissions->setValue(null, $originalPermissions);
        }
    }

    public function testMcpListsAndDeletesOrphanWithoutLoadingProject(): void
    {
        $this->connection->update($this->table, ['project' => 'phpunit-feed-valid'], ['id' => 2]);
        $requestUser = new ReflectionProperty(QUI\AI\MCP\Server::class, 'RequestUser');
        $originalUser = $requestUser->getValue();
        $User = $this->createMock(QUI\Interfaces\Users\User::class);
        $User->method('isSU')->willReturn(true);
        $requestUser->setValue(null, $User);

        try {
            $Builder = new \Mcp\Server\Builder();
            (new QUI\Feed\MCP\Feeds\ListFeeds())->register($Builder);
            (new QUI\Feed\MCP\Feeds\DeleteFeed())->register($Builder);
            $tools = (new ReflectionProperty($Builder, 'tools'))->getValue($Builder);
            $list = $tools[0]['handler'];
            $delete = $tools[1]['handler'];

            $result = $list();
            self::assertIsArray($result);
            self::assertCount(3, $result['feeds']);
            self::assertTrue($result['feeds'][0]['missingProject']);
            self::assertSame('project-a', $result['feeds'][0]['project']);
            self::assertSame('', $result['feeds'][0]['url']);
            self::assertFalse($result['feeds'][0]['directOutput']);
            self::assertSame('Test sitemap', $result['feeds'][1]['typeTitle']);
            self::assertTrue($result['feeds'][1]['directOutput']);
            self::assertSame('https://example.test' . URL_DIR . 'feed=2.xml', $result['feeds'][1]['url']);
            self::assertCount(1, $list(project: 'project-a')['feeds']);
            self::assertSame(2, $list(limit: 1, offset: 1)['feeds'][0]['id']);

            self::assertSame(['deleted' => [1]], $delete([1]));
            self::assertSame(2, (new Manager())->count());
            self::assertCount(0, $list(project: 'project-a')['feeds']);
        } finally {
            $requestUser->setValue(null, $originalUser);
        }
    }

    private function assertCacheMissing(string $key): void
    {
        try {
            LongTermCache::get($key);
            self::fail('Deleted feed cache still exists: ' . $key);
        } catch (QUI\Cache\Exception) {
            self::assertTrue(true);
        }
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
