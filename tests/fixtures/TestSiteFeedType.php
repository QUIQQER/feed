<?php

declare(strict_types=1);

namespace QUITests\Feed;

use QUI\Feed\Feed;
use QUI\Feed\Handler\RSS\Feed as RssFeed;

class TestSiteFeedType extends RssFeed
{
    /**
     * @return array<int, int>
     */
    public function getAllIds(Feed $Feed): array
    {
        return $this->getAllSiteIds($Feed);
    }

    /**
     * @param array<int, string|int> $values
     * @return array<int, int>
     */
    public function getSelectedIds(Feed $Feed, array $values, bool $useFeedLimit = true): array
    {
        return $this->getSiteIdsBySiteIdControlValues($Feed, $values, $useFeedLimit);
    }
}
