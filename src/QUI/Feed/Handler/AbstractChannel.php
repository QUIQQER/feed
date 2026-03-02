<?php

/**
 * This file contains \QUI\Feed\Handler\AbstractFeed
 */

namespace QUI\Feed\Handler;

use QUI\Feed\Interfaces\ChannelInterface;
use QUI\Feed\Interfaces\FeedItemInterface;
use QUI\QDOM;

/**
 * Class AbstractChannel
 */
abstract class AbstractChannel extends QDOM implements ChannelInterface
{
    /**
     * Main host
     *
     * @var string
     */
    protected string $host = '';

    /**
     * RSS Channel items
     *
     * @var array
     */
    protected array $items = [];

    /**
     * constructor
     *
     * @param array $params
     */
    public function __construct(array $params = [])
    {
        // defaults
        $this->setAttributes([
            'title' => '',
            'description' => '',
            'timestamp' => '',
            'language' => ''
        ]);

        $this->setAttributes($params);
    }

    /**
     * Add an item to the channel
     *
     * @param FeedItemInterface $Item
     */
    public function addItem(FeedItemInterface $Item): void
    {
        $this->items[] = $Item;
    }

    /**
     * Return the feed items
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Set the title of the channel
     *
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->setAttribute('title', $title);
    }

    /**
     * Set the description of the channel
     *
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->setAttribute('description', $description);
    }

    /**
     * Set the unix timestamp
     *
     * @param integer $timestamp - Unix timestamp
     */
    public function setDate(int $timestamp): void
    {
        $this->setAttribute('timestamp', $timestamp);
    }

    /**
     * Set the language of the channel
     *
     * @param string $language
     */
    public function setLanguage(string $language): void
    {
        $this->setAttribute('language', $language);
    }

    /**
     * Set the main host
     *
     * @param string $host - eq http://www.myhost.com
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * Return the main host
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }
}
