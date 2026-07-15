<?php

namespace QUI\Feed\Handler;

use DOMDocument;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use QUI;
use QUI\Exception;
use QUI\Feed\Feed;
use QUI\Feed\Feed as FeedInstance;
use QUI\Feed\FeedItemCollection;
use QUI\Feed\Interfaces\ChannelInterface;
use QUI\Feed\Utils\SimpleXML;
use QUI\Utils\Doctrine;

use function array_diff;
use function array_filter;
use function array_map;
use function array_merge;
use function array_pad;
use function array_unique;
use function ceil;
use function explode;
use function in_array;
use function is_numeric;
use function is_string;
use function ltrim;
use function preg_match;
use function rtrim;
use function strtotime;
use function strtoupper;
use function substr;
use function time;
use function trim;

/**
 * Class AbstractSiteFeedType
 * Abstract class for feed types that publish CMS Site feeds.
 */
abstract class AbstractSiteFeedType extends AbstractFeedType
{
    /**
     * Return the Feed as an XML string
     *
     * @param FeedInstance $Feed - The Feed that shall be created
     * @param int|null $page (optional) - Get a specific page of the feed (only required if feed is paginated)
     * @return string - Feed as XML string
     * @throws QUI\Exception
     */
    public function create(FeedInstance $Feed, ?int $page = null): string
    {
        $Project = $Feed->getProject();
        $projectHost = $Project->getVHost(true, true);
        $feedUrl = $projectHost . URL_DIR . 'feed=' . $Feed->getId() . '.xml';

        $Channel = $this->createChannel();
        $Channel->setLanguage($Project->getLang());
        $Channel->setHost($projectHost . URL_DIR);

        $Channel->setAttribute('link', $feedUrl);
        $Channel->setAttribute('description', $Feed->getAttribute('feedDescription'));
        $Channel->setAttribute('title', $Feed->getAttribute('feedName'));
        $Channel->setDate(time());

        $this->addItemsToChannel($Feed, $Channel);

        // Create the XML
        $XML = $this->getXML();

        $Dom = new DOMDocument('1.0', 'UTF-8');
        $Dom->preserveWhiteSpace = false;
        $Dom->formatOutput = true;
        $xmlString = $XML->asXML();

        if ($xmlString === false) {
            throw new QUI\Exception('Could not serialize feed XML.');
        }

        $Dom->loadXML($xmlString);

        $output = $Dom->saveXML();

        if ($output === false) {
            throw new QUI\Exception('Could not format feed XML.');
        }

        return $output;
    }

    /**
     * @return SimpleXML
     */
    abstract public function getXML(): SimpleXML;

    /**
     * Add all relevant items to a feed channel.
     *
     * @param FeedInstance $Feed
     * @param ChannelInterface $Channel
     * @return void
     * @throws QUI\Exception
     */
    protected function addItemsToChannel(FeedInstance $Feed, ChannelInterface $Channel): void
    {
        $this->collectFeedItems($Feed)->addToChannel($Channel);
    }

    /**
     * Collect all feed items for a feed.
     *
     * Modules can add entries through the "quiqqerFeedCollectItems" event.
     * Event signature: (Feed $Feed, FeedTypeInterface $FeedType, FeedItemCollection $Collection)
     *
     * @param FeedInstance $Feed
     * @return FeedItemCollection
     * @throws QUI\Exception
     */
    protected function collectFeedItems(FeedInstance $Feed): FeedItemCollection
    {
        $Collection = new FeedItemCollection();

        $this->collectSiteFeedItems($Feed, $Collection);
        $this->collectFeedTypeItems($Feed, $Collection);

        QUI::getEvents()->fireEvent('quiqqerFeedCollectItems', [
            $Feed,
            $this,
            $Collection
        ]);

        $Collection->sortByDate();
        $Collection->deduplicate();

        return $Collection;
    }

