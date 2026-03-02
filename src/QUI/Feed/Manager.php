<?php

namespace QUI\Feed;

use DOMDocument;
use QUI;
use QUI\Cache\LongTermCache;
use QUI\Database\Exception;
use QUI\Package\Package;
use QUI\Utils\DOM as DOMUtils;
use QUI\Utils\Grid;

use function array_key_exists;
use function array_merge;
use function class_exists;
use function file_exists;
use function hash;
use function is_a;
use function is_array;
use function is_null;
use function is_string;
use function json_decode;
use function preg_match_all;
use function str_replace;

/**
 * Class Feed Manager
 */
class Manager
{
    /**
     * Feed Table
     */
    const TABLE = 'feeds';

    /**
     * Runtime cache for feed types
     *
     * @var array<string, QUI\Feed\Interfaces\FeedTypeInterface>
     */
    protected array $feedTypes = [];

    /**
     * Add a new feed
     *
     * @param string $typeId - Feed type id
     * @param array<string, mixed> $params - Feed attributes
     *
     * @return Feed
     *
     * @throws QUI\Exception
     */
    public function addFeed(string $typeId, array $params): Feed
    {
        if (!$this->existsType($typeId)) {
            throw new QUI\Exception([
                'quiqqer/feed',
                'exception.Manager.type_does_not_exist',
                [
                    'typeId' => $typeId
                ]
            ]);
        }

        $DefaultProject = QUI::getProjectManager()::getStandard();
        if (!$DefaultProject) {
            throw new QUI\Exception('Unable to determine default project.');
        }

        QUI::getDataBase()->insert(
            QUI::getDBTableName(self::TABLE),
            [
                'type_id' => $typeId,
                'project' => $DefaultProject->getName(),
                'lang' => $DefaultProject->getLang()
            ]
        );

        $PDO = QUI::getDataBase()->getPDO();

        if (!$PDO) {
            throw new QUI\Exception('No PDO instance available.');
        }

        $id = $PDO->lastInsertId();
        $Feed = new Feed((int)$id);

        $Feed->setAttributes($this->filterFeedParams($typeId, $params));
        $Feed->save();

        return $Feed;
    }

    /**
     * Return the Feed
     *
     * @param integer $feedId - ID of the Feed
     *
     * @return Feed
     * @throws QUI\Exception
     */
    public function getFeed(int $feedId): Feed
    {
        return new Feed($feedId);
    }

