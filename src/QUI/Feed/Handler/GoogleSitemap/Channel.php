<?php

/**
 * This file contains \QUI\Feed\Handler\GoogleSitemap\Channel
 */

namespace QUI\Feed\Handler\GoogleSitemap;

use QUI\Feed\Handler\AbstractChannel;
use QUI\Feed\Interfaces\FeedItemInterface;

/**
 * Class Channel - Google Sitemap XML
 */
class Channel extends AbstractChannel
{
    /**
     * @param array<string, mixed> $params
     * @return FeedItemInterface
     */
    public function createItem(array $params = []): FeedItemInterface
    {
        $Item = new Item($params);
        $this->addItem($Item);

        return $Item;
    }
}