    /**
     * Collect feed-type-specific entries.
     *
     * @param FeedInstance $Feed
     * @param FeedItemCollection $Collection
     * @return void
     */
    protected function collectFeedTypeItems(FeedInstance $Feed, FeedItemCollection $Collection): void
    {
    }

    /**
     * Add all relevant CMS site entries to a feed item collection.
     *
     * @param FeedInstance $Feed
     * @param FeedItemCollection $Collection
     * @return void
     * @throws QUI\Exception
     */
    protected function collectSiteFeedItems(FeedInstance $Feed, FeedItemCollection $Collection): void
    {
        $Project = $Feed->getProject();
        $projectHost = $Project->getVHost(true, true);

        if (!is_string($projectHost)) {
            $projectHost = '';
        }

        $ids = $this->getSiteIds($Feed);

        foreach ($ids as $id) {
            try {
                $Site = $Project->get($id);
                $date = $Site->getAttribute('release_from');

                if ($date == '0000-00-00 00:00:00') {
                    $date = $Site->getAttribute('c_date');
                }

                $editDate = $Site->getAttribute('e_date');

                // Workaround bug  $Site->getCanonical() come with protocol
                $link = $Site->getId() === 1
                    ? rtrim($projectHost, '/') . '/'
                    : (string)$Site->getUrlRewritten();
                $permalink = $Site->getCanonical();

                if (!str_contains($link, 'https:') && !str_contains($link, 'http:')) {
                    $link = rtrim($projectHost, '/') . '/' . ltrim($link, '/');
                }

                if (!str_contains($permalink, 'https:') && !str_contains($permalink, 'http:')) {
                    $permalink = $projectHost . $Site->getCanonical();
                }

                $item = [
                    'title' => $Site->getAttribute('title'),
                    'description' => $Site->getAttribute('short'),
                    'language' => $Project->getLang(),
                    'date' => strtotime($date),
                    'e_date' => strtotime($editDate),
                    'link' => $link,
                    'permalink' => $permalink,
                    'seoDirective' => $Site->getAttribute('quiqqer.meta.site.robots')
                ];

                $Config = QUI::getPackage("quiqqer/feed")->getConfig();

                try {
                    //Check if the creation user should be picked
                    if ($Config?->get("common", "user") != "c_user") {
                        throw new QUI\Exception("Invalid user field choice!");
                    }

                    $User = QUI::getUsers()->get($Site->getAttribute("c_user"));
                    $item['author'] = $User->getName();
                } catch (Exception) {
                    $item['author'] = $Config?->get("common", "author");
                }

                $image = $Site->getAttribute('image_site');

                try {
                    if ($image) {
                        $item['image'] = QUI\Projects\Media\Utils::getImageByUrl($image);
                    }
                } catch (QUI\Exception) {
                }

                $Collection->add($item);
            } catch (QUI\Exception) {
            }
        }
    }

    /**
     * Returns the number of pages of this feed.
     *
     * @param Feed $Feed
     * @return int - Returns the number of pages or 0 if nor pages are used
     * @throws QUI\Exception
     */
    public function getPageCount(Feed $Feed): int
    {
        if (!$Feed->getAttribute("pageSize")) {
            return 0;
        }

        $pageSize = $Feed->getAttribute("pageSize");
        $totalItems = $this->getTotalItemCount($Feed);

        return (int)ceil($totalItems / $pageSize);
    }

