<?php

/**
 * This file contains \QUI\Feed\Handler\CSV\Channel
 */

namespace QUI\Feed\Handler\CSV;

use QUI\Feed\Handler\AbstractChannel;
use QUI\Feed\Interfaces\FeedItemInterface;

/**
 * Class Channel - CSV Feed
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
