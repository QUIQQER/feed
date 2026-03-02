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
    public function addItem(FeedItemInterface $Item): void;

    /**
     * Create an item and add it to the channel
     *
     * @param array<string, mixed> $params
     *
     * @return FeedItemInterface
     */
    public function createItem(array $params = []): FeedItemInterface;

    /**
     * Return the feed items
     *
     * @return array<int, FeedItemInterface>
     */
    public function getItems(): array;

    /**
     * Set the title of the channel
     *
     * @param string $title
     */
    public function setTitle(string $title): void;

    /**
     * Set the description of the channel
     *
     * @param string $description
     */
    public function setDescription(string $description): void;

    /**
     * Set the unix timestamp
     *
     * @param integer $timestamp - Unix timestamp
     */
    public function setDate(int $timestamp): void;

    /**
     * Set the language of the channel
     *
     * @param string $language
     */
    public function setLanguage(string $language): void;

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setAttribute(string $name, mixed $value): void;

    /**
     * Host
     */

    /**
     * Set the main host
     *
     * @param string $host - https://my.host.com
     */
    public function setHost(string $host): void;

    /**
     * Return the main host
     *
     * @return string
     */
    public function getHost(): string;
}
