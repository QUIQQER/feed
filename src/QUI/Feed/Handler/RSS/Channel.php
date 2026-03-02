<?php

/**
 * This file contains \QUI\Feed\Handler\RSS\Channel
 */

namespace QUI\Feed\Handler\RSS;

use QUI\Feed\Handler\AbstractChannel;
use QUI\Feed\Interfaces\FeedItemInterface;

/**
 * Class Channel - RSS Feed 2.0
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
