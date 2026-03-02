<?php

namespace QUI\Feed;

use Exception;
use QUI;
use QUI\Cache\LongTermCache;
use QUI\Rewrite;
use Symfony\Component\HttpFoundation\Response;

use function array_key_exists;
use function json_decode;
use function json_encode;

/**
 * Class Events -> System Events
 */
class EventHandler
{
    /**
     * quiqqer/core: onPackageSetup
     *
     * @param QUI\Package\Package $Package
     * @return void
     */
    public static function onPackageSetup(QUI\Package\Package $Package): void
    {
        if ($Package->getName() !== 'quiqqer/feed') {
            return;
        }

        LongTermCache::clear(QUI\Feed\Utils\Utils::getFeedTypeCachePath());

        try {
            self::patchV1();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        try {
            self::patchV2();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Patch database for migration from quiqqer/feed 1.*
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected static function patchV1(): void
    {
        $result = QUI::getDataBase()->fetch([
            'from' => QUI::getDBTableName(Manager::TABLE),
            'where' => [
                'type_id' => null
            ]
        ]);

        $feedIdRss = '1de938991bab7c523b9adbb631de5077588ecd348a68e7d993f619200f5a8bec';
        $feedIdAtom = 'b71ca88546347228c7a9057939de67a49852df3f5fc90fac389bc19f509f7bc1';
        $feedIdGoogleSitemap = '2db63240d86aee59430c7c17f92039cb954d2e88fde05889ea3a60a6266462cb';

        foreach ($result as $row) {
            $update = [];

            switch ($row['feedtype']) {
                case 'googleSitemap':
                    $update['type_id'] = $feedIdGoogleSitemap;
                    break;

                case 'atom':
                    $update['type_id'] = $feedIdAtom;
                    break;

                case 'rss':
                    $update['type_id'] = $feedIdRss;
                    break;
            }

            $update['feed_settings'] = json_encode([
                'feedsites' => !empty($row['feedsites']) ? $row['feedsites'] : '',
                'feedsites_exclude' => !empty($row['feedsites_exclude']) ? $row['feedsites_exclude'] : ''
            ]);

            QUI::getDataBase()->update(
                QUI::getDBTableName(Manager::TABLE),
                $update,
                [
                    'id' => $row['id']
                ]
            );
        }
    }

    /**
     * Patch feed attributes
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected static function patchV2(): void
    {
        $result = QUI::getDataBase()->fetch([
            'select' => ['id', 'feed_settings'],
            'from' => QUI::getDBTableName(Manager::TABLE)
        ]);

        foreach ($result as $row) {
            if (!empty($row['feed_settings'])) {
                $settings = json_decode($row['feed_settings'], true);
            } else {
                $settings = [];
            }

            if (!array_key_exists('directOutput', $settings)) {
                $settings['directOutput'] = true;

                QUI::getDataBase()->update(
                    QUI::getDBTableName(Manager::TABLE),
                    [
                        'feed_settings' => json_encode($settings)
                    ],
                    [
                        'id' => $row['id']
                    ]
                );
            }
        }
    }

    /**
     * event : on request
     *
     * @param Rewrite $Rewrite
     * @param string $url
     *
     * @return void
     */
    public static function onRequest(Rewrite $Rewrite, string $url): void
    {
        if (stripos($url, 'feed=') === false) {
            return;
        }

        if (!str_contains($url, '.xml')) {
            return;
        }

        $params = str_replace('.xml', '', $url);
        $params = explode('=', $params);

        if (!isset($params[1])) {
            return;
        }

        // The identifier of the name, can be either just the feed id or the feedid with the pagenomber, for paginated feeds
        $feedIdentifier = $params[1];
        $feedDetails = explode("-", $feedIdentifier);
        $feedId = (int)$feedDetails[0];
        $pageNo = 0;

        if (isset($feedDetails[1])) {
            $pageNo = (int)$feedDetails[1];
        }

        try {
            $Manager = new Manager();
            $Feed = $Manager->getFeed($feedId);
        } catch (Exception) {
            $Response = new Response("Feed not found", 404);
            $Response->send();
            exit;
        }

        if ($pageNo > $Feed->getPageCount()) {
            $Response = new Response("Page not found", 404);
            $Response->send();
            exit;
        }

        if (!$Manager->isFeedBuilt($Feed, $pageNo) && !$Feed->getAttribute('directOutput')) {
            $Response = new Response();
            $Response->setStatusCode(503);
            $Response->send();
            exit;
        }

        $mimeType = $Feed->getFeedType()->getAttribute('mimeType');

        header('Content-Type: ' . $mimeType . '; charset=UTF-8');
        echo $Manager->getFeedOutput($Feed, $pageNo);
        exit;
    }

    /**
     * event : site change it

     * @param QUI\Interfaces\Projects\Site $Site
     * @throws QUI\Database\Exception
     */
    public static function onSiteChange(QUI\Interfaces\Projects\Site $Site): void
    {
        // get feeds by project
        $Project = $Site->getProject();
        $Manager = new Manager();

        $projectName = $Project->getName();
        $projectLang = $Project->getLang();

        $feedList = $Manager->getList();

        foreach ($feedList as $feed) {
            if ($projectName != $feed['project']) {
                continue;
            }

            if ($projectLang != $feed['lang']) {
                continue;
            }

            // clear cache
            QUI\Cache\Manager::clear('quiqqer/feed/' . $feed['id']);
        }
    }

    /**
     * @param QUI\Template $Template
     * @throws Exception
     */
    public static function onTemplateGetHeader(QUI\Template $Template): void
    {
        $Manager = new Manager();
        $feedRows = $Manager->getList();
        $curProject = QUI::getRewrite()->getProject();
        $Site = QUI::getRewrite()->getSite();

        if (!$curProject || !$Site) {
            return;
        }

        foreach ($feedRows as $databaseRow) {
            $feedID = $databaseRow['id'];

            try {
                $Feed = new Feed($feedID);
            } catch (Exception) {
                QUI\System\Log::addWarning("Attempt to add non existing feed '" . $feedID . "' to header");
                continue;
            }

            try {
                $FeedType = $Manager->getType($Feed->getTypeId());
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                continue;
            }

            if (empty($Feed->getAttribute('publish')) || empty($FeedType->getAttribute('publishable'))) {
                continue;
            }

            $FeedProject = QUI::getProject(
                $Feed->getAttribute('project'),
                $Feed->getAttribute('lang')
            );

            // Only display feeds for the current project and language
            if ($curProject->getName() != $FeedProject->getName()) {
                continue;
            }

            if ($curProject->getLang() != $FeedProject->getLang()) {
                continue;
            }


            // Check if the feed should be included on this page
            if (!$Feed->publishOnSite($Site)) {
                continue;
            }

            $projectHost = $FeedProject->getVHost(true, true);
            $url = $projectHost . URL_DIR . 'feed=' . $Feed->getId() . '.xml';
            $mimeType = $FeedType->getAttribute('mimeType');

            $rssTag = '<link rel="alternate" type="' . $mimeType . '" href="' . $url . '" />' . PHP_EOL;
            $Template->extendHeader($rssTag);
        }
    }
}