    /**
     * Gets the site ids which should be used for the feed
     *
     * @param FeedInstance $Feed - Get Site IDs from specific feed
     * @return array<int, int>
     * @throws QUI\Exception
     */
    protected function getSiteIds(FeedInstance $Feed): array
    {
        $feedSites = $Feed->getAttribute('feedsites');
        $feedSitesExclude = $Feed->getAttribute('feedsites_exclude');

        if (empty($feedSites)) {
            $feedSites = [];
        } else {
            $feedSites = explode(';', $feedSites);
            $feedSites = array_filter($feedSites, function ($siteId) {
                return !empty($siteId);
            });
        }

        if (empty($feedSitesExclude)) {
            $feedSitesExclude = [];
        } else {
            $feedSitesExclude = explode(';', $feedSitesExclude);
            $feedSitesExclude = array_filter($feedSitesExclude, function ($siteId) {
                return !empty($siteId);
            });
        }

        // Some site types are always excluded!
        $feedSitesExclude[] = 'quiqqer/sitetypes:types/forwarding';

        // All sites, if no sites were selected.
        if (empty($feedSites)) {
            $siteIds = $this->getAllSiteIds($Feed);
        } else {
            $siteIds = $this->getSiteIdsBySiteIdControlValues($Feed, $feedSites);
        }

        $siteIdsExclude = $this->getSiteIdsBySiteIdControlValues($Feed, $feedSitesExclude, false);

        return array_diff($siteIds, $siteIdsExclude);
    }

    /**
     * Get total item count for a feed.
     *
     * @param FeedInstance $Feed
     * @return int
     * @throws QUI\Exception
     */
    protected function getTotalItemCount(Feed $Feed): int
    {
        return $this->collectFeedItems($Feed)->count();
    }

    /**
     * Return configured feed limit.
     *
     * @param FeedInstance $Feed
     * @return int
     */
    protected function getFeedLimit(FeedInstance $Feed): int
    {
        $feedLimit = (int)$Feed->getAttribute('feedlimit');

        if (empty($feedLimit)) {
            return 10;
        }

        return $feedLimit;
    }

    /**
     * @param FeedInstance $Feed
     * @return string
     */
    protected function getFeedSqlOrder(FeedInstance $Feed): string
    {
        return match ((string)$Feed->getAttribute('feedOrder')) {
            'editDate' => 'e_date DESC',
            default => 'release_from DESC, c_date DESC'
        };
    }

    /**
     * @param QueryBuilder $QueryBuilder
     * @param FeedInstance $Feed
     */
    protected function applyFeedOrder(QueryBuilder $QueryBuilder, FeedInstance $Feed): void
    {
        foreach (explode(',', $this->getFeedSqlOrder($Feed)) as $order) {
            [$field, $direction] = array_pad(explode(' ', trim($order), 2), 2, 'ASC');
            $direction = strtoupper($direction);

            if ($direction !== 'ASC' && $direction !== 'DESC') {
                $direction = 'ASC';
            }

            $QueryBuilder->addOrderBy(Doctrine::quoteIdentifier($field), $direction);
        }
    }

    /**
     * @param FeedInstance $Feed
     * @return string
     */
    protected function getFeedSearch(FeedInstance $Feed): string
    {
        $feedSearch = $Feed->getAttribute('feedSearch');

        if (!is_string($feedSearch)) {
            return '';
        }

        return trim($feedSearch);
    }

    /**
     * @return array<int, string>
     */
    protected function getFeedSearchFields(FeedInstance $Feed): array
    {
        $fields = [];

        if (!empty($Feed->getAttribute('feedSearchFieldTitle'))) {
            $fields[] = 'title';
        }

        if (!empty($Feed->getAttribute('feedSearchFieldShort'))) {
            $fields[] = 'short';
        }

        if (!empty($Feed->getAttribute('feedSearchFieldContent'))) {
            $fields[] = 'content';
        }

        if (!empty($fields)) {
            return $fields;
        }

        return [
            'title',
            'short',
            'content'
        ];
    }

    protected function applyFeedSearch(QueryBuilder $QueryBuilder, FeedInstance $Feed): void
    {
        $feedSearch = $this->getFeedSearch($Feed);

        if ($feedSearch === '') {
            return;
        }

        $searchParts = [];

        foreach ($this->getFeedSearchFields($Feed) as $field) {
            $searchParts[] = Doctrine::quoteIdentifier($field) . ' LIKE :feedSearch';
        }

        $QueryBuilder
            ->andWhere($QueryBuilder->expr()->or(...$searchParts))
            ->setParameter('feedSearch', '%' . $feedSearch . '%');
    }

