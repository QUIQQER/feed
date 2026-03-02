<?php

/**
 * This file contains \QUI\Feed\Feed;
 */

namespace QUI\Feed;

use QUI;
use QUI\Exception;
use QUI\Utils\Security\Orthos;

use function json_decode;
use function json_encode;

/**
 * Class Feed
 * One Feed, you can edit and save the feed, and get the feed as an XML / RSS Feed
 */
class Feed extends QUI\QDOM
{
    /**
     * ID of the Feed
     *
     * @var integer
     */
    protected int $feedId;

    /**
     * ID of the Feed type (hashed classname)
     *
     * @var string
     */
    protected mixed $typeId = '';

    /**
     * @var ?QUI\Projects\Project
     */
    protected ?QUI\Projects\Project $Project = null;

    /**
     * @var ?QUI\Feed\Interfaces\FeedTypeInterface
     */
    protected ?Interfaces\FeedTypeInterface $FeedType = null;

    /**
     * Constructor
     *
     * @param integer $feedId
     *
     * @throws QUI\Exception
     */
    public function __construct(int $feedId)
    {
        $this->feedId = $feedId;

        $data = QUI::getDataBase()->fetch([
            'from' => QUI::getDBTableName(Manager::TABLE),
            'where' => [
                'id' => $this->feedId
            ],
            'limit' => 1
        ]);

        if (!isset($data[0])) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/feed', 'exception.feed.not.found')
            );
        }

        $data = $data[0];

        if (!empty($data['type_id'])) {
            $this->typeId = $data['type_id'];
        }

        $this->setAttributes($data);

        if (!empty($data['feed_settings'])) {
            $feedSettings = json_decode($data['feed_settings'], true);
            $this->setAttributes($feedSettings);
        }

        // Build project
        if (!empty($data['project']) && !empty($data['lang'])) {
            $this->Project = QUI::getProjectManager()::getProject($data['project'], $data['lang']);
        } else {
            $this->Project = QUI::getProjectManager()::getStandard();
        }

        // Build FeedType
        $Manager = new Manager();
        $this->FeedType = $Manager->getType($this->typeId);
    }

    /**
     * Return the Feed-ID
     *
     * @return integer
     */
    public function getId(): int
    {
        return $this->feedId;
    }

    /**
     * @return string
     */
    public function getTypeId(): string
    {
        return $this->typeId;
    }

    /**
     * Return the feed type data (as configured in feed.xml)
     *
     * @return Interfaces\FeedTypeInterface
     * @throws Exception
     */
    public function getFeedTypeData(): Interfaces\FeedTypeInterface
    {
        $Manager = new Manager();
        return $Manager->getType($this->typeId);
    }

    /**
     * @return Interfaces\FeedTypeInterface
     */
    public function getFeedType(): QUI\Feed\Interfaces\FeedTypeInterface
    {
        return $this->FeedType;
    }

    /**
     * Return the feed attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        $attributes = parent::getAttributes();
        $attributes['id'] = $this->feedId;

        return $attributes;
    }

    /**
     * Save the feed
     *
     * @throws QUI\Exception
     */
    public function save(): void
    {
        $table = QUI::getDBTableName(Manager::TABLE);

        $feedlimit = (int)$this->getAttribute('feedlimit');
        $feedName = '';
        $feedDescription = '';

        if ($this->getAttribute('feedName')) {
            $feedName = Orthos::clear($this->getAttribute('feedName'));
        }

        if ($this->getAttribute('feedDescription')) {
            $feedDescription = Orthos::clear($this->getAttribute('feedDescription'));
        }

        $feedSettings = [];

        foreach ($this->FeedType->getAttribute('attributes') as $attribute) {
            $feedSettings[$attribute] = $this->getAttribute($attribute);
        }

        // Special attributes that are set directly to the feed settings array
        $feedSettings['directOutput'] = $this->getAttribute('directOutput');

        QUI::getDataBase()->update($table, [
            'project' => $this->getAttribute('project'),
            'lang' => $this->getAttribute('lang'),
//            'feedtype'        => $this->getFeedType(),
//            'feedsites'         => $this->getAttribute('feedsites'),
//            'feedsites_exclude' => $this->getAttribute('feedsites_exclude'),
            'feedlimit' => $feedlimit ?: 0,
            'feedName' => $feedName,
            'feedDescription' => $feedDescription,
            'pageSize' => $this->getAttribute("pageSize") ?: 0,
            'publish' => $this->getAttribute("publish") ? 1 : 0,
            'publish_sites' => $this->getAttribute("publish_sites"),
            'feedImage' => $this->getAttribute("feedImage"),
            'feed_settings' => json_encode($feedSettings),
            'type_id' => $this->getAttribute('type_id')
        ], [
            'id' => $this->getId()
        ]);

        // clear cache
        QUI\Cache\Manager::clear('quiqqer/feed/' . $this->getId());
    }

    /**
     * Output the feed as XML
     *
     * @param int $page - (optional) The pagenumber, if supported
     * @return string - Feed as XML string
     */
    public function output(int $page = 0): string
    {
        return $this->FeedType->create($this, $page);
    }

    /**
     * Returns the number of pages of this feed.
     */
    public function getPageCount(): int
    {
        return $this->FeedType->getPageCount($this);
    }

    public function getProject(): QUI\Projects\Project
    {
        return $this->Project;
    }

    /**
     * Check if this Feed should be published on $Site
     */
    public function publishOnSite(QUI\Interfaces\Projects\Site $Site): bool
    {
        return $this->FeedType->publishOnSite($this, $Site);
    }

    /**
     * Get feed URL
     */
    public function getUrl(): string
    {
        return $this->Project->getVHost(true, true) . 'feed=' . $this->getId() . '.xml';
    }
}
