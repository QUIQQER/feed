<?php

/**
 * This file contains QUI\Feed\Interfaces\Channel
 */

namespace QUI\Feed\Interfaces;

/**
 * Interface Feed
 */
interface ChannelInterface
{
    /**
     * Add a feed item
     *
     * @param FeedItemInterface $Item
     */
    public function addItem(FeedItemInterface $Item);

    /**
     * Create an item and add it to the channel
     *
     * @param array $params
     *
     * @return FeedItemInterface
     */
    public function createItem(array $params = []): FeedItemInterface;

    /**
     * Return the feed items
     *
     * @return array
     */
    public function getItems(): array;

    /**
     * Set the title of the channel
     *
     * @param string $title
     */
    public function setTitle(string $title);

    /**
     * Set the description of the channel
     *
     * @param string $description
     */
    public function setDescription(string $description);

    /**
     * Set the unix timestamp
     *
     * @param integer $timestamp - Unix timestamp
     */
    public function setDate(int $timestamp);

    /**
     * Set the language of the channel
     *
     * @param string $language
     */
    public function setLanguage(string $language);

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setAttribute(string $name, mixed $value);

    /**
     * Host
     */

    /**
     * Set the main host
     *
     * @param string $host - https://my.host.com
     */
    public function setHost(string $host);

    /**
     * Return the main host
     *
     * @return string
     */
    public function getHost(): string;
}
