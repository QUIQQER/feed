<?php

/**
 * Check if a feed is downloadable
 *
 * @param int $feedId
 * @return void
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_feed_ajax_backend_downloadCheck',
    function ($feedId) {
        $FeedManager = new QUI\Feed\Manager();
        $Feed = $FeedManager->getFeed((int)$feedId);

        return $FeedManager->isFeedBuilt($Feed) || $Feed->getAttribute('directOutput');
    },
    ['feedId'],
    'Permission::checkAdminUser'
);
