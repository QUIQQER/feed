<?php

namespace QUI\Feed\Handler;

use QUI;
use QUI\Feed\Feed;
use QUI\Feed\Interfaces\ChannelInterface as ChannelInterface;
use QUI\Feed\Interfaces\FeedTypeInterface as FeedInterface;
use QUI\QDOM;

/**
 * Class AbstractFeed
 */
abstract class AbstractFeedType extends QDOM implements FeedInterface
{
    /**
     * @param array<string, mixed> $attributes - Feed type attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setAttributes($attributes);
    }

    /**
     * Channel list
     *
     * @var array<int, ChannelInterface>
     */
    protected array $channels = [];

    /**
     * Add a channel to the feed
     *
     * @param ChannelInterface $Channel
     */
    public function addChannel(ChannelInterface $Channel): void
    {
        $this->channels[] = $Channel;
    }

    /**
     * Return the channels
     *
     * @return array<int, ChannelInterface>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Return the Feed as an XML string
     *
     * @param int|null $page (optional) - Get a specific page of the feed (only required if feed is paginated)
     * @return string - Feed as XML string
     */
    abstract public function create(Feed $Feed, ?int $page = null): string;

    /**
     * Returns the number of pages of this feed.
     *
     * @param Feed $Feed
     * @return int - Returns the number of pages or 0 if nor pages are used
     */
    public function getPageCount(Feed $Feed): int
    {
        return 0;
    }

    /**
     * Check if $Feed shall be published on $Site
     *
     * @param Feed $Feed
     * @param QUI\Interfaces\Projects\Site $Site
     * @return bool
     */
    public function publishOnSite(Feed $Feed, QUI\Interfaces\Projects\Site $Site): bool
    {
        return !empty($Feed->getAttribute('publish')) && !empty($this->getAttribute('publishable'));
    }
}
