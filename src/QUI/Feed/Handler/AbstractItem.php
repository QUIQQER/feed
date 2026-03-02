<?php

/**
 * this file contains \QUI\Feed\Handler\AbstractItem
 */

namespace QUI\Feed\Handler;

use QUI;
use QUI\Feed\Interfaces\FeedItemInterface as InterfaceItem;
use QUI\Projects\Media\Image;
use QUI\QDOM;

/**
 * Class AbstractItem
 */
abstract class AbstractItem extends QDOM implements InterfaceItem
{
    /**
     * Image for the feed item
     *
     * @var ?QUI\Projects\Media\Image
     */
    protected ?QUI\Projects\Media\Image $Image = null;

    /**
     * constructor
     *
     * @param array<string, mixed> $params
     */
    public function __construct(array $params = [])
    {
        $this->setAttributes($params);
    }

    /**
     * Set the title of the feed item
     *
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->setAttribute('title', $title);
    }

    /**
     * Set the description of the feed item
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
        $this->setAttribute('time', $timestamp);
    }

    /**
     * Set the link of the feed item
     *
     * @param string $link
     */
    public function setLink(string $link): void
    {
        $this->setAttribute('link', $link);
    }

    /**
     * Set the permalink
     * RSS = GUID
     *
     * @param string $link
     */
    public function setPermaLink(string $link): void
    {
        $this->setAttribute('permalink', $link);
    }

    /**
     * Set the language of the feed item
     *
     * @param string $language
     */
    public function setLanguage(string $language): void
    {
        $this->setAttribute('lang', $language);
    }

    /**
     * Image
     */

    /**
     * Set an image for the feed item
     *
     * @param Image $Image
     */
    public function setImage(QUI\Projects\Media\Image $Image): void
    {
        $this->Image = $Image;
    }

    /**
     * Return the feed image
     *
     * @return null|QUI\Projects\Media\Image
     */
    public function getImage(): ?Image
    {
        return $this->Image;
    }

    /**
     * Remove the image for the item
     */
    public function removeImage(): void
    {
        $this->Image = null;
    }
}
