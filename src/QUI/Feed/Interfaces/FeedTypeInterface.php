<?php

namespace QUI\Feed\Interfaces;

use QUI;
use QUI\Feed\Feed;

/**
 * Interface Feed
 */
interface FeedTypeInterface extends QUI\QDOMInterface
{
    /**
     * @param array<string, mixed> $attributes - Feed type attributes
     */
    public function __construct(array $attributes = []);

    /**
     * Add a feed channel
     *
     * @param ChannelInterface $Channel
     */
    public function addChannel(ChannelInterface $Channel): void;

    /**
     * Create a new channel and add it to the feed
     *
     * @return ChannelInterface
     */
    public function createChannel(): ChannelInterface;

    /**
     * Return the channel list
     *
     * @return array<int, ChannelInterface>
     */
    public function getChannels(): array;

    /**
     * Return the Feed as an XML string
     *
     * @param Feed $Feed - The Feed that shall be created
     * @param int|null $page (optional) - Get a specific page of the feed (only required if feed is paginated)
     * @return string - Feed as XML string
     */
    public function create(Feed $Feed, ?int $page = null): string;

    /**
     * Returns the number of pages of this feed.
     *
     * @param Feed $Feed
     * @return int - Returns the number of pages or 0 if nor pages are used
     */
    public function getPageCount(Feed $Feed): int;

    /**
     * Check if $Feed shall be published on $Site
     */
    public function publishOnSite(Feed $Feed, QUI\Interfaces\Projects\Site $Site): bool;
}
