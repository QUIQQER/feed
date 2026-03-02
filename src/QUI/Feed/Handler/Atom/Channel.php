<?php

/**
 * This file contains \QUI\Feed\Handler\Atom\Channel
 */

namespace QUI\Feed\Handler\Atom;

use QUI\Feed\Handler\AbstractChannel;
use QUI\Feed\Interfaces\FeedItemInterface;

/**
 * Class Channel - Atom Feed
 */
class Channel extends AbstractChannel
{
    /**
     * @param array $params
     * @return FeedItemInterface
     */
    public function createItem(array $params = []): FeedItemInterface
    {
        $Item = new Item($params);
        $this->addItem($Item);

        return $Item;
    }
}