    /**
     * Delete a feed
     *
     * @param integer $feedId - ID of the Feed
     */
    public function deleteFeed(int $feedId): void
    {
        try {
            $this->getFeed($feedId);

            QUI::getDataBase()->delete(
                QUI::getDBTableName(Manager::TABLE),
                ['id' => $feedId]
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Return the feed entries
     *
     * @param array<string, mixed> $params
     *
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function getList(array $params = []): array
    {
        if (empty($params)) {
            return QUI::getDataBase()->fetch([
                'from' => QUI::getDBTableName(self::TABLE)
            ]);
        }

        $Grid = new Grid();

        $params = array_merge($Grid->parseDBParams($params), [
            'from' => QUI::getDBTableName(self::TABLE)
        ]);

        return QUI::getDataBase()->fetch($params);
    }

    /**
     * Get data of a specific feed type.
     *
     * @param string $typeId
     * @return QUI\Feed\Interfaces\FeedTypeInterface
     *
     * @throws QUI\Exception
     */
    public function getType(string $typeId): QUI\Feed\Interfaces\FeedTypeInterface
    {
        if (isset($this->feedTypes[$typeId])) {
            return $this->feedTypes[$typeId];
        }

        foreach ($this->getTypes() as $typeData) {
            if ($typeId === $typeData['id']) {
                /** @var class-string<QUI\Feed\Interfaces\FeedTypeInterface> $feedClass */
                $feedClass = $typeData['class'];
                $FeedType = new $feedClass($typeData);

                $this->feedTypes[$typeId] = $FeedType;

                return $FeedType;
            }
        }

        throw new QUI\Exception([
            'quiqqer/feed',
            'exception.Manager.type_does_not_exist',
            [
                'typeId' => $typeId
            ]
        ]);
    }

    /**
     * Get list of all available feed types
     *
     * @return array<int, array{
     *   id: string,
     *   class: class-string<QUI\Feed\Interfaces\FeedTypeInterface>,
     *   title: string,
     *   description: string|false,
     *   settingsHtml: string,
     *   attributes: array<int, string>,
     *   publishable: bool,
     *   pagination: bool,
     *   mimeType: string
     * }>
     */
    public function getTypes(): array
    {
        $cacheName = QUI\Feed\Utils\Utils::getFeedTypeCachePath();

        try {
            $types = LongTermCache::get($cacheName);

            if (!is_array($types)) {
                return [];
            }

            /** @var array<int, array{
             *   id: string,
             *   class: class-string<QUI\Feed\Interfaces\FeedTypeInterface>,
             *   title: string,
             *   description: string|false,
             *   settingsHtml: string,
             *   attributes: array<int, string>,
             *   publishable: bool,
             *   pagination: bool,
             *   mimeType: string
             * }> $types */
            return $types;
        } catch (\Exception) {
            // no worries; re-build cache
        }

        // @todo cache

        $types = [];

        foreach ($this->getFeedXmlFiles() as $xmlFile) {
            $Document = new DOMDocument();
            $Document->load($xmlFile);

            $feedTypes = $Document->getElementsByTagName('feedType');

            foreach ($feedTypes as $FeedTypeNode) {
                $feedClass = $FeedTypeNode->getAttribute('class');

                if (empty($feedClass)) {
                    QUI\System\Log::addError(
                        'quiqqer/feed - Feed from feeds.xml file "' . $xmlFile . '"'
                        . ' is missing the "class" attribute. Feed is ignored!'
                    );

                    continue;
                }

                if (
                    !class_exists($feedClass) || !is_a(
                        $feedClass,
                        QUI\Feed\Interfaces\FeedTypeInterface::class,
                        true
                    )
                ) {
                    QUI\System\Log::addError(
                        'quiqqer/feed - Feed class "' . $feedClass . '" from feeds.xml file "' . $xmlFile . '"'
                        . ' does not exist or does not implement "QUI\Feed\Interfaces\Feed". Feed is ignored!'
                    );

                    continue;
                }

                /** @var class-string<QUI\Feed\Interfaces\FeedTypeInterface> $feedClass */
                $type = [
                    'id' => $this->parseFeedClassToHash($feedClass),
                    'class' => $feedClass
                ];

                // Title (required!)
                $title = $FeedTypeNode->getElementsByTagName('title');

                if (!$title->length) {
                    QUI\System\Log::addError(
                        'quiqqer/feed - Feed class "' . $feedClass . '" from feeds.xml file "' . $xmlFile . '"'
                        . ' does not have a "title" attribute in the <feed> tag. Feed is ignored!'
                    );

                    continue;
                }

                $titleNode = $title->item(0);
                if (!$titleNode) {
                    continue;
                }

                $type['title'] = $this->normalizeXmlTextValue(
                    DOMUtils::getTextFromNode($titleNode)
                );

                // Description
                $desc = $FeedTypeNode->getElementsByTagName('description');

                if ($desc->length) {
                    $descNode = $desc->item(0);
                    $type['description'] = $descNode
                        ? $this->normalizeXmlTextValue(DOMUtils::getTextFromNode($descNode))
                        : false;
                } else {
                    $type['description'] = false;
                }

                // Settings
                $settings = $FeedTypeNode->getElementsByTagName('category');
                $settingsHtml = '';

                foreach ($settings as $settingNode) {
                    $settingsHtml .= DOMUtils::parseCategoryToHTML($settingNode);
                    $settingsHtml = str_replace(
                        'class="description"',
                        'class="field-container-item-desc"',
                        $settingsHtml
                    );
                }

                $type['settingsHtml'] = $settingsHtml;

                // Parse available attributes from settings HTML
                preg_match_all('#name=[\'|"](\w*)[\'|"]#', $settingsHtml, $matches);
                $type['attributes'] = !empty($matches[1]) ? $matches[1] : [];

                // Special attributes
                $publishable = $FeedTypeNode->getElementsByTagName('publishable');
                $type['publishable'] = false;

                if ($publishable->length) {
                    $publishableNode = $publishable->item(0);
                    $type['publishable'] = $publishableNode && !empty($publishableNode->nodeValue);
                }

                $pagination = $FeedTypeNode->getElementsByTagName('pagination');
                $type['pagination'] = false;

                if ($pagination->length) {
                    $paginationNode = $pagination->item(0);
                    $type['pagination'] = $paginationNode && !empty($paginationNode->nodeValue);
                }

                // Mime type
                $mimeType = $FeedTypeNode->getElementsByTagName('mimeType');
                $type['mimeType'] = 'application/xml'; // fallback

                if ($mimeType->length) {
                    $mimeTypeNode = $mimeType->item(0);
                    $type['mimeType'] = $mimeTypeNode && $mimeTypeNode->nodeValue
                        ? $mimeTypeNode->nodeValue
                        : 'application/xml';
                } else {
                    QUI\System\Log::addNotice(
                        'quiqqer/feed - Feed type "' . $feedClass . '" does not have a mimeType set in feed.xml.'
                        . ' Using default "application/xml" mime type.'
                    );
                }

                $types[] = $type;
            }
        }

        LongTermCache::set($cacheName, $types);

        return $types;
    }

    /**
     * Check if a specific feed type exists.
     *
     * @param string $typeId
     * @return bool
     */
    public function existsType(string $typeId): bool
    {
        try {
            $this->getType($typeId);
            return true;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return false;
        }
    }

    /**
     * Build feed and write to cache.
     *
     * @param Feed $Feed
     * @return void
     */
    public function buildFeed(Feed $Feed): void
    {
        $this->deleteFeedOutputCache($Feed);

        for ($pageNo = 0; $pageNo <= $Feed->getPageCount(); $pageNo++) {
            $output = $Feed->output($pageNo);
            $cacheName = $this->getFeedOutputCacheName($Feed, $pageNo);

            try {
                LongTermCache::set($cacheName, $output);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }
    }

    /**
     * Check if feed is built and is available in the cache.
     *
     * @param Feed $Feed
     * @param int|null $page (optional)
     * @return bool
     */
    public function isFeedBuilt(Feed $Feed, ?int $page = null): bool
    {
        if (!is_null($page)) {
            $cacheName = $this->getFeedOutputCacheName($Feed, $page);

            try {
                LongTermCache::get($cacheName);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
                return false;
            }

            return true;
        }

        for ($pageNo = 0; $pageNo <= $Feed->getPageCount(); $pageNo++) {
            $cacheName = $this->getFeedOutputCacheName($Feed, $pageNo);

            try {
                LongTermCache::get($cacheName);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
                return false;
            }
        }

        return true;
    }

    /**
     * Returns feed output
     *
     * @param Feed $Feed
     * @param int $pageNo (optional)
     * @return string
     */
    public function getFeedOutput(Feed $Feed, int $pageNo = 0): string
    {
        $cacheName = $this->getFeedOutputCacheName($Feed, $pageNo);

        try {
            return LongTermCache::get($cacheName);
        } catch (\Exception) {
            // re-build cache
        }

        $output = $Feed->output($pageNo);

        LongTermCache::set($cacheName, $output);

        return $output;
    }

    /**
     * Delete feed cache
     *
     * @param Feed $Feed
     * @return void
     */
    public function deleteFeedOutputCache(Feed $Feed): void
    {
        LongTermCache::clear($this->getFeedOutputCacheName($Feed));
    }

    /**
     * Geed internal cache name of feed output.
     *
     * @param Feed $Feed
     * @param int|null $pageNo (optional) - If omitted, return cache path for ALL feed pages
     * @return string
     */
    protected function getFeedOutputCacheName(Feed $Feed, ?int $pageNo = null): string
    {
        $cacheName = 'quiqqer/feed/' . $Feed->getId();

        if ($pageNo) {
            $cacheName .= '/' . $pageNo;
        }

        return $cacheName;
    }

    /**
     * Get list of all feed.xml files of installed QUIQQER packages.
     *
     * @return string[]
     */
    protected function getFeedXmlFiles(): array
    {
        $packages = QUI::getPackageManager()->getInstalled();
        $list = [];

        /* @var $Package Package */
        foreach ($packages as $package) {
            try {
                $Package = QUI::getPackage($package['name']);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                continue;
            }

            if (!$Package->isQuiqqerPackage()) {
                continue;
            }

            $feedXmlFile = $Package->getDir() . '/feeds.xml';

            if (file_exists($feedXmlFile)) {
                $list[] = $feedXmlFile;
            }
        }

        return $list;
    }

    /**
     * Return the number of the feeds
     *
     * @return integer
     * @throws Exception
     */
    public function count(): int
    {
        $result = QUI::getDataBase()->fetch([
            'count' => [
                'select' => 'id',
                'as' => 'count'
            ],
            'from' => QUI::getDBTableName(self::TABLE)
        ]);

        return (int)$result[0]['count'];
    }

    /**
     * Filter all feed parameters (from frontend/QUIQQER backend)
     *
     * @param string $typeId
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws QUI\Exception
     */
    public function filterFeedParams(string $typeId, array $params): array
    {
        $FeedType = $this->getType($typeId);
        $feedTypeAttributes = $FeedType->getAttribute('attributes');
        $feedTypeAttributes = is_array($feedTypeAttributes) ? $feedTypeAttributes : [];

        $feedAttributes = array_merge(
            [
                'feedDescription',
                'feedImage',
                'feedName',
                'feedlimit',
                'feedtype',
                'pageSize',
                'project',
                'publish',
                'publish-sites',
                'split',
                'directOutput'
            ],
            $feedTypeAttributes
        );

        $sanitizedAttributes = [];

        foreach ($feedAttributes as $attribute) {
            if (!is_string($attribute)) {
                continue;
            }

            if (!array_key_exists($attribute, $params)) {
                $sanitizedAttributes[$attribute] = null;
                continue;
            }

            switch ($attribute) {
                case 'project':
                    if (!is_string($params['project'])) {
                        break;
                    }

                    $projects = json_decode($params['project'], true);

                    if (is_array($projects) && !empty($projects) && isset($projects[0]) && is_array($projects[0])) {
                        $project = $projects[0];

                        if (isset($project['project'])) {
                            $sanitizedAttributes['project'] = $project['project'];
                        }

                        if (isset($project['lang'])) {
                            $sanitizedAttributes['lang'] = $project['lang'];
                        }
                    }
                    break;

                default:
                    $sanitizedAttributes[$attribute] = $params[$attribute];
            }
        }

        return $sanitizedAttributes;
    }

    /**
     * @param string $feedClass
     * @return string
     */
    protected function parseFeedClassToHash(string $feedClass): string
    {
        return hash('sha256', $feedClass);
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function normalizeXmlTextValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            return '';
        }

        foreach ($value as $entry) {
            if (is_string($entry)) {
                return $entry;
            }
        }

        return '';
    }
}