    /**
     * @param FeedInstance $Feed
     * @return array<int, int>
     */
    protected function getAllSiteIds(FeedInstance $Feed): array
    {
        $Project = $Feed->getProject();
        $table = $this->getProjectTableName($Project);
        $feedLimit = $this->getFeedLimit($Feed);
        $QueryBuilder = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($table))
            ->where(Doctrine::quoteIdentifier('active') . ' = :active')
            ->andWhere(Doctrine::quoteIdentifier('deleted') . ' = :deleted')
            ->setParameter('active', 1)
            ->setParameter('deleted', 0);

        $this->applyFeedSearch($QueryBuilder, $Feed);
        $this->applyFeedOrder($QueryBuilder, $Feed);

        if ($feedLimit > 0) {
            $QueryBuilder->setMaxResults($feedLimit);
        }

        return array_map('intval', $QueryBuilder->executeQuery()->fetchFirstColumn());
    }

    /**
     * Get all Site IDs based on the values of the controls/projects/project/site/Select control
     *
     * @param FeedInstance $Feed
     * @param array<int, string|int> $values
     * @param bool $useFeedLimit
     * @return int[]
     * @throws Exception
     */
    protected function getSiteIdsBySiteIdControlValues(Feed $Feed, array $values, bool $useFeedLimit = true): array
    {
        $Project = $Feed->getProject();
        $table = $this->getProjectTableName($Project);
        $idCount = 0;
        $strCount = 0;

        $whereParts = [];
        $whereParameters = [];
        $childPageIDs = [];

        $feedLimit = $this->getFeedLimit($Feed);

        foreach ($values as $needle) {
            if (is_numeric($needle)) {
                $parameter = 'id' . $idCount;
                $whereParts[] = Doctrine::quoteIdentifier('id') . ' = :' . $parameter;
                $whereParameters[$parameter] = (int)$needle;

                $idCount++;
                continue;
            }

            // Search for children of this site

            if (preg_match("~p[0-9]+~i", $needle)) {
                $parentSiteID = (int)substr($needle, 1);
                $childPageIDs = array_merge($childPageIDs, $Project->get($parentSiteID)->getChildrenIdsRecursive());
                continue;
            }

            // Search for type
            $parameter = 'type' . $strCount;
            $whereParts[] = Doctrine::quoteIdentifier('type') . ' LIKE :' . $parameter;
            $whereParameters[$parameter] = $needle;

            $strCount++;
        }

        // Create the part of the query for the site ids of child sites.
        // `id` IN ( id1, id2, id3, id4 )
        if (!empty($childPageIDs)) {
            $childPageIDs = array_map('intval', array_unique($childPageIDs));
            $whereParts[] = Doctrine::quoteIdentifier('id') . ' IN (:childPageIds)';
        }

        if (empty($whereParts)) {
            return [];
        }

        $QueryBuilder = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($table))
            ->where(Doctrine::quoteIdentifier('active') . ' = :active')
            ->andWhere(Doctrine::quoteIdentifier('deleted') . ' = :deleted')
            ->setParameter('active', 1)
            ->setParameter('deleted', 0);

        $QueryBuilder->andWhere($QueryBuilder->expr()->or(...$whereParts));

        foreach ($whereParameters as $parameter => $value) {
            $QueryBuilder->setParameter($parameter, $value);
        }

        if (!empty($childPageIDs)) {
            $QueryBuilder->setParameter('childPageIds', $childPageIDs, ArrayParameterType::INTEGER);
        }

        $this->applyFeedSearch($QueryBuilder, $Feed);
        $this->applyFeedOrder($QueryBuilder, $Feed);

        if ($useFeedLimit && $feedLimit > 0) {
            $QueryBuilder->setMaxResults($feedLimit);
        }

        return array_map('intval', $QueryBuilder->executeQuery()->fetchFirstColumn());
    }

    /**
     * Parses the value of the site select control and returns all selected Site IDs
     *
     * @param QUI\Projects\Project $Project
     * @param string $siteSelectValue
     *
     * @return array<int, int> - Site IDs
     * @throws QUI\Exception
     */
    protected function parseSiteSelect(QUI\Projects\Project $Project, string $siteSelectValue): array
    {
        $ids = [];

        // All sites, if no sites were selected.
        if (empty($siteSelectValue)) {
            $queryParams = [
                'order' => 'release_from DESC, c_date DESC'
            ];

            $ids = $Project->getSitesIds($queryParams);

            return array_map(function ($entry) {
                return (int)$entry['id'];
            }, $ids);
        }

        // Get the IDs of the selected sites
        $table = $this->getProjectTableName($Project);
        $sites = explode(';', $siteSelectValue);

        $idCount = 0;
        $strCount = 0;

        $whereParts = [];
        $whereParameters = [];
        $childPageIDs = [];

        foreach ($sites as $needle) {
            //
            if (is_numeric($needle)) {
                $parameter = 'id' . $idCount;
                $whereParts[] = Doctrine::quoteIdentifier('id') . ' = :' . $parameter;
                $whereParameters[$parameter] = (int)$needle;

                $idCount++;
                continue;
            }

            // Search for children of this site
            if (preg_match("~p[0-9]+~i", $needle)) {
                $parentSiteID = (int)substr($needle, 1);
                $childPageIDs = array_merge($childPageIDs, $Project->get($parentSiteID)->getChildrenIdsRecursive());
                continue;
            }

            // Search for type
            $parameter = 'type' . $strCount;
            $whereParts[] = Doctrine::quoteIdentifier('type') . ' LIKE :' . $parameter;
            $whereParameters[$parameter] = $needle;

            $strCount++;
        }

        // Create the part of the query for the site ids of child sites.
        // `id` IN ( id1, id2, id3, id4 )
        if (!empty($childPageIDs)) {
            $childPageIDs = array_map('intval', array_unique($childPageIDs));
            $whereParts[] = Doctrine::quoteIdentifier('id') . ' IN (:childPageIds)';
        }

        if (empty($whereParts)) {
            return [];
        }

        $QueryBuilder = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier($table))
            ->where(Doctrine::quoteIdentifier('active') . ' = :active')
            ->setParameter('active', 1)
            ->addOrderBy(Doctrine::quoteIdentifier('release_from'), 'DESC')
            ->addOrderBy(Doctrine::quoteIdentifier('c_date'), 'DESC');

        $QueryBuilder->andWhere($QueryBuilder->expr()->or(...$whereParts));

        foreach ($whereParameters as $parameter => $value) {
            $QueryBuilder->setParameter($parameter, $value);
        }

        if (!empty($childPageIDs)) {
            $QueryBuilder->setParameter('childPageIds', $childPageIDs, ArrayParameterType::INTEGER);
        }

        return array_map('intval', $QueryBuilder->executeQuery()->fetchFirstColumn());
    }

    /**
     * Check if $Feed shall be published on $Site
     *
     * @param Feed $Feed
     * @param QUI\Projects\Site $Site
     * @return bool
     * @throws QUI\Exception
     */
    public function publishOnSite(Feed $Feed, QUI\Interfaces\Projects\Site $Site): bool
    {
        $publishSitesString = $Feed->getAttribute("publish_sites");
        $feedPublishSiteIDs = $this->parseSiteSelect($Feed->getProject(), $publishSitesString);

        if (!empty($publishSitesString) && !in_array($Site->getId(), $feedPublishSiteIDs)) {
            return false;
        }

        return parent::publishOnSite($Feed, $Site);
    }

    /**
     * @param QUI\Projects\Project $Project
     * @return string
     * @throws QUI\Exception
     */
    protected function getProjectTableName(QUI\Projects\Project $Project): string
    {
        $table = $Project->getAttribute('db_table');

        if (!is_string($table) || $table === '') {
            throw new QUI\Exception('Project db_table attribute is invalid.');
        }

        return $table;
    }
}
