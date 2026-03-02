<?php

namespace QUI\Feed\Handler;

use DOMDocument;
use PDO;
use QUI;
use QUI\Exception;
use QUI\Feed\Feed;
use QUI\Feed\Feed as FeedInstance;
use QUI\Feed\Interfaces\ChannelInterface;
use QUI\Feed\Utils\SimpleXML;

use function array_diff;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function ceil;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_numeric;
use function is_string;
use function method_exists;
use function preg_match;
use function rtrim;
use function strtotime;
use function substr;
use function time;

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
        $Project = $Feed->getProject();
        $projectHost = $Project->getVHost(true, true);
        $ids = $this->getSiteIds($Feed);

        // create feed
        foreach ($ids as $id) {
            try {
                $Site = $Project->get($id);
                $date = $Site->getAttribute('release_from');

                if ($date == '0000-00-00 00:00:00') {
                    $date = $Site->getAttribute('c_date');
                }

                $editDate = $Site->getAttribute('e_date');

                // Workaround bug  $Site->getCanonical() come with protocol
                $link = $Site->getUrlRewritten();
                $permalink = $Site->getCanonical();

                if (!str_contains($link, 'https:') && !str_contains($link, 'http:')) {
                    $link = $projectHost . $Site->getUrlRewritten();
                }

                if (!str_contains($permalink, 'https:') && !str_contains($permalink, 'http:')) {
                    $permalink = $projectHost . $Site->getCanonical();
                }

                /** @var QUI\Feed\Handler\AbstractItem $Item */
                $Item = $Channel->createItem([
                    'title' => $Site->getAttribute('title'),
                    'description' => $Site->getAttribute('short'),
                    'language' => $Project->getLang(),
                    'date' => strtotime($date),
                    'e_date' => strtotime($editDate),
                    'link' => $link,
                    'permalink' => $permalink,
                    'seoDirective' => $Site->getAttribute('quiqqer.meta.site.robots')
                ]);

                $Config = QUI::getPackage("quiqqer/feed")->getConfig();

                try {
                    //Check if the creation user should be picked
                    if ($Config?->get("common", "user") != "c_user") {
                        throw new QUI\Exception("Invalid user field choice!");
                    }

                    $User = QUI::getUsers()->get($Site->getAttribute("c_user"));
                    $Item->setAttribute("author", $User->getName());
                } catch (Exception) {
                    $Item->setAttribute(
                        "author",
                        $Config?->get("common", "author")
                    );
                }

                // Image
                $image = $Site->getAttribute('image_site');
                if (!$image) {
                    continue;
                }

                $Image = QUI\Projects\Media\Utils::getImageByUrl($image);
                $Item->setImage($Image);
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

        $feedLimit = (int)$Feed->getAttribute('feedlimit');

        if (empty($feedLimit)) {
            $feedLimit = 10;
        }

        $Project = QUI::getProject(
            $Feed->getAttribute('project'),
            $Feed->getAttribute('lang')
        );

        // All sites, if no sites were selected.
        if (empty($feedSites)) {
            $queryParams = [
                'order' => 'release_from DESC, c_date DESC'
            ];

            if ($feedLimit > 0) {
                $queryParams['limit'] = $feedLimit;
            }

            $ids = $Project->getSitesIds($queryParams);

            $siteIds = array_map(function ($entry) {
                return (int)$entry['id'];
            }, $ids);
        } else {
            $siteIds = $this->getSiteIdsBySiteIdControlValues($Feed, $feedSites);
        }

        $siteIdsExclude = $this->getSiteIdsBySiteIdControlValues($Feed, $feedSitesExclude);

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
        return count($this->getSiteIds($Feed));
    }

    /**
     * Get all Site IDs based on the values of the controls/projects/project/site/Select control
     *
     * @param FeedInstance $Feed
     * @param array<int, string|int> $values
     * @return int[]
     * @throws Exception
     */
    protected function getSiteIdsBySiteIdControlValues(Feed $Feed, array $values): array
    {
        $Project = $Feed->getProject();
        $PDO = QUI::getPDO();
        $table = $this->getProjectTableName($Project);
        $idCount = 0;
        $strCount = 0;

        $whereParts = [];
        $wherePrepared = [];
        $childPageIDs = [];

        $feedLimit = (int)$Feed->getAttribute('feedlimit');

        if (empty($feedLimit)) {
            $feedLimit = 10;
        }

        foreach ($values as $needle) {
            if (is_numeric($needle)) {
                $_id = ':id' . $idCount;

                $whereParts[] = " id = $_id ";

                $wherePrepared[] = [
                    'type' => PDO::PARAM_INT,
                    'value' => $needle,
                    'name' => $_id
                ];

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
            $_id = ':str' . $strCount;

            $whereParts[] = " type LIKE $_id ";
            $wherePrepared[] = [
                'type' => PDO::PARAM_STR,
                'value' => $needle,
                'name' => $_id
            ];

            $strCount++;
        }

        // Create the part of the query for the site ids of child sites.
        // `id` IN ( id1, id2, id3, id4 )
        if (!empty($childPageIDs)) {
            $childPageIDs = array_unique($childPageIDs);

            $idString = "";

            for ($i = 0; $i < count($childPageIDs); $i++) {
                $idString .= ":pageid" . $i . ",";
            }

            $idString = rtrim($idString, ",");
            $whereParts[] = " id IN ($idString) ";

            $i = 0;

            foreach ($childPageIDs as $id) {
                $wherePrepared[] = [
                    'type' => PDO::PARAM_INT,
                    'value' => $id,
                    'name' => ":pageid" . $i
                ];
                $i++;
            }
        }

        $where = implode(' OR ', $whereParts);

        // query
        $query = "
                SELECT id
                FROM {$table}
                WHERE active = 1 AND ($where)
                ORDER BY release_from DESC, c_date DESC
            ";

        if ($feedLimit > 0) {
            $query .= "LIMIT :limit";
        }

        // search
        $Statement = $PDO->prepare($query);

        foreach ($wherePrepared as $prepared) {
            $Statement->bindValue(
                $prepared['name'],
                $prepared['value'],
                $prepared['type']
            );
        }

        if ($feedLimit > 0) {
            $Statement->bindValue(':limit', $feedLimit, PDO::PARAM_INT);
        }

        $Statement->execute();
        $result = $Statement->fetchAll(PDO::FETCH_ASSOC);

        $ids = [];

        foreach ($result as $row) {
            $ids[] = $row['id'];
        }

        return $ids;
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
        $PDO = QUI::getPDO();
        $table = $this->getProjectTableName($Project);
        $sites = explode(';', $siteSelectValue);

        $idCount = 0;
        $strCount = 0;

        $whereParts = [];
        $wherePrepared = [];
        $childPageIDs = [];

        foreach ($sites as $needle) {
            //
            if (is_numeric($needle)) {
                $_id = ':id' . $idCount;

                $whereParts[] = " id = $_id ";

                $wherePrepared[] = [
                    'type' => PDO::PARAM_INT,
                    'value' => $needle,
                    'name' => $_id
                ];

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
            $_id = ':str' . $strCount;

            $whereParts[] = " type LIKE $_id ";
            $wherePrepared[] = [
                'type' => PDO::PARAM_STR,
                'value' => $needle,
                'name' => $_id
            ];

            $strCount++;
        }

        // Create the part of the query for the site ids of child sites.
        // `id` IN ( id1, id2, id3, id4 )
        if (!empty($childPageIDs)) {
            $childPageIDs = array_unique($childPageIDs);
            $idString = "";

            for ($i = 0; $i < count($childPageIDs); $i++) {
                $idString .= ":pageid" . $i . ",";
            }

            $idString = rtrim($idString, ",");
            $whereParts[] = " id IN ($idString) ";

            $i = 0;
            foreach ($childPageIDs as $id) {
                $wherePrepared[] = [
                    'type' => PDO::PARAM_INT,
                    'value' => $id,
                    'name' => ":pageid" . $i
                ];
                $i++;
            }
        }

        $where = implode(' OR ', $whereParts);

        // query
        $query = "
                SELECT id
                FROM {$table}
                WHERE active = 1 AND ($where)
                ORDER BY release_from DESC, c_date DESC
            ";

        // search
        $Statement = $PDO->prepare($query);

        foreach ($wherePrepared as $prepared) {
            $Statement->bindValue(
                $prepared['name'],
                $prepared['value'],
                $prepared['type']
            );
        }

        $Statement->execute();
        $result = $Statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $row) {
            $ids[] = $row['id'];
        }

        return $ids;
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
